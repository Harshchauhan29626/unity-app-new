<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\Billing\MembershipSyncService;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BillingCheckoutController extends Controller
{
    public function __construct(
        private readonly ZohoBillingService $zohoBillingService,
        private readonly MembershipSyncService $membershipSyncService,
    ) {
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'plan_code' => ['required', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $result = $this->zohoBillingService->createHostedPageForSubscription($user, $validated['plan_code']);

            $this->recordPendingZohoPayment($user, $validated['plan_code'], (string) $result['hostedpage_id']);

            return response()->json([
                'success' => true,
                'message' => 'Hosted checkout URL created successfully.',
                'data' => [
                    'hostedpage_id' => $result['hostedpage_id'],
                    'checkout_url' => $result['checkout_url'],
                ],
            ]);
        } catch (ValidationException $validationException) {
            return response()->json([
                'success' => false,
                'message' => collect($validationException->errors())->flatten()->first() ?? 'Validation failed',
                'data' => [
                    'errors' => $validationException->errors(),
                ],
            ], 422);
        } catch (Throwable $throwable) {
            Log::error('Zoho checkout creation failed', [
                'user_id' => $user->id,
                'message' => 'Failed to generate checkout URL.',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate checkout URL.',
                'data' => [],
            ], 500);
        }
    }

    public function status(Request $request, string $hostedpage_id)
    {
        try {
            $payment = Payment::query()
                ->where('provider', 'zoho')
                ->where('zoho_hostedpage_id', $hostedpage_id)
                ->latest('created_at')
                ->first();

            $user = null;

            if ($payment) {
                $user = User::query()->where('id', $payment->user_id)->first();
            }

            if (! $user) {
                /** @var User|null $authUser */
                $authUser = $request->user();
                $user = $authUser;
            }

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for hosted page status.',
                    'data' => [
                        'hostedpage_id' => $hostedpage_id,
                    ],
                ], 404);
            }

            $hostedPageResponse = $this->zohoBillingService->getHostedPage($hostedpage_id);

            $hostedPage = data_get($hostedPageResponse, 'hostedpage')
                ?? data_get($hostedPageResponse, 'hosted_page')
                ?? data_get($hostedPageResponse, 'data.hostedpage')
                ?? [];

            $hostedPageStatus = data_get($hostedPage, 'status')
                ?? data_get($hostedPage, 'hostedpage_status')
                ?? data_get($hostedPageResponse, 'status')
                ?? null;

            $subscriptionBlock = data_get($hostedPage, 'subscription')
                ?? data_get($hostedPage, 'subscriptions.0')
                ?? [];

            $subscriptionId = data_get($subscriptionBlock, 'subscription_id')
                ?? data_get($hostedPage, 'subscription_id')
                ?? null;

            $planCode = data_get($subscriptionBlock, 'plan.plan_code')
                ?? data_get($subscriptionBlock, 'plan_code')
                ?? data_get($hostedPage, 'plan_code')
                ?? $payment?->zoho_plan_code;

            $invoiceId = data_get($hostedPage, 'invoice.invoice_id')
                ?? data_get($hostedPage, 'invoice_id')
                ?? null;

            $termStart = data_get($subscriptionBlock, 'current_term_starts_at')
                ?? data_get($subscriptionBlock, 'created_time')
                ?? now()->toDateTimeString();

            $termEnd = data_get($subscriptionBlock, 'current_term_ends_at')
                ?? data_get($subscriptionBlock, 'expires_at')
                ?? null;

            Log::info('Zoho checkout status parsed', [
                'hostedpage_id' => $hostedpage_id,
                'user_id' => $user->id,
                'hostedpage_status' => $hostedPageStatus,
                'subscription_id' => $subscriptionId,
                'plan_code' => $planCode,
                'term_start' => $termStart,
                'term_end' => $termEnd,
            ]);

            if (! $subscriptionId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment pending finalization',
                    'data' => [
                        'hostedpage_id' => $hostedpage_id,
                        'hostedpage_status' => $hostedPageStatus,
                        'has_subscription' => false,
                    ],
                ]);
            }

            $normalizedStatus = strtolower((string) $hostedPageStatus);
            $isCompleted = in_array($normalizedStatus, ['paid', 'success', 'completed', 'active', 'payment_success'], true);

            if (! $isCompleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment pending finalization',
                    'data' => [
                        'hostedpage_id' => $hostedpage_id,
                        'hostedpage_status' => $hostedPageStatus,
                        'has_subscription' => true,
                    ],
                ]);
            }

            if (! $termEnd) {
                $termEnd = (string) (strtolower((string) $planCode) === '01'
                    ? now()->copy()->addYear()->toDateTimeString()
                    : now()->copy()->addYear()->toDateTimeString());
            }

            $freshUser = DB::transaction(function () use ($user, $payment, $subscriptionBlock, $subscriptionId, $planCode, $termStart, $termEnd, $invoiceId) {
                $syncedUser = $this->membershipSyncService->syncUserMembershipFromZoho($user, [
                    'subscription' => array_merge($subscriptionBlock, [
                        'subscription_id' => $subscriptionId,
                        'plan_code' => $planCode,
                        'current_term_starts_at' => $termStart,
                        'current_term_ends_at' => $termEnd,
                    ]),
                    'invoice' => ['invoice_id' => $invoiceId],
                ]);

                if ($payment) {
                    $payment->forceFill([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'zoho_plan_code' => $planCode,
                    ])->save();

                    $this->syncUserMembershipRow($syncedUser, $payment, $termStart, $termEnd);
                }

                return $syncedUser;
            });

            return response()->json([
                'success' => true,
                'message' => 'Membership synced successfully.',
                'data' => [
                    'membership_status' => $freshUser?->membership_status ?? $freshUser?->membership_type ?? $freshUser?->membership ?? 'active',
                    'membership_starts_at' => $freshUser?->membership_starts_at,
                    'membership_ends_at' => $freshUser?->membership_ends_at,
                    'zoho_subscription_id' => $freshUser?->zoho_subscription_id,
                    'zoho_last_invoice_id' => $freshUser?->zoho_last_invoice_id,
                    'zoho_plan_code' => $freshUser?->zoho_plan_code,
                    'hostedpage_status' => $hostedPageStatus,
                ],
            ]);
        } catch (Throwable $throwable) {
            Log::error('Zoho checkout status sync failed', [
                'hostedpage_id' => $hostedpage_id,
                'message' => $throwable->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $throwable->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    private function recordPendingZohoPayment(User $user, string $planCode, string $hostedpageId): void
    {
        $payment = Payment::query()
            ->where('provider', 'zoho')
            ->where('zoho_hostedpage_id', $hostedpageId)
            ->first();

        if (! $payment) {
            $payment = new Payment();
            $payment->id = (string) Str::uuid();
        }

        $payment->forceFill([
            'user_id' => $user->id,
            'provider' => 'zoho',
            'zoho_plan_code' => $planCode,
            'zoho_hostedpage_id' => $hostedpageId,
            'status' => 'pending',
        ]);

        $payment->save();
    }

    private function syncUserMembershipRow(User $user, Payment $payment, mixed $startsAt, mixed $endsAt): void
    {
        if (! Schema::hasTable('user_memberships')) {
            return;
        }

        $existing = UserMembership::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        if ($existing) {
            $existing->forceFill([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'active',
                'payment_id' => $payment->id,
            ])->save();

            return;
        }

        try {
            UserMembership::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'membership_plan_id' => null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'active',
                'payment_id' => $payment->id,
            ]);
        } catch (Throwable $throwable) {
            Log::warning('Unable to create user_memberships row during Zoho sync', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}

/*
| Postman Smoke Steps
| 1) GET /api/v1/zoho/plans
| 2) POST /api/v1/billing/checkout {"plan_code":"01"}
| 3) Open checkout_url and complete payment
| 4) GET /api/v1/billing/checkout/{hostedpage_id}/status to finalize update
| 5) Webhook can also update automatically.
*/

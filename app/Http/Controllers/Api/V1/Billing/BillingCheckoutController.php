<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Membership\MembershipUpdater;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class BillingCheckoutController extends Controller
{
    public function __construct(
        private readonly ZohoBillingService $zohoBillingService,
        private readonly MembershipUpdater $membershipUpdater,
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
        /** @var User $user */
        $user = $request->user();

        try {
            $hostedPage = $this->zohoBillingService->getHostedPage($hostedpage_id);
            $parsed = $this->zohoBillingService->parseHostedPageForMembership($hostedPage);

            if (! $parsed['is_paid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is not completed yet.',
                    'data' => [
                        'hostedpage_status' => $parsed['status'],
                        'hosted_page' => $hostedPage,
                    ],
                ]);
            }

            DB::transaction(function () use ($user, $parsed): void {
                $this->membershipUpdater->applyPaidMembership($user, [
                    'zoho_subscription_id' => $parsed['subscription_id'],
                    'zoho_plan_code' => $parsed['plan_code'],
                    'zoho_last_invoice_id' => $parsed['invoice_id'],
                    'membership_starts_at' => $parsed['starts_at'],
                    'membership_ends_at' => $parsed['ends_at'],
                    'last_payment_at' => now(),
                ]);
            });

            $freshUser = $user->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Membership synced successfully.',
                'data' => [
                    'membership_status' => $freshUser?->membership_status ?? $freshUser?->membership_type ?? $freshUser?->membership ?? null,
                    'membership_starts_at' => $freshUser?->membership_starts_at,
                    'membership_ends_at' => $freshUser?->membership_ends_at,
                    'zoho_subscription_id' => $freshUser?->zoho_subscription_id,
                    'zoho_last_invoice_id' => $freshUser?->zoho_last_invoice_id,
                    'hostedpage_status' => $parsed['status'],
                ],
            ]);
        } catch (Throwable $throwable) {
            Log::error('Zoho checkout status sync failed', [
                'user_id' => $user->id,
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
}

/*
| Postman Smoke Steps
| 1) GET /api/v1/zoho/plans
| 2) POST /api/v1/billing/checkout {"plan_code":"01"}
| 3) Open checkout_url and complete payment
| 4) GET /api/v1/billing/checkout/{hostedpage_id}/status to finalize update
| 5) Webhook can also update automatically.
*/

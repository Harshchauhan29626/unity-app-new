<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CircleJoinPayment;
use App\Models\CircleMember;
use App\Models\User;
use App\Services\Zoho\ZohoCircleAddonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ZohoCircleJoinWebhookController extends Controller
{
    public function __construct(private readonly ZohoCircleAddonService $zohoCircleAddonService)
    {
    }

    public function handle(Request $request)
    {
        $requestId = (string) ($request->header('X-Request-Id') ?: Str::uuid());

        if (! $this->zohoCircleAddonService->verifyWebhookSignature($request)) {
            Log::warning('Circle webhook unauthorized', ['request_id' => $requestId]);

            return response()->json(['status' => true, 'message' => 'ok', 'data' => null]);
        }

        $payload = $request->all();
        $hostedPageId = (string) data_get($payload, 'hostedpage.hostedpage_id', data_get($payload, 'hostedpage_id', ''));
        $invoiceId = (string) data_get($payload, 'invoice.invoice_id', data_get($payload, 'invoice_id', ''));
        $paymentId = (string) data_get($payload, 'payment.payment_id', data_get($payload, 'payment_id', ''));

        if ($hostedPageId === '' && $invoiceId === '' && $paymentId === '') {
            Log::warning('Circle webhook missing identifiers', ['request_id' => $requestId]);

            return response()->json(['status' => true, 'message' => 'ok', 'data' => null]);
        }

        DB::transaction(function () use ($hostedPageId, $invoiceId, $paymentId, $payload, $requestId): void {
            $payment = CircleJoinPayment::query()
                ->when($hostedPageId !== '', fn ($q) => $q->orWhere('zoho_hostedpage_id', $hostedPageId))
                ->when($invoiceId !== '', fn ($q) => $q->orWhere('zoho_invoice_id', $invoiceId))
                ->when($paymentId !== '', fn ($q) => $q->orWhere('zoho_payment_id', $paymentId))
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('Circle join payment not found from webhook', [
                    'request_id' => $requestId,
                    'hostedpage_id' => $hostedPageId,
                    'invoice_id' => $invoiceId,
                    'payment_id' => $paymentId,
                ]);

                return;
            }

            if ($payment->status === 'paid') {
                return;
            }

            $status = strtolower((string) (data_get($payload, 'status')
                ?? data_get($payload, 'event_type')
                ?? data_get($payload, 'hostedpage.payment_status')
                ?? ''));

            $isSuccess = str_contains($status, 'success') || in_array($status, ['paid', 'completed', 'active'], true);

            $payment->forceFill([
                'status' => $isSuccess ? 'paid' : (str_contains($status, 'cancel') ? 'cancelled' : 'failed'),
                'paid_at' => $isSuccess ? now() : $payment->paid_at,
                'failed_at' => $isSuccess ? null : now(),
                'zoho_invoice_id' => $invoiceId ?: $payment->zoho_invoice_id,
                'zoho_payment_id' => $paymentId ?: $payment->zoho_payment_id,
                'zoho_subscription_id' => (string) (data_get($payload, 'subscription.subscription_id')
                    ?? data_get($payload, 'subscription_id')
                    ?? $payment->zoho_subscription_id),
                'raw_payload' => $payload,
            ])->save();

            if (! $isSuccess) {
                return;
            }

            $member = CircleMember::query()
                ->where('circle_id', $payment->circle_id)
                ->where('user_id', $payment->user_id)
                ->first();

            $meta = [
                'interval_key' => $payment->circleFee?->interval_key,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'zoho_hostedpage_id' => $payment->zoho_hostedpage_id,
                'zoho_invoice_id' => $payment->zoho_invoice_id,
                'zoho_payment_id' => $payment->zoho_payment_id,
            ];

            if (! $member) {
                $member = CircleMember::query()->create([
                    'id' => (string) Str::uuid(),
                    'circle_id' => $payment->circle_id,
                    'user_id' => $payment->user_id,
                    'role' => 'member',
                    'status' => 'active',
                    'joined_via' => 'payment',
                    'joined_at' => now(),
                    'paid_at' => now(),
                    'payment_id' => $payment->id,
                    'meta' => $meta,
                ]);
            } else {
                $member->forceFill([
                    'status' => 'active',
                    'joined_via' => 'payment',
                    'joined_at' => $member->joined_at ?: now(),
                    'paid_at' => now(),
                    'payment_id' => $payment->id,
                    'meta' => $meta,
                ])->save();
            }

            $activeCount = CircleMember::query()
                ->where('user_id', $payment->user_id)
                ->where('status', 'active')
                ->count();

            $membershipStatus = $activeCount >= 2
                ? 'multi_circle_member'
                : ($activeCount === 1 ? 'circle_member' : 'only_unity_peer');

            User::query()->where('id', $payment->user_id)->update([
                'membership_status' => $membershipStatus,
                'updated_at' => now(),
            ]);
        });

        return response()->json(['status' => true, 'message' => 'ok', 'data' => null]);
    }
}

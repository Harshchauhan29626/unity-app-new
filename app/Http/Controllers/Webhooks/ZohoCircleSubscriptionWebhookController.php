<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ZohoCircleSubscriptionWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $providedToken = (string) ($request->header('X-Zoho-Webhook-Token')
            ?: $request->input('token')
            ?: $request->header('Authorization'));

        if (str_starts_with(strtolower($providedToken), 'bearer ')) {
            $providedToken = trim(substr($providedToken, 7));
        }

        if ($providedToken !== (string) env('ZOHO_WEBHOOK_TOKEN')) {
            return response()->json(['message' => 'Unauthorized webhook token.'], 401);
        }

        $payload = $request->all();
        $eventType = strtolower((string) data_get($payload, 'event_type', data_get($payload, 'event')));
        $paymentStatus = strtolower((string) data_get($payload, 'payment.status', data_get($payload, 'payment_status')));

        $isPaymentSuccess = str_contains($eventType, 'payment')
            ? in_array($paymentStatus, ['success', 'paid', 'completed'], true)
            : str_contains($eventType, 'success') || $paymentStatus === 'success';

        if (! $isPaymentSuccess) {
            return response()->json(['message' => 'Webhook ignored.']);
        }

        $user = $this->resolveUser($payload);

        if (! $user) {
            Log::error('zoho api error', [
                'context' => 'circle_webhook_user_not_found',
                'payload' => $payload,
            ]);

            return response()->json(['message' => 'User not found.'], 404);
        }

        $circleId = (string) data_get($payload, 'circle_id', data_get($payload, 'subscription.custom_fields.circle_id'));
        $start = data_get($payload, 'subscription_start', data_get($payload, 'subscription.start_date', now()->toDateTimeString()));
        $expiry = data_get($payload, 'subscription_expiry', data_get($payload, 'subscription.next_billing_at'));

        $updates = ['membership_status' => 'circle_peer'];

        if ($circleId !== '' && Schema::hasColumn('users', 'circle_id')) {
            $updates['circle_id'] = $circleId;
        }

        if (Schema::hasColumn('users', 'subscription_start')) {
            $updates['subscription_start'] = $start;
        }

        if (Schema::hasColumn('users', 'subscription_expiry')) {
            $updates['subscription_expiry'] = $expiry;
        }

        if (Schema::hasColumn('users', 'membership_starts_at')) {
            $updates['membership_starts_at'] = $start;
        }

        if (Schema::hasColumn('users', 'membership_ends_at')) {
            $updates['membership_ends_at'] = $expiry;
        }

        $user->forceFill($updates);
        $user->save();

        return response()->json(['message' => 'Webhook processed.']);
    }

    private function resolveUser(array $payload): ?User
    {
        $userId = (string) data_get($payload, 'user_id');
        if ($userId !== '') {
            $user = User::query()->find($userId);
            if ($user) {
                return $user;
            }
        }

        $email = (string) data_get($payload, 'customer.email', data_get($payload, 'email'));
        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        $customerId = (string) data_get($payload, 'customer.customer_id', data_get($payload, 'customer_id'));
        if ($customerId !== '') {
            return User::query()->where('zoho_customer_id', $customerId)->first();
        }

        return null;
    }
}

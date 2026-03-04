<?php

namespace App\Services\Zoho;

use App\Models\Circle;
use App\Models\CircleFee;
use App\Models\CircleJoinPayment;
use App\Models\Payment;
use App\Models\User;
use App\Support\Zoho\ZohoBillingClient;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ZohoCircleAddonService
{
    public function __construct(
        private readonly ZohoBillingClient $client,
        private readonly ZohoBillingService $zohoBillingService,
    ) {
    }

    public function syncCircleFeesToZoho(Circle $circle): void
    {
        $circle->loadMissing('fees');

        foreach ($circle->fees->where('is_active', true) as $fee) {
            $this->ensureAddonForFee($fee);
        }
    }

    public function ensureAddonForFee(CircleFee $fee): CircleFee
    {
        $requestId = (string) Str::uuid();
        $addonPayload = [
            'name' => $this->buildAddonName($fee),
            'addon_code' => $this->buildAddonCode($fee),
            'description' => sprintf('Circle membership fee for %s (%s)', $fee->circle?->name ?? 'circle', $fee->interval_key),
            'pricing_scheme' => 'per_unit',
            'price' => (float) $fee->amount,
            'currency_code' => strtoupper($fee->currency ?: 'INR'),
            'type' => 'recurring',
            'is_taxable' => false,
        ];

        $configuredProductId = (string) config('zoho_circle.product_id', '');
        if ($configuredProductId !== '') {
            $addonPayload['product_id'] = $configuredProductId;
        }

        Log::info('Syncing circle addon fee to Zoho', [
            'request_id' => $requestId,
            'circle_fee_id' => $fee->id,
            'zoho_addon_id' => $fee->zoho_addon_id,
            'payload_keys' => array_keys($addonPayload),
        ]);

        $response = $fee->zoho_addon_id
            ? $this->client->request('PUT', '/addons/' . $fee->zoho_addon_id, $addonPayload)
            : $this->client->request('POST', '/addons', $addonPayload);

        $addon = data_get($response, 'addon', []);

        $fee->forceFill([
            'zoho_addon_id' => data_get($addon, 'addon_id', $fee->zoho_addon_id),
            'zoho_addon_code' => data_get($addon, 'addon_code', $fee->zoho_addon_code),
            'zoho_item_id' => data_get($addon, 'item_id', $fee->zoho_item_id),
            'zoho_product_id' => data_get($addon, 'product_id', $fee->zoho_product_id),
            'zoho_price_id' => data_get($addon, 'price_id', $fee->zoho_price_id),
        ])->save();

        return $fee->refresh();
    }

    public function createHostedPageForCircleJoin(User $user, CircleJoinPayment $payment, CircleFee $fee): array
    {
        $requestId = (string) Str::uuid();
        $subscriptionId = $this->resolveZohoSubscriptionId($user);

        if ($subscriptionId === '') {
            throw new RuntimeException('No active Zoho subscription found for this user. Please purchase Unity Peer membership first.');
        }

        if (! $fee->zoho_addon_id) {
            $fee = $this->ensureAddonForFee($fee);
        }

        $customerId = $this->zohoBillingService->ensureCustomerForUser($user);

        $payload = [
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
            'addons' => [[
                'addon_id' => $fee->zoho_addon_id,
                'quantity' => 1,
            ]],
            'redirect_url' => config('zoho_circle.hostedpage_redirect_url_success'),
            'cancel_url' => config('zoho_circle.hostedpage_redirect_url_cancel'),
        ];

        Log::info('Creating circle join hostedpage', [
            'request_id' => $requestId,
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'circle_fee_id' => $fee->id,
            'subscription_id' => $subscriptionId,
        ]);

        $response = $this->client->request('POST', '/hostedpages/updatesubscription', $payload);

        $hostedpageId = (string) data_get($response, 'hostedpage.hostedpage_id', '');
        $hostedpageUrl = (string) data_get($response, 'hostedpage.url', '');

        if ($hostedpageId === '' || $hostedpageUrl === '') {
            throw new RuntimeException('Unable to create Zoho hosted page for circle join checkout.');
        }

        $payment->forceFill([
            'zoho_subscription_id' => $subscriptionId,
            'zoho_addon_id' => $fee->zoho_addon_id,
            'zoho_hostedpage_id' => $hostedpageId,
            'zoho_hostedpage_url' => $hostedpageUrl,
            'raw_payload' => [
                'request' => [
                    'payload' => $payload,
                    'request_id' => $requestId,
                ],
                'response' => $response,
            ],
        ])->save();

        return [
            'payment_id' => $payment->id,
            'checkout_url' => $hostedpageUrl,
            'hostedpage_id' => $hostedpageId,
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('zoho_circle.webhook_secret', '');
        $headerToken = (string) $request->header('X-Zoho-Circle-Token', '');

        if ($secret !== '' && hash_equals($secret, $headerToken)) {
            return true;
        }

        $shared = (string) config('zoho_circle.webhook_token', '');

        return $shared !== '' && hash_equals($shared, (string) $request->header('X-Webhook-Token', ''));
    }

    private function resolveZohoSubscriptionId(User $user): string
    {
        $fromUser = (string) ($user->getAttribute('zoho_subscription_id') ?? '');
        if ($fromUser !== '') {
            return $fromUser;
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'zoho_subscription_id')) {
            $fromPayment = (string) Payment::query()
                ->where('user_id', $user->id)
                ->whereNotNull('zoho_subscription_id')
                ->latest('created_at')
                ->value('zoho_subscription_id');

            if ($fromPayment !== '') {
                return $fromPayment;
            }
        }

        return '';
    }

    private function buildAddonCode(CircleFee $fee): string
    {
        $prefix = strtoupper((string) config('zoho_circle.circle_addon_prefix', 'CIRCLE_'));

        return $prefix . strtoupper($fee->circle_id . '_' . $fee->interval_key);
    }

    private function buildAddonName(CircleFee $fee): string
    {
        return trim(($fee->circle?->name ?? 'Circle') . ' - ' . str_replace('_', ' ', $fee->interval_key));
    }
}

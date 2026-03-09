<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\CircleSubscription;
use App\Models\User;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CircleSubscriptionController extends BaseApiController
{
    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function package(Circle $circle)
    {
        $addonCode = trim((string) ($circle->zoho_addon_code ?? ''));
        $amount = $circle->circle_price_amount;
        $currency = $circle->circle_price_currency;

        if ($addonCode !== '' && ($amount === null || $currency === null || $currency === '')) {
            $addon = $this->zohoBillingService->findCirclePackageAddonByCodeOrId($addonCode, false);

            if (is_array($addon)) {
                $amount = $amount
                    ?? data_get($addon, 'raw.price_brackets.0.price')
                    ?? data_get($addon, 'price_brackets.0.price')
                    ?? ($addon['price'] ?? null)
                    ?? ($addon['amount'] ?? null)
                    ?? ($addon['rate'] ?? null);

                $currency = $currency
                    ?: ($circle->circle_price_currency
                        ?: ($addon['currency_code'] ?? null)
                        ?: ($addon['currency'] ?? null)
                        ?: data_get($addon, 'raw.currency_code')
                        ?: data_get($addon, 'raw.currency')
                        ?: 'INR');

                Log::info('circle package response addon fallback applied', [
                    'circle_id' => $circle->id,
                    'addon_code' => $addonCode,
                    'addon_payload' => $addon['raw'] ?? $addon,
                    'resolved_amount' => $amount,
                    'resolved_currency' => $currency,
                ]);
            }
        }

        $currency = $currency ?: 'INR';

        return $this->success([
            'circle_id' => $circle->id,
            'circle_name' => $circle->name,
            'addon_code' => $circle->zoho_addon_code,
            'addon_name' => $circle->zoho_addon_name,
            'amount' => $amount,
            'currency' => $currency,
            'duration_months' => (int) ($circle->circle_duration_months ?: 12),
            'joinable' => $addonCode !== '',
        ]);
    }

    public function checkout(Request $request, Circle $circle)
    {
        /** @var User $user */
        $user = $request->user();

        if (trim((string) ($circle->zoho_addon_code ?? '')) === '') {
            return $this->error('This circle does not have a package configured yet.', 422);
        }

        $existing = CircleSubscription::query()
            ->where('user_id', $user->id)
            ->where('circle_id', $circle->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('created_at')
            ->first();

        if ($existing) {
            return $this->error('You already have an active subscription for this circle.', 422);
        }

        try {
            Log::info('circle subscription checkout started', [
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'addon_code' => $circle->zoho_addon_code,
            ]);

            $checkout = $this->zohoBillingService->createHostedPageForCircleAddon($user, $circle);

            $subscription = CircleSubscription::query()->create([
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'zoho_customer_id' => $checkout['customer_id'] ?? $user->zoho_customer_id,
                'zoho_subscription_id' => $checkout['subscription_id'] ?? $user->zoho_subscription_id,
                'zoho_hosted_page_id' => $checkout['hostedpage_id'] ?? null,
                'zoho_addon_id' => $circle->zoho_addon_id,
                'zoho_addon_code' => $circle->zoho_addon_code,
                'zoho_addon_name' => $circle->zoho_addon_name,
                'amount' => $circle->circle_price_amount,
                'currency_code' => $circle->circle_price_currency,
                'status' => 'pending',
                'raw_checkout_response' => $checkout['raw'] ?? null,
            ]);

            Log::info('circle subscription checkout created', [
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'circle_subscription_id' => $subscription->id,
                'hostedpage_id' => $subscription->zoho_hosted_page_id,
            ]);

            return $this->success([
                'circle_subscription_id' => $subscription->id,
                'checkout_url' => $checkout['checkout_url'] ?? null,
                'hostedpage_id' => $checkout['hostedpage_id'] ?? null,
            ], 'Circle checkout URL created successfully.');
        } catch (ValidationException $validationException) {
            return $this->error(
                collect($validationException->errors())->flatten()->first() ?? 'Validation failed',
                422,
                $validationException->errors(),
            );
        } catch (Throwable $throwable) {
            Log::error('circle subscription checkout failed', [
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'message' => $throwable->getMessage(),
            ]);

            return $this->error('Failed to generate circle checkout URL.', 500);
        }
    }
}

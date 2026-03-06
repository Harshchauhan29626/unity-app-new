<?php

namespace App\Services\Zoho;

use App\Models\Circle;
use App\Models\ZohoCircleAddon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ZohoCircleAddonService
{
    private const INTERVALS = [
        'monthly' => ['code' => 'm', 'months' => 1, 'column' => 'price_monthly'],
        'quarterly' => ['code' => 'q', 'months' => 3, 'column' => 'price_quarterly'],
        'half_yearly' => ['code' => 'h', 'months' => 6, 'column' => 'price_half_yearly'],
        'yearly' => ['code' => 'y', 'months' => 12, 'column' => 'price_yearly'],
    ];

    public function __construct(private readonly ZohoTokenService $tokenService)
    {
    }

    public function syncCircleAddons(Circle $circle): void
    {
        foreach (self::INTERVALS as $intervalType => $meta) {
            $price = $circle->{$meta['column']} ?? null;

            if ($price === null || $price === '') {
                continue;
            }

            try {
                $this->syncIntervalAddon($circle, $intervalType, $meta, (float) $price);
            } catch (\Throwable $exception) {
                Log::error('zoho api error', [
                    'context' => 'sync_circle_addon',
                    'circle_id' => $circle->id,
                    'interval_type' => $intervalType,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function syncIntervalAddon(Circle $circle, string $intervalType, array $meta, float $price): void
    {
        $addonCode = $this->buildAddonCode($circle, $meta['code']);

        $existingAddon = ZohoCircleAddon::query()
            ->where('circle_id', $circle->id)
            ->where('interval_type', $intervalType)
            ->first();

        if ($existingAddon) {
            $response = $this->updateAddon($addonCode, $price, $meta['months']);

            $existingAddon->fill([
                'price' => $price,
                'zoho_addon_id' => (string) data_get($response, 'addon.addon_id', $existingAddon->zoho_addon_id),
                'zoho_addon_code' => (string) data_get($response, 'addon.addon_code', $addonCode),
                'product_id' => (string) data_get($response, 'addon.product_id', env('ZOHO_CIRCLE_ADDON_PRODUCT_ID')),
            ]);
            $existingAddon->save();

            Log::info('circle addon updated', [
                'circle_id' => $circle->id,
                'interval_type' => $intervalType,
                'addon_code' => $addonCode,
                'price' => $price,
            ]);

            return;
        }

        $response = $this->createAddon($circle, $addonCode, $price, $intervalType, $meta['months']);

        ZohoCircleAddon::query()->create([
            'circle_id' => $circle->id,
            'interval_type' => $intervalType,
            'price' => $price,
            'zoho_addon_id' => (string) data_get($response, 'addon.addon_id'),
            'zoho_addon_code' => (string) data_get($response, 'addon.addon_code', $addonCode),
            'product_id' => (string) data_get($response, 'addon.product_id', env('ZOHO_CIRCLE_ADDON_PRODUCT_ID')),
        ]);

        Log::info('circle addon created', [
            'circle_id' => $circle->id,
            'interval_type' => $intervalType,
            'addon_code' => $addonCode,
            'price' => $price,
        ]);
    }

    private function createAddon(Circle $circle, string $addonCode, float $price, string $intervalType, int $months): array
    {
        return $this->request('POST', '/addons', [
            'name' => sprintf('%s %s', $circle->name, Str::title(str_replace('_', ' ', $intervalType))),
            'addon_code' => $addonCode,
            'product_id' => env('ZOHO_CIRCLE_ADDON_PRODUCT_ID'),
            'type' => 'recurring',
            'pricing_model' => 'per_unit',
            'price' => $price,
            'interval_unit' => 'months',
            'interval' => $months,
        ]);
    }

    private function updateAddon(string $addonCode, float $price, int $months): array
    {
        return $this->request('PUT', '/addons/' . $addonCode, [
            'price' => $price,
            'type' => 'recurring',
            'pricing_model' => 'per_unit',
            'interval_unit' => 'months',
            'interval' => $months,
            'product_id' => env('ZOHO_CIRCLE_ADDON_PRODUCT_ID'),
        ]);
    }

    private function request(string $method, string $path, array $payload): array
    {
        $baseUrl = rtrim((string) env('ZOHO_BILLING_BASE_URL'), '/');
        $url = $baseUrl . '/' . ltrim($path, '/');

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => 'Zoho-oauthtoken ' . $this->tokenService->getAccessToken(),
                    'X-com-zoho-subscriptions-organizationid' => (string) env('ZOHO_BILLING_ORG_ID'),
                ])
                ->send(strtoupper($method), $url, ['json' => $payload])
                ->throw();

            return $response->json() ?? [];
        } catch (RequestException $exception) {
            Log::error('zoho api error', [
                'context' => 'circle_addon_request',
                'method' => strtoupper($method),
                'url' => $url,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json() ?? $exception->response?->body(),
                'payload' => $payload,
            ]);

            throw $exception;
        }
    }

    private function buildAddonCode(Circle $circle, string $intervalCode): string
    {
        $slug = $circle->slug ?: Str::slug((string) $circle->name, '_');
        $slug = str_replace('-', '_', (string) $slug);

        return 'circle_' . $slug . '_' . $intervalCode;
    }
}

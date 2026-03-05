<?php

namespace App\Services\Zoho;

use App\Models\Circle;
use App\Models\CircleSubscriptionPrice;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ZohoCircleAddonSyncService
{
    private const DURATION_LABELS = [
        1 => 'Monthly',
        3 => 'Quarterly',
        6 => 'Half-Yearly',
        12 => 'Yearly',
    ];

    private const MAX_CREATE_ATTEMPTS = 5;
    private const MAX_DB_CODE_SCAN_ATTEMPTS = 500;

    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function syncCircleAddons(Circle $circle): void
    {
        $query = $circle->subscriptionPrices()->whereIn('duration_months', [1, 3, 6, 12]);

        if (Schema::hasColumn('circle_subscription_prices', 'is_active')) {
            $query->where('is_active', true);
        }

        $prices = $query->orderBy('duration_months')->get();
        $zohoCodes = $this->fetchZohoAddonCodes();

        foreach ($prices as $price) {
            if ((float) $price->price <= 0) {
                continue;
            }

            $duration = (int) $price->duration_months;
            $name = sprintf('%s - %s', $circle->name, self::DURATION_LABELS[$duration] ?? ($duration . ' Months'));
            $intervalUnit = 'monthly';
            $interval = $duration;

            if ($price->zoho_addon_id) {
                $this->updateAddon($price, $name, $intervalUnit, $interval);

                continue;
            }

            $this->createAddon($circle, $price, $name, $intervalUnit, $interval, $zohoCodes);
        }
    }

    private function createAddon(Circle $circle, CircleSubscriptionPrice $price, string $name, string $intervalUnit, int $interval, array &$zohoCodes): void
    {
        $productId = $this->resolveCircleProductId();

        for ($attempt = 1; $attempt <= self::MAX_CREATE_ATTEMPTS; $attempt++) {
            $addonCode = $this->reserveNextAddonCode($zohoCodes, $price->id);

            $payload = [
                'product_id' => $productId,
                'addon_code' => $addonCode,
                'name' => $name,
                'unit_name' => 'Unit',
                'pricing_scheme' => 'unit',
                'price_brackets' => [[
                    'price' => (float) $price->price,
                    'currency_code' => (string) ($price->currency ?: 'INR'),
                ]],
                'type' => 'recurring',
                'interval_unit' => $intervalUnit,
                'interval' => $interval,
            ];

            Log::info('Zoho addon create attempt', [
                'circle_id' => $circle->id,
                'duration' => $price->duration_months,
                'code' => $addonCode,
                'attempt' => $attempt,
                'payload' => $payload,
            ]);

            try {
                $response = $this->zohoBillingService->createAddon($payload);
                $addon = data_get($response, 'addon', []);

                DB::transaction(function () use ($price, $addon, $name, $addonCode, $response): void {
                    $updates = [
                        'zoho_addon_id' => (string) (data_get($addon, 'addon_id') ?? data_get($addon, 'id') ?? ''),
                        'zoho_addon_code' => (string) (data_get($addon, 'addon_code') ?? data_get($addon, 'code') ?? $addonCode),
                        'zoho_addon_name' => (string) (data_get($addon, 'name') ?? $name),
                    ];

                    if (Schema::hasColumn('circle_subscription_prices', 'zoho_addon_interval_unit')) {
                        $updates['zoho_addon_interval_unit'] = (string) (data_get($addon, 'interval_unit') ?? data_get($addon, 'interval.unit') ?? null);
                    }

                    if (Schema::hasColumn('circle_subscription_prices', 'payload')) {
                        $updates['payload'] = $response;
                    } else {
                        Log::warning('circle_subscription_prices.payload column missing; skipping payload save.');
                    }

                    $price->forceFill($updates)->save();
                });

                $zohoCodes[$addonCode] = true;

                Log::info('Zoho addon create success', [
                    'addon_id' => (string) (data_get($addon, 'addon_id') ?? data_get($addon, 'id') ?? ''),
                    'code' => $addonCode,
                    'circle_id' => $circle->id,
                    'duration' => $price->duration_months,
                ]);

                return;
            } catch (Throwable $throwable) {
                Log::error('Zoho addon create failed', [
                    'circle_id' => $circle->id,
                    'duration' => $price->duration_months,
                    'code' => $addonCode,
                    'message' => $throwable->getMessage(),
                ]);

                if (! $this->isCodeError($throwable) || $attempt === self::MAX_CREATE_ATTEMPTS) {
                    throw $throwable;
                }

                usleep(200000);
            }
        }
    }

    private function updateAddon(CircleSubscriptionPrice $price, string $name, string $intervalUnit, int $interval): void
    {
        $target = (string) ($price->zoho_addon_code ?: $price->zoho_addon_id);

        if ($target === '') {
            return;
        }

        $payload = [
            'name' => $name,
            'unit_name' => 'Unit',
            'pricing_scheme' => 'unit',
            'price_brackets' => [[
                'price' => (float) $price->price,
                'currency_code' => (string) ($price->currency ?: 'INR'),
            ]],
            'type' => 'recurring',
            'interval_unit' => $intervalUnit,
            'interval' => $interval,
        ];

        $response = $this->zohoBillingService->updateAddon($target, $payload);
        $addon = data_get($response, 'addon', []);

        $updates = [
            'zoho_addon_id' => (string) (data_get($addon, 'addon_id') ?? data_get($addon, 'id') ?? $price->zoho_addon_id),
            'zoho_addon_code' => (string) (data_get($addon, 'addon_code') ?? data_get($addon, 'code') ?? $price->zoho_addon_code),
            'zoho_addon_name' => (string) (data_get($addon, 'name') ?? $name),
        ];

        if (Schema::hasColumn('circle_subscription_prices', 'zoho_addon_interval_unit')) {
            $updates['zoho_addon_interval_unit'] = (string) (data_get($addon, 'interval_unit') ?? data_get($addon, 'interval.unit') ?? $price->zoho_addon_interval_unit);
        }

        if (Schema::hasColumn('circle_subscription_prices', 'payload')) {
            $updates['payload'] = $response;
        }

        $price->forceFill($updates)->save();
    }

    private function reserveNextAddonCode(array $zohoCodes, ?string $ignoreId = null): string
    {
        return DB::transaction(function () use ($zohoCodes, $ignoreId): string {
            $start = (int) config('zoho_billing.addon_code_start', 10);
            $width = max(1, (int) config('zoho_billing.addon_code_min_width', 2));

            if (! Schema::hasTable('zoho_addon_code_sequences')) {
                throw new RuntimeException('Missing table zoho_addon_code_sequences. Run required SQL/migration.');
            }

            $sequence = DB::table('zoho_addon_code_sequences')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                throw new RuntimeException('Missing zoho_addon_code_sequences seed row id=1. Run required SQL/migration.');
            }

            $candidate = max((int) ($sequence->next_code ?? $start), $start);

            for ($i = 0; $i < self::MAX_DB_CODE_SCAN_ATTEMPTS; $i++) {
                $formatted = $this->formatCode($candidate, $width);

                if (! isset($zohoCodes[$formatted]) && ! $this->codeExistsInDb($formatted, $ignoreId)) {
                    DB::table('zoho_addon_code_sequences')
                        ->where('id', 1)
                        ->update([
                            'next_code' => $candidate + 1,
                            'updated_at' => now(),
                        ]);

                    return $formatted;
                }

                $candidate++;
            }

            throw new RuntimeException('Unable to reserve unique addon code from sequence.');
        });
    }

    private function formatCode(int $value, int $width): string
    {
        $raw = (string) $value;

        if (strlen($raw) >= $width) {
            return $raw;
        }

        return str_pad($raw, $width, '0', STR_PAD_LEFT);
    }

    private function fetchZohoAddonCodes(): array
    {
        $codes = [];
        $page = 1;

        while (true) {
            $response = $this->zohoBillingService->listAddons($page, 200);
            $addons = data_get($response, 'addons', []);

            if (! is_array($addons) || $addons === []) {
                break;
            }

            foreach ($addons as $addon) {
                $code = (string) (data_get($addon, 'addon_code') ?? data_get($addon, 'code') ?? '');
                if ($code !== '') {
                    $codes[$code] = true;
                }
            }

            if (count($addons) < 200) {
                break;
            }

            $page++;
        }

        return $codes;
    }

    private function codeExistsInDb(string $code, ?string $ignoreId = null): bool
    {
        $query = CircleSubscriptionPrice::query()->where('zoho_addon_code', $code);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function resolveCircleProductId(): string
    {
        $productId = (string) (config('services.zoho.circle_addon_product_id')
            ?: config('zoho_billing.product_id')
            ?: '');

        if ($productId === '') {
            throw new RuntimeException('ZOHO circle addon product id is missing. Set ZOHO_CIRCLE_ADDON_PRODUCT_ID.');
        }

        return $productId;
    }

    private function isCodeError(Throwable $throwable): bool
    {
        return Str::contains(Str::lower($throwable->getMessage()), [
            'invalid value passed for code',
            'code already exists',
            'duplicate',
            'addon_code',
            'code',
        ]);
    }
}

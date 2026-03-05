<?php

namespace App\Services\Zoho;

use App\Models\Circle;
use App\Models\CircleSubscriptionPrice;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ZohoCircleAddonService
{
    private const DURATION_LABELS = [
        1 => 'Monthly',
        3 => 'Quarterly',
        6 => 'Half-Yearly',
        12 => 'Yearly',
    ];

    private const MAX_ZOHO_CREATE_ATTEMPTS = 5;
    private const MAX_DB_CODE_SCAN_ATTEMPTS = 500;

    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function ensureAddonsForCircle(Circle $circle): void
    {
        $prices = $circle->subscriptionPrices()
            ->whereIn('duration_months', array_keys(self::DURATION_LABELS))
            ->get()
            ->keyBy('duration_months');

        foreach (self::DURATION_LABELS as $duration => $label) {
            /** @var CircleSubscriptionPrice|null $price */
            $price = $prices->get($duration);

            if (! $price || (float) $price->price <= 0) {
                continue;
            }

            $addonName = sprintf('%s - %s', $circle->name, $label);

            if ($price->zoho_addon_id) {
                $this->syncExistingAddon($circle, $price, $addonName);
            } else {
                $this->createAddonWithRetry($circle, $price, $addonName);
            }
        }
    }

    public static function durationLabel(int $durationMonths): string
    {
        return self::DURATION_LABELS[$durationMonths] ?? ($durationMonths . ' Months');
    }

    public function generateUniqueAddonCode(Circle $circle, int $durationMonths): string
    {
        $dbCodes = CircleSubscriptionPrice::query()
            ->whereNotNull('zoho_addon_code')
            ->pluck('zoho_addon_code')
            ->filter(fn ($code) => is_string($code) && preg_match('/^[0-9]+$/', $code))
            ->map(fn ($code) => (int) $code)
            ->values();

        $zohoCodes = collect($this->fetchZohoAddonCodes())
            ->filter(fn ($code) => preg_match('/^[0-9]+$/', (string) $code))
            ->map(fn ($code) => (int) $code)
            ->values();

        $used = $dbCodes->merge($zohoCodes)->unique()->values();
        $usedSet = array_flip($used->map(fn ($n) => (string) $n)->all());

        $next = max(10, ((int) ($used->max() ?? 0)) + 1);

        for ($i = 0; $i < self::MAX_DB_CODE_SCAN_ATTEMPTS; $i++) {
            if (! isset($usedSet[(string) $next])) {
                return (string) $next;
            }

            $next++;
        }

        throw new RuntimeException('Unable to generate unique numeric Zoho addon code within scan limit.');
    }

    private function createAddonWithRetry(Circle $circle, CircleSubscriptionPrice $price, string $addonName): void
    {
        $productId = $this->resolveCircleAddonProductId();
        $codeNumber = $this->resolveInitialCodeNumber($circle, $price);

        for ($attempt = 1; $attempt <= self::MAX_ZOHO_CREATE_ATTEMPTS; $attempt++) {
            $codeNumber = $this->nextFreeCodeNumber($codeNumber, $price->id);
            $addonCode = (string) $codeNumber;

            $payload = [
                'product_id' => $productId,
                'addon_code' => $addonCode,
                'name' => $addonName,
                'unit_name' => 'Unit',
                'pricing_scheme' => 'unit',
                'price_brackets' => [[
                    'price' => (float) $price->price,
                    'currency_code' => (string) ($price->currency ?: 'INR'),
                ]],
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

                DB::transaction(function () use ($price, $addon, $addonCode, $addonName, $response): void {
                    $price->forceFill([
                        'zoho_addon_id' => (string) (data_get($addon, 'addon_id') ?? data_get($addon, 'id') ?? ''),
                        'zoho_addon_code' => (string) (data_get($addon, 'addon_code') ?? data_get($addon, 'code') ?? $addonCode),
                        'zoho_addon_name' => (string) (data_get($addon, 'name') ?? $addonName),
                        'payload' => $response,
                    ])->save();
                });

                Log::info('Zoho addon create success', [
                    'circle_id' => $circle->id,
                    'duration' => $price->duration_months,
                    'addon_id' => $price->fresh()->zoho_addon_id,
                    'code' => $addonCode,
                ]);

                return;
            } catch (Throwable $throwable) {
                Log::error('Zoho addon create failed', [
                    'circle_id' => $circle->id,
                    'duration' => $price->duration_months,
                    'code' => $addonCode,
                    'message' => $throwable->getMessage(),
                ]);

                if (! $this->isCodeRelatedZohoError($throwable) || $attempt === self::MAX_ZOHO_CREATE_ATTEMPTS) {
                    throw $throwable;
                }

                $codeNumber++;
                usleep(200000);
            }
        }
    }

    private function syncExistingAddon(Circle $circle, CircleSubscriptionPrice $price, string $addonName): void
    {
        $target = (string) ($price->zoho_addon_code ?: $price->zoho_addon_id);
        if ($target === '') {
            $this->createAddonWithRetry($circle, $price, $addonName);

            return;
        }

        $payload = [
            'name' => $addonName,
            'unit_name' => 'Unit',
            'pricing_scheme' => 'unit',
            'price_brackets' => [[
                'price' => (float) $price->price,
                'currency_code' => (string) ($price->currency ?: 'INR'),
            ]],
        ];

        try {
            $response = $this->zohoBillingService->updateAddon($target, $payload);
            $addon = data_get($response, 'addon', []);

            $price->forceFill([
                'zoho_addon_id' => (string) (data_get($addon, 'addon_id') ?? data_get($addon, 'id') ?? $price->zoho_addon_id),
                'zoho_addon_code' => (string) (data_get($addon, 'addon_code') ?? data_get($addon, 'code') ?? $price->zoho_addon_code),
                'zoho_addon_name' => (string) (data_get($addon, 'name') ?? $addonName),
                'payload' => $response,
            ])->save();
        } catch (Throwable $throwable) {
            if (! $this->isCodeRelatedZohoError($throwable)) {
                throw $throwable;
            }

            Log::warning('Zoho circle addon update failed due to code issue, creating replacement addon', [
                'circle_id' => $circle->id,
                'duration' => $price->duration_months,
                'addon_id' => $price->zoho_addon_id,
                'message' => $throwable->getMessage(),
            ]);

            $price->forceFill([
                'zoho_addon_id' => null,
                'zoho_addon_code' => null,
            ])->save();

            $this->createAddonWithRetry($circle, $price, $addonName);
        }
    }

    private function resolveInitialCodeNumber(Circle $circle, CircleSubscriptionPrice $price): int
    {
        if ($this->isValidAddonCode((string) $price->zoho_addon_code)) {
            return (int) $price->zoho_addon_code;
        }

        return (int) $this->generateUniqueAddonCode($circle, (int) $price->duration_months);
    }

    private function nextFreeCodeNumber(int $codeNumber, ?string $ignoreId = null): int
    {
        for ($i = 0; $i < self::MAX_DB_CODE_SCAN_ATTEMPTS; $i++) {
            if (! $this->addonCodeExists((string) $codeNumber, $ignoreId)) {
                return $codeNumber;
            }

            $codeNumber++;
        }

        throw new RuntimeException('Unable to resolve free numeric Zoho addon code within scan limit.');
    }

    private function addonCodeExists(string $code, ?string $ignoreId = null): bool
    {
        $query = CircleSubscriptionPrice::query()
            ->where('zoho_addon_code', $code);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
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
                    $codes[] = $code;
                }
            }

            if (count($addons) < 200) {
                break;
            }

            $page++;
        }

        return array_values(array_unique($codes));
    }

    private function resolveCircleAddonProductId(): string
    {
        $productId = (string) (config('services.zoho.circle_addon_product_id')
            ?: config('services.zoho.product_id')
            ?: config('zoho_billing.product_id')
            ?: '');

        if ($productId === '') {
            throw new RuntimeException('Circle addon product id is not configured. Set ZOHO_CIRCLE_ADDON_PRODUCT_ID.');
        }

        return $productId;
    }

    private function isValidAddonCode(string $code): bool
    {
        return (bool) preg_match('/^[0-9]{1,6}$/', $code);
    }

    private function isCodeRelatedZohoError(Throwable $throwable): bool
    {
        $message = Str::lower($throwable->getMessage());

        return Str::contains($message, ['invalid value passed for code', 'code already exists', 'duplicate', 'invalid code', 'addon_code', 'code']);
    }
}

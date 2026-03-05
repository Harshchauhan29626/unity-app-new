<?php

namespace App\Services\Zoho;

use App\Models\Circle;
use App\Models\CircleSubscriptionPrice;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ZohoCircleAddonService
{
    private const DURATION_LABELS = [
        1 => 'Monthly',
        3 => 'Quarterly',
        6 => 'Half-Yearly',
        12 => 'Yearly',
    ];

    private const MAX_ZOHO_CREATE_ATTEMPTS = 10;
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
        $maxUsed = (int) (CircleSubscriptionPrice::query()
            ->whereNotNull('zoho_addon_code')
            ->whereRaw("zoho_addon_code ~ '^[0-9]+$'")
            ->selectRaw('COALESCE(MAX(CAST(zoho_addon_code AS INT)), 0) as max_code')
            ->value('max_code') ?? 0);

        $next = max(1, $maxUsed + 1);

        for ($i = 0; $i < self::MAX_DB_CODE_SCAN_ATTEMPTS; $i++) {
            if (! $this->addonCodeExists($next)) {
                return $this->formatNumericCode($next);
            }

            $next++;
        }

        throw new \RuntimeException('Unable to generate unique numeric Zoho addon code within scan limit.');
    }

    private function createAddonWithRetry(Circle $circle, CircleSubscriptionPrice $price, string $addonName): void
    {
        $codeNumber = $this->resolveInitialCodeNumber($circle, $price);

        for ($attempt = 1; $attempt <= self::MAX_ZOHO_CREATE_ATTEMPTS; $attempt++) {
            $codeNumber = $this->nextFreeCodeNumber($codeNumber, $price->id);

            $addonCode = $this->formatNumericCode($codeNumber);
            $payload = [
                'name' => $addonName,
                'code' => $addonCode,
                'price' => (float) $price->price,
                'type' => 'recurring',
            ];

            Log::info('Zoho circle addon create attempt', [
                'circle_id' => $circle->id,
                'duration_months' => $price->duration_months,
                'attempt' => $attempt,
                'addon_code' => $addonCode,
                'payload' => $payload,
            ]);

            try {
                $response = $this->zohoBillingService->createAddon($payload);
                $addon = data_get($response, 'addon', []);

                DB::transaction(function () use ($price, $addon, $addonCode, $addonName, $response): void {
                    $price->forceFill([
                        'zoho_addon_id' => (string) (data_get($addon, 'addon_id') ?? data_get($addon, 'id') ?? ''),
                        'zoho_addon_code' => (string) (data_get($addon, 'code') ?? $addonCode),
                        'zoho_addon_name' => (string) (data_get($addon, 'name') ?? $addonName),
                        'payload' => $response,
                    ])->save();
                });

                return;
            } catch (Throwable $throwable) {
                if (! $this->isCodeRelatedZohoError($throwable) || $attempt === self::MAX_ZOHO_CREATE_ATTEMPTS) {
                    throw $throwable;
                }

                Log::warning('Zoho circle addon create retry due to code issue', [
                    'circle_id' => $circle->id,
                    'duration_months' => $price->duration_months,
                    'attempt' => $attempt,
                    'addon_code' => $addonCode,
                    'message' => $throwable->getMessage(),
                ]);

                $codeNumber++;
            }
        }
    }

    private function syncExistingAddon(Circle $circle, CircleSubscriptionPrice $price, string $addonName): void
    {
        $codeNumber = $this->resolveInitialCodeNumber($circle, $price);
        $codeNumber = $this->nextFreeCodeNumber($codeNumber, $price->id);

        $addonCode = $this->formatNumericCode($codeNumber);

        $payload = [
            'name' => $addonName,
            'code' => $addonCode,
            'price' => (float) $price->price,
            'type' => 'recurring',
        ];

        try {
            $response = $this->zohoBillingService->updateAddon((string) $price->zoho_addon_id, $payload);
            $addon = data_get($response, 'addon', []);

            $price->forceFill([
                'zoho_addon_id' => (string) (data_get($addon, 'addon_id') ?? data_get($addon, 'id') ?? $price->zoho_addon_id),
                'zoho_addon_code' => (string) (data_get($addon, 'code') ?? $addonCode),
                'zoho_addon_name' => (string) (data_get($addon, 'name') ?? $addonName),
                'payload' => $response,
            ])->save();
        } catch (Throwable $throwable) {
            if (! $this->isCodeRelatedZohoError($throwable)) {
                throw $throwable;
            }

            Log::warning('Zoho circle addon update failed due to code issue, creating replacement addon', [
                'circle_id' => $circle->id,
                'duration_months' => $price->duration_months,
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

    private function formatNumericCode(int $number): string
    {
        if ($number < 100) {
            return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        }

        return (string) $number;
    }

    private function nextFreeCodeNumber(int $codeNumber, ?string $ignoreId = null): int
    {
        for ($i = 0; $i < self::MAX_DB_CODE_SCAN_ATTEMPTS; $i++) {
            if (! $this->addonCodeExists($codeNumber, $ignoreId)) {
                return $codeNumber;
            }

            $codeNumber++;
        }

        throw new \RuntimeException('Unable to resolve free numeric Zoho addon code within scan limit.');
    }

    private function addonCodeExists(int $codeNumber, ?string $ignoreId = null): bool
    {
        $query = CircleSubscriptionPrice::query()
            ->where('zoho_addon_code', $this->formatNumericCode($codeNumber));

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function isValidAddonCode(string $code): bool
    {
        if (! preg_match('/^[0-9]+$/', $code)) {
            return false;
        }

        if (strlen($code) >= 3 && str_starts_with($code, '0')) {
            return false;
        }

        return true;
    }

    private function isCodeRelatedZohoError(Throwable $throwable): bool
    {
        $message = Str::lower($throwable->getMessage());

        return Str::contains($message, ['invalid value passed for code', 'code already exists', 'duplicate', 'invalid code', 'code']);
    }
}

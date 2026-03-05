<?php

namespace App\Services\Zoho;

use App\Models\Circle;
use App\Models\CircleSubscriptionPrice;
use App\Support\Zoho\ZohoBillingService;
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

    private const MAX_DB_CODE_ATTEMPTS = 10;
    private const MAX_ZOHO_CREATE_ATTEMPTS = 5;

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
        for ($attempt = 1; $attempt <= self::MAX_DB_CODE_ATTEMPTS; $attempt++) {
            $random = Str::upper(Str::random(12));
            $candidate = sprintf('CIR_%dM_%s', $durationMonths, preg_replace('/[^A-Z0-9]/', '', $random));
            $candidate = substr($candidate, 0, 40);

            $exists = CircleSubscriptionPrice::query()
                ->where('zoho_addon_code', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate unique Zoho addon code.');
    }

    private function createAddonWithRetry(Circle $circle, CircleSubscriptionPrice $price, string $addonName): void
    {
        $fallbackCode = $this->isValidAddonCode((string) $price->zoho_addon_code)
            ? (string) $price->zoho_addon_code
            : null;

        for ($attempt = 1; $attempt <= self::MAX_ZOHO_CREATE_ATTEMPTS; $attempt++) {
            $addonCode = $attempt === 1 && $fallbackCode
                ? $fallbackCode
                : $this->generateUniqueAddonCode($circle, (int) $price->duration_months);

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
            ]);

            try {
                $response = $this->zohoBillingService->createAddon($payload);
                $addon = data_get($response, 'addon', []);

                $price->forceFill([
                    'zoho_addon_id' => (string) (data_get($addon, 'addon_id') ?? data_get($addon, 'id') ?? ''),
                    'zoho_addon_code' => (string) (data_get($addon, 'code') ?? $addonCode),
                    'zoho_addon_name' => (string) (data_get($addon, 'name') ?? $addonName),
                    'payload' => $response,
                ])->save();

                return;
            } catch (Throwable $throwable) {
                if (! $this->isCodeRelatedZohoError($throwable) || $attempt === self::MAX_ZOHO_CREATE_ATTEMPTS) {
                    throw $throwable;
                }
            }
        }
    }

    private function syncExistingAddon(Circle $circle, CircleSubscriptionPrice $price, string $addonName): void
    {
        $addonCode = $this->isValidAddonCode((string) $price->zoho_addon_code)
            ? (string) $price->zoho_addon_code
            : $this->generateUniqueAddonCode($circle, (int) $price->duration_months);

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

    private function isValidAddonCode(string $code): bool
    {
        if ($code === '') {
            return false;
        }

        return (bool) preg_match('/^[A-Z0-9_]{5,40}$/', $code);
    }

    private function isCodeRelatedZohoError(Throwable $throwable): bool
    {
        $message = Str::lower($throwable->getMessage());

        return Str::contains($message, ['invalid value passed for code', 'code already exists', 'duplicate', 'invalid code']);
    }
}

<?php

namespace App\Services\Zoho;

use App\Enums\CircleBillingTerm;
use App\Models\Circle;
use App\Models\CircleZohoAddon;
use App\Support\Zoho\ZohoBillingClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CircleAddonSyncService
{
    public function __construct(
        private readonly CircleAddonCodeGenerator $codeGenerator,
        private readonly CircleAddonPayloadBuilder $payloadBuilder,
        private readonly ZohoBillingClient $client,
    ) {
    }

    public function syncCircle(Circle $circle): array
    {
        if (! $this->isPaymentEnabled($circle)) {
            $this->markCircleAddonsInactive($circle);

            return ['created' => 0, 'updated' => 0, 'skipped' => 4];
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach (CircleBillingTerm::cases() as $term) {
            $amount = $this->resolveAmount($circle, $term);

            if ($amount <= 0) {
                $result['skipped']++;
                continue;
            }

            $code = $this->codeGenerator->generate($circle, $term);
            $payload = $this->payloadBuilder->build($circle, $term, $code, $amount);
            $syncHash = $this->payloadBuilder->syncHash($circle, $term, $payload, true);
            $local = CircleZohoAddon::query()->firstOrNew([
                'circle_id' => $circle->id,
                'billing_term' => $term->value,
            ]);

            if ($local->exists && (string) $local->sync_hash === $syncHash && (string) $local->addon_code === $code) {
                $result['skipped']++;
                continue;
            }

            $remoteAddon = $this->findRemoteAddonByCode($code);
            $action = 'created';

            if ($remoteAddon) {
                $remoteId = (string) ($remoteAddon['addon_id'] ?? '');
                $response = $remoteId !== ''
                    ? $this->client->request('PUT', '/addons/' . $remoteId, $payload)
                    : $this->client->request('POST', '/addons', $payload);
                $remoteAddon = $response['addon'] ?? $remoteAddon;
                $action = 'updated';
            } else {
                $response = $this->client->request('POST', '/addons', $payload);
                $remoteAddon = $response['addon'] ?? [];
            }

            $this->saveLocalAddon($local, $circle, $term, $code, $payload, $syncHash, $remoteAddon, true);

            $result[$action]++;

            Log::info('Circle addon synced', [
                'circle_id' => $circle->id,
                'billing_term' => $term->value,
                'addon_code' => $code,
                'zoho_addon_id' => $local->zoho_addon_id ?? null,
                'action' => $action,
            ]);
        }

        return $result;
    }

    public function resolveAvailablePlans(Circle $circle): array
    {
        $plans = [];

        foreach (CircleBillingTerm::cases() as $term) {
            $amount = $this->resolveAmount($circle, $term);
            $code = $this->codeGenerator->generate($circle, $term);
            $plans[] = [
                'billing_term' => $term->value,
                'label' => $term->label(),
                'months' => $term->months(),
                'amount' => $amount,
                'available' => $this->isPaymentEnabled($circle) && $amount > 0,
                'addon_code' => $code,
            ];
        }

        return $plans;
    }

    private function saveLocalAddon(CircleZohoAddon $model, Circle $circle, CircleBillingTerm $term, string $code, array $payload, string $syncHash, array $remoteAddon, bool $isActive): void
    {
        $tableColumns = Schema::getColumnListing($model->getTable());

        $data = Arr::only([
            'circle_id' => $circle->id,
            'billing_term' => $term->value,
            'addon_code' => $code,
            'zoho_addon_id' => (string) ($remoteAddon['addon_id'] ?? $model->zoho_addon_id ?? ''),
            'zoho_product_id' => (string) ($payload['product_id'] ?? ''),
            'name' => (string) ($payload['name'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'price' => (float) ($payload['price'] ?? 0),
            'currency_code' => 'INR',
            'is_active' => $isActive,
            'sync_hash' => $syncHash,
            'last_synced_at' => now(),
            'metadata' => ['remote' => $remoteAddon],
        ], $tableColumns);

        $model->forceFill($data)->save();
    }

    private function findRemoteAddonByCode(string $code): ?array
    {
        $response = $this->client->request('GET', '/addons', ['page' => 1, 'per_page' => 200], true);
        $addons = $response['addons'] ?? [];

        foreach ($addons as $addon) {
            if ((string) ($addon['addon_code'] ?? '') === $code) {
                return $addon;
            }
        }

        return null;
    }

    private function markCircleAddonsInactive(Circle $circle): void
    {
        if (! Schema::hasTable('circle_zoho_addons')) {
            return;
        }

        CircleZohoAddon::query()->where('circle_id', $circle->id)->update(['is_active' => false]);
    }

    public function isPaymentEnabled(Circle $circle): bool
    {
        foreach (['circle_payment_enabled', 'payment_enabled', 'is_payment_enabled', 'is_paid', 'paid_enabled'] as $column) {
            if (Schema::hasColumn('circles', $column)) {
                return (bool) data_get($circle, $column, false);
            }
        }

        return false;
    }

    public function resolveAmount(Circle $circle, CircleBillingTerm $term): float
    {
        $candidates = match ($term) {
            CircleBillingTerm::MONTHLY => ['monthly_amount', 'monthly_price', 'price_monthly', 'amount_monthly'],
            CircleBillingTerm::QUARTERLY => ['quarterly_amount', 'quarterly_price', 'price_quarterly', 'amount_quarterly'],
            CircleBillingTerm::HALF_YEARLY => ['half_yearly_amount', 'half_yearly_price', 'six_month_amount', 'price_half_yearly'],
            CircleBillingTerm::YEARLY => ['yearly_amount', 'yearly_price', 'annual_amount', 'price_yearly'],
        };

        foreach ($candidates as $column) {
            if (Schema::hasColumn('circles', $column)) {
                return (float) data_get($circle, $column, 0);
            }
        }

        return 0;
    }
}

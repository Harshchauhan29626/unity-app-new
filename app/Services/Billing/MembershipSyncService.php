<?php

namespace App\Services\Billing;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MembershipSyncService
{
    public function syncUserMembershipFromZoho(User $user, array $zohoData): User
    {
        $subscription = $zohoData['subscription'] ?? [];
        $invoice = $zohoData['invoice'] ?? [];

        $startAt = $subscription['current_term_starts_at']
            ?? $subscription['start_date']
            ?? $subscription['created_time']
            ?? now()->toDateTimeString();

        $endAt = $subscription['current_term_ends_at']
            ?? $subscription['expires_at']
            ?? $subscription['next_billing_at']
            ?? $this->calculateEndsAt($startAt, $subscription);

        $updates = array_filter([
            'zoho_subscription_id' => $subscription['subscription_id'] ?? null,
            'zoho_plan_code' => data_get($subscription, 'plan.plan_code') ?? $subscription['plan_code'] ?? null,
            'zoho_last_invoice_id' => $invoice['invoice_id'] ?? $subscription['invoice_id'] ?? null,
            'membership_starts_at' => $startAt,
            'membership_ends_at' => $endAt,
            'membership_expiry' => $endAt,
            'last_payment_at' => now(),
        ], static fn ($value) => ! is_null($value));

        $membershipColumn = $this->resolveMembershipColumn();

        if ($membershipColumn) {
            $updates[$membershipColumn] = 'active';
        }

        $user->forceFill($updates);
        $user->save();

        Log::info('Membership synced from Zoho', [
            'user_id' => $user->id,
            'subscription_id' => $updates['zoho_subscription_id'] ?? null,
            'plan_code' => $updates['zoho_plan_code'] ?? null,
            'membership_column' => $membershipColumn,
        ]);

        return $user->fresh();
    }

    private function calculateEndsAt(string $startAt, array $subscription): string
    {
        try {
            $start = Carbon::parse($startAt);
        } catch (\Throwable) {
            $start = now();
        }

        $interval = (int) ($subscription['interval'] ?? 1);
        $unit = strtolower((string) ($subscription['interval_unit'] ?? ''));
        $planName = strtolower((string) ($subscription['name'] ?? data_get($subscription, 'plan.name') ?? ''));

        if ($unit === 'years' || str_contains($planName, 'annual')) {
            return $start->copy()->addYears(max(1, $interval))->toDateTimeString();
        }

        return $start->copy()->addMonths(max(1, $interval))->toDateTimeString();
    }

    private function resolveMembershipColumn(): ?string
    {
        $table = (new User())->getTable();

        foreach (['membership_status', 'membership_type', 'membership'] as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

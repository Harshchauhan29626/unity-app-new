<?php

namespace App\Support\Membership;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MembershipUpdater
{
    public function applyPaidMembership(User $user, array $attributes = []): bool
    {
        $fields = array_filter([
            'zoho_customer_id' => $attributes['zoho_customer_id'] ?? null,
            'zoho_subscription_id' => $attributes['zoho_subscription_id'] ?? null,
            'zoho_plan_code' => $attributes['zoho_plan_code'] ?? null,
            'zoho_last_invoice_id' => $attributes['zoho_last_invoice_id'] ?? null,
            'membership_starts_at' => $attributes['membership_starts_at'] ?? null,
            'membership_ends_at' => $attributes['membership_ends_at'] ?? null,
            'last_payment_at' => $attributes['last_payment_at'] ?? now(),
        ], static fn ($value) => ! is_null($value));

        $membershipColumn = $this->resolveMembershipColumn();

        if ($membershipColumn !== null) {
            $currentValue = (string) ($user->getAttribute($membershipColumn) ?? '');

            if ($currentValue !== 'only_unity_peer') {
                $fields[$membershipColumn] = 'only_unity_peer';
            }
        } else {
            Log::warning('Membership column not found for Zoho membership update', [
                'user_id' => $user->id,
            ]);
        }

        if ($fields === []) {
            return false;
        }

        $user->forceFill($fields);
        $user->save();

        return true;
    }

    private function resolveMembershipColumn(): ?string
    {
        $table = (new User())->getTable();

        foreach (['membership_type', 'membership', 'membership_status'] as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

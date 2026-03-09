<?php

namespace App\Support\Membership;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MembershipUpdater
{
    private const ALLOWED_MEMBERSHIP_STATUSES = [
        'visitor',
        'member',
        'premium',
        'charter',
        'suspended',
        'free_peer',
        'Only Unity Peer',
        'Circle Peer',
        'Multi Circle Peer',
        'Charter Peer',
    ];

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
            $planCode = (string) ($attributes['zoho_plan_code'] ?? $user->zoho_plan_code ?? '');
            $resolvedMembershipStatus = $this->resolveMembershipStatusFromPlanCode($planCode);
            $membershipStatus = $this->sanitizeMembershipStatus($resolvedMembershipStatus, $user, $planCode);

            Log::info('Updating membership', [
                'user_id' => $user->id,
                'plan_code' => $planCode,
                'membership_status' => $membershipStatus,
            ]);

            $currentValue = (string) ($user->getAttribute($membershipColumn) ?? '');

            if ($currentValue !== $membershipStatus) {
                $fields[$membershipColumn] = $membershipStatus;
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

    private function resolveMembershipStatusFromPlanCode(string $planCode): string
    {
        return match (trim($planCode)) {
            '012' => 'Only Unity Peer',
            '013' => 'Circle Peer',
            '014' => 'Multi Circle Peer',
            '015' => 'Charter Peer',
            default => 'free_peer',
        };
    }

    private function sanitizeMembershipStatus(string $membershipStatus, User $user, string $planCode): string
    {
        if (in_array($membershipStatus, self::ALLOWED_MEMBERSHIP_STATUSES, true)) {
            return $membershipStatus;
        }

        Log::error('Invalid membership status resolved for Zoho update, applying fallback', [
            'user_id' => $user->id,
            'plan_code' => $planCode,
            'resolved_membership_status' => $membershipStatus,
            'fallback_membership_status' => 'free_peer',
        ]);

        return 'free_peer';
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

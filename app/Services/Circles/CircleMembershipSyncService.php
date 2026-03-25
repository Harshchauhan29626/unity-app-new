<?php

namespace App\Services\Circles;

use App\Models\CircleMember;
use App\Models\CircleSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class CircleMembershipSyncService
{
    public function syncPaidMembershipFromSubscription(
        CircleSubscription $subscription,
        ?Carbon $paidAt = null,
        ?Carbon $startedAt = null,
        ?Carbon $expiresAt = null
    ): CircleMember {
        $paidAt = $paidAt ?: now();
        $startedAt = $startedAt ?: $paidAt;
        $expiresAt = $expiresAt ?: $startedAt->copy()->addMonths(max(1, (int) ($subscription->circle?->circle_duration_months ?: 12)));

        $member = CircleMember::withTrashed()
            ->where('circle_id', $subscription->circle_id)
            ->where('user_id', $subscription->user_id)
            ->first();

        $updates = [
            'status' => 'active',
            'role' => $member?->role ?: 'member',
            'joined_at' => $member?->joined_at ?: $startedAt,
            'left_at' => null,
        ];

        if (Schema::hasColumn('circle_members', 'joined_via')) {
            $updates['joined_via'] = 'payment';
        }

        if (Schema::hasColumn('circle_members', 'joined_via_payment')) {
            $updates['joined_via_payment'] = true;
        }

        if (Schema::hasColumn('circle_members', 'payment_status')) {
            $updates['payment_status'] = 'paid';
        }

        if (Schema::hasColumn('circle_members', 'payment_id')) {
            $updates['payment_id'] = $subscription->zoho_payment_id;
        }

        if (Schema::hasColumn('circle_members', 'paid_at')) {
            $updates['paid_at'] = $paidAt;
        }

        if (Schema::hasColumn('circle_members', 'paid_starts_at')) {
            $updates['paid_starts_at'] = $startedAt;
        }

        if (Schema::hasColumn('circle_members', 'paid_ends_at')) {
            $updates['paid_ends_at'] = $expiresAt;
        }

        if (Schema::hasColumn('circle_members', 'billing_term')) {
            $updates['billing_term'] = 'yearly';
        }

        if (Schema::hasColumn('circle_members', 'zoho_subscription_id')) {
            $updates['zoho_subscription_id'] = $subscription->zoho_subscription_id;
        }

        if (Schema::hasColumn('circle_members', 'zoho_addon_code')) {
            $updates['zoho_addon_code'] = $subscription->zoho_addon_code;
        }

        if ($member) {
            if ($member->trashed()) {
                $member->restore();
            }

            $member->forceFill($updates)->save();

            return $member->fresh();
        }

        return CircleMember::query()->create(array_merge($updates, [
            'circle_id' => $subscription->circle_id,
            'user_id' => $subscription->user_id,
        ]));
    }

    public function refreshUserActiveCircleSummary(User $user): void
    {
        $latestActiveMembership = CircleMember::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'approved'])
            ->orderByDesc('joined_at')
            ->orderByDesc('updated_at')
            ->first();

        if (! $latestActiveMembership) {
            return;
        }

        $activeSubscription = CircleSubscription::query()
            ->where('user_id', $user->id)
            ->where('circle_id', $latestActiveMembership->circle_id)
            ->where('status', 'active')
            ->latest('paid_at')
            ->latest('created_at')
            ->first();

        $user->forceFill([
            'active_circle_id' => $latestActiveMembership->circle_id,
            'active_circle_subscription_id' => $activeSubscription?->id,
            'active_circle_addon_code' => $activeSubscription?->zoho_addon_code,
            'active_circle_addon_name' => $activeSubscription?->zoho_addon_name,
            'circle_joined_at' => $latestActiveMembership->joined_at,
            'circle_expires_at' => $activeSubscription?->expires_at,
        ])->save();
    }
}


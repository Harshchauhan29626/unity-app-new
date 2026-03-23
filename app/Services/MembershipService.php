<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipService
{
    public function calculateAmounts(MembershipPlan $plan): array
    {
        $baseAmount = (float) $plan->price;
        $gstPercent = (float) $plan->gst_percent;
        $gstAmount = round($baseAmount * ($gstPercent / 100), 2);
        $totalAmount = round($baseAmount + $gstAmount, 2);

        return [
            'base_amount' => $baseAmount,
            'gst_percent' => $gstPercent,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
        ];
    }

    public function resolveMembershipStatus(MembershipPlan $plan): string
    {
        $slug = strtolower((string) $plan->slug);

        if (Str::startsWith($slug, 'unity_peer')) {
            return 'unity_peer';
        }

        return match ($slug) {
            'circle_peer' => 'circle_peer',
            'multi_circle_peer' => 'multi_circle_peer',
            'charter_peer' => 'charter_peer',
            'free_peer' => 'free_peer',
            default => 'free_peer',
        };
    }

    /**
     * TODO: Recompute membership status from paid circle membership counts.
     * This is intentionally left as a placeholder for future circle billing logic.
     */
    public function recomputeStatusFromCircles(User $user): void
    {
        // TODO: Implement membership_status resolution based on circle participation rules.
    }

    public function activateMembership(User $user, MembershipPlan $plan, Payment $payment): User
    {
        $now = now();
        $endsAt = null;

        if ((int) $plan->duration_days > 0) {
            $endsAt = $now->copy()->addDays((int) $plan->duration_days);
        }

        UserMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'ends_at' => $now,
            ]);

        $existingMembership = UserMembership::query()
            ->where('payment_id', $payment->id)
            ->first();

        if (! $existingMembership) {
            UserMembership::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'membership_plan_id' => $plan->id,
                'starts_at' => $now,
                'ends_at' => $endsAt,
                'status' => 'active',
                'payment_id' => $payment->id,
            ]);
        }

        $membershipStatus = $this->resolveMembershipStatus($plan);
        $coins = (int) ($plan->coins ?? 0);

        $userUpdate = [
            'membership_status' => $membershipStatus,
            'membership_ends_at' => $endsAt,
            'membership_expiry' => $endsAt,
        ];

        if ($coins > 0) {
            $userUpdate['coins_balance'] = DB::raw('COALESCE(coins_balance, 0) + ' . $coins);
        }

        User::query()->where('id', $user->id)->update($userUpdate);

        return $user->fresh();
    }
}

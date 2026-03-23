<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipsExpire extends Command
{
    protected $signature = 'memberships:expire';
    protected $description = 'Expire memberships and downgrade users when needed.';

    public function handle(): int
    {
        $now = now();

        $expiredMemberships = UserMembership::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->get();

        if ($expiredMemberships->isEmpty()) {
            $this->info('No memberships to expire.');

            return self::SUCCESS;
        }

        $membershipIds = $expiredMemberships->pluck('id')->all();
        $userIds = $expiredMemberships->pluck('user_id')->unique()->values()->all();

        UserMembership::query()
            ->whereIn('id', $membershipIds)
            ->update(['status' => 'expired']);

        foreach ($userIds as $userId) {
            DB::transaction(function () use ($userId, $now): void {
                $hasActiveMembership = UserMembership::query()
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where(function ($query) use ($now) {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', $now);
                    })
                    ->exists();

                if ($hasActiveMembership) {
                    return;
                }

                User::query()->where('id', $userId)->update([
                    'membership_status' => 'free_peer',
                    'membership_ends_at' => null,
                    'membership_expiry' => null,
                ]);
            });
        }

        Log::info('Expired memberships processed', [
            'count' => count($membershipIds),
        ]);

        $this->info('Expired memberships processed.');

        return self::SUCCESS;
    }
}

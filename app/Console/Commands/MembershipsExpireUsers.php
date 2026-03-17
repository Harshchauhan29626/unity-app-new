<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MembershipsExpireUsers extends Command
{
    protected $signature = 'memberships:expire-users';

    protected $description = 'Normalize expired users to Free Peer membership status.';

    public function handle(): int
    {
        $freePeerStatus = User::freePeerMembershipStatus();

        $updated = User::query()
            ->whereNotNull('membership_ends_at')
            ->where('membership_ends_at', '<', now())
            ->where('membership_status', '!=', $freePeerStatus)
            ->update([
                'membership_status' => $freePeerStatus,
            ]);

        $this->info("Expired users normalized: {$updated}");

        return self::SUCCESS;
    }
}

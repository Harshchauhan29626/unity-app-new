<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ExpireTrialUsers extends Command
{
    protected $signature = 'users:expire-trial';

    protected $description = 'Expire free trial users and downgrade them to free peer.';

    public function handle(): int
    {
        $updated = User::query()
            ->where('membership_status', User::STATUS_FREE_TRIAL)
            ->whereNotNull('membership_ends_at')
            ->where('membership_ends_at', '<=', now())
            ->update([
                'membership_status' => User::STATUS_FREE,
            ]);

        $this->info("Expired trial users: {$updated}");

        return self::SUCCESS;
    }
}

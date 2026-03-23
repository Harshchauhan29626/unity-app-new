<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMembershipExpiryFields extends Command
{
    protected $signature = 'users:sync-membership-expiry';

    protected $description = 'Backfill membership_expiry from membership_ends_at for rows with mismatched values.';

    public function handle(): int
    {
        $updated = DB::update(<<<'SQL'
UPDATE users
SET membership_expiry = membership_ends_at
WHERE membership_expiry IS DISTINCT FROM membership_ends_at
SQL);

        $this->info("Membership expiry rows synchronized: {$updated}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\CollaborationPost;
use Illuminate\Console\Command;

class ExpireCollaborationPosts extends Command
{
    protected $signature = 'collaborations:expire';

    protected $description = 'Expire active collaboration posts past their expiry date';

    public function handle(): int
    {
        $updated = CollaborationPost::query()
            ->where('status', CollaborationPost::STATUS_ACTIVE)
            ->where('expires_at', '<', now())
            ->update(['status' => CollaborationPost::STATUS_EXPIRED]);

        $this->info("Expired {$updated} collaboration posts.");

        return self::SUCCESS;
    }
}

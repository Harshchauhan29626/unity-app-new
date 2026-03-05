<?php

namespace App\Console\Commands;

use App\Models\Circle;
use App\Services\Zoho\ZohoCircleAddonSyncService;
use Illuminate\Console\Command;

class SyncCircleZohoAddons extends Command
{
    protected $signature = 'circles:sync-zoho-addons {--circle_id=}';

    protected $description = 'Sync Zoho addons for circle subscription durations.';

    public function __construct(private readonly ZohoCircleAddonSyncService $circleZohoAddonSyncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $circleId = (string) $this->option('circle_id');

        $query = Circle::query()->whereHas('subscriptionPrices');

        if ($circleId !== '') {
            $query->where('id', $circleId);
        }

        $circles = $query->get();

        foreach ($circles as $circle) {
            $this->circleZohoAddonSyncService->syncCircleAddons($circle);
        }

        $this->info('Synced Zoho addons for ' . $circles->count() . ' circle(s).');

        return self::SUCCESS;
    }
}

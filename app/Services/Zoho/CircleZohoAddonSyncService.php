<?php

namespace App\Services\Zoho;

use App\Models\Circle;

class CircleZohoAddonSyncService
{
    public function __construct(private readonly ZohoCircleAddonSyncService $service)
    {
    }

    public function syncCircleAddons(Circle $circle): void
    {
        $this->service->syncCircleAddons($circle);
    }
}

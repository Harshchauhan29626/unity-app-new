<?php

namespace App\Services\Zoho;

use App\Models\Circle;

class ZohoCircleAddonService
{
    private const DURATION_LABELS = [
        1 => 'Monthly',
        3 => 'Quarterly',
        6 => 'Half-Yearly',
        12 => 'Yearly',
    ];

    public function __construct(private readonly CircleZohoAddonSyncService $circleZohoAddonSyncService)
    {
    }

    public function ensureAddonsForCircle(Circle $circle): void
    {
        $this->circleZohoAddonSyncService->syncCircleAddons($circle);
    }

    public static function durationLabel(int $durationMonths): string
    {
        return self::DURATION_LABELS[$durationMonths] ?? ($durationMonths . ' Months');
    }
}

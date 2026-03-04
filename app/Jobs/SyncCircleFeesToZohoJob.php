<?php

namespace App\Jobs;

use App\Models\Circle;
use App\Services\Zoho\ZohoCircleAddonService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncCircleFeesToZohoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $circleId)
    {
    }

    public function handle(ZohoCircleAddonService $zohoCircleAddonService): void
    {
        $requestId = (string) Str::uuid();
        $circle = Circle::query()->find($this->circleId);

        if (! $circle) {
            Log::warning('SyncCircleFeesToZohoJob circle missing', [
                'request_id' => $requestId,
                'circle_id' => $this->circleId,
            ]);

            return;
        }

        $zohoCircleAddonService->syncCircleFeesToZoho($circle);

        Log::info('SyncCircleFeesToZohoJob completed', [
            'request_id' => $requestId,
            'circle_id' => $this->circleId,
        ]);
    }
}

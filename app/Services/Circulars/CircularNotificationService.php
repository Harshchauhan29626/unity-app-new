<?php

namespace App\Services\Circulars;

use App\Models\Circular;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;

class CircularNotificationService
{
    public function __construct(private readonly ?PushNotificationService $pushNotificationService = null)
    {
    }

    public function notify(Circular $circular): void
    {
        Log::info('Circular notification attempt queued.', [
            'circular_id' => $circular->id,
            'title' => $circular->title,
            'audience_type' => $circular->audience_type,
            'city_id' => $circular->city_id,
            'circle_id' => $circular->circle_id,
        ]);

        if (! $this->pushNotificationService) {
            return;
        }

        try {
            User::query()
                ->select(['id'])
                ->limit(50)
                ->each(function (User $user) use ($circular): void {
                    $this->pushNotificationService?->send(
                        $user,
                        $circular->title,
                        $circular->summary,
                        ['type' => 'circular', 'circular_id' => (string) $circular->id]
                    );
                });
        } catch (\Throwable $exception) {
            Log::warning('Circular push notification dispatch failed.', [
                'circular_id' => $circular->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

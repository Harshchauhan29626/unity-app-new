<?php

namespace App\Services\Impacts;

use App\Jobs\SendFcmNotificationJob;
use App\Models\Impact;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImpactUserNotificationService
{
    public function sendSubmitted(Impact $impact): Notification
    {
        $payload = [
            'notification_type' => 'impact_submitted',
            'title' => 'Impact Submitted',
            'body' => 'Your Impact has been submitted successfully and is awaiting review.',
            'impact_id' => (string) $impact->id,
            'status' => (string) $impact->status,
        ];

        return $this->storeAndDispatch($impact, 'impact_submitted', $payload);
    }

    public function sendApproved(Impact $impact): Notification
    {
        $payload = [
            'notification_type' => 'impact_approved',
            'title' => 'Impact Approved',
            'body' => 'Your Impact has been approved successfully.',
            'impact_id' => (string) $impact->id,
            'status' => (string) $impact->status,
            'life_impacted' => (int) ($impact->life_impacted ?? 1),
        ];

        return $this->storeAndDispatch($impact, 'impact_approved', $payload);
    }

    private function storeAndDispatch(Impact $impact, string $type, array $payload): Notification
    {
        Log::info('impact.notification.store', [
            'impact_id' => (string) $impact->id,
            'user_id' => (string) $impact->user_id,
            'type' => $type,
        ]);

        $notification = Notification::create([
            'user_id' => $impact->user_id,
            'type' => $type,
            'payload' => $payload,
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ]);

        DB::afterCommit(function () use ($impact, $type, $payload): void {
            SendFcmNotificationJob::dispatch(
                (string) $impact->user_id,
                (string) ($payload['title'] ?? 'Notification'),
                (string) ($payload['body'] ?? 'You have a new notification'),
                [
                    'notification_type' => $type,
                    'impact_id' => (string) $impact->id,
                ]
            );
        });

        return $notification;
    }
}

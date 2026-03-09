<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;

class PushNotificationService
{
    public function send(User $toUser, string $title, string $body, array $data = []): void
    {
        SendPushNotificationJob::dispatch($toUser, $title, $body, $data);
    }

    public function storeAndSend(User $toUser, string $title, string $body, array $payload, array $pushData = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $toUser->id,
            'type' => 'activity_update',
            'payload' => $payload,
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ]);

        $this->send($toUser, $title, $body, $pushData);

        return $notification;
    }
}

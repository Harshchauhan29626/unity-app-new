<?php

namespace App\Notifications;

use App\Http\Resources\UserMiniResource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UnfollowedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $fromUser)
    {
    }

    public function toArray($notifiable): array
    {
        return [
            'notification_type' => 'unfollowed',
            'title' => 'User unfollowed you',
            'body' => ($this->fromUser->display_name ?? $this->fromUser->first_name ?? 'Someone').' unfollowed you.',
            'from_user' => (new UserMiniResource($this->fromUser))->resolve(),
            'follow' => null,
        ];
    }
}

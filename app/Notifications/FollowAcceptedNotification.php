<?php

namespace App\Notifications;

use App\Http\Resources\UserMiniResource;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FollowAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $fromUser,
        private readonly UserFollow $follow
    ) {
    }

    public function toArray($notifiable): array
    {
        return [
            'notification_type' => 'follow_accepted',
            'title' => 'Follow request accepted',
            'body' => ($this->fromUser->display_name ?? $this->fromUser->first_name ?? 'Someone').' accepted your follow request.',
            'from_user' => (new UserMiniResource($this->fromUser))->resolve(),
            'follow' => [
                'id' => $this->follow->id,
                'status' => $this->follow->status,
            ],
        ];
    }
}

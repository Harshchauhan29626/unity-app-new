<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CircleChatMessagesRead implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $circleId,
        public array $messageIds,
        public array $reader,
    ) {
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('presence-circle-chat.' . $this->circleId);
    }

    public function broadcastAs(): string
    {
        return 'circle.chat.message.read';
    }

    public function broadcastWith(): array
    {
        return [
            'circle_id' => $this->circleId,
            'message_ids' => $this->messageIds,
            'reader' => $this->reader,
        ];
    }
}

<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        if (! $user->pushTokens()->exists()) {
            return;
        }

        try {
            SendPushNotificationJob::dispatch($user, $this->title, $this->body, $this->data);
        } catch (\Throwable $exception) {
            Log::error('coin_claim.fcm_dispatch_failed', [
                'user_id' => $this->userId,
                'title' => $this->title,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

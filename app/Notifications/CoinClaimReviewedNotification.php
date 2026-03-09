<?php

namespace App\Notifications;

use App\Models\CoinClaimRequest;
use App\Support\CoinClaims\CoinClaimActivityRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CoinClaimReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CoinClaimRequest $claim,
        private readonly string $decision,
        private readonly ?int $coinsAwarded = null,
        private readonly ?string $reason = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->decision === 'approved'
            ? 'coin_claim_approved'
            : 'coin_claim_rejected';
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        $registry = app(CoinClaimActivityRegistry::class);
        $activity = $registry->get((string) $this->claim->activity_code);
        $notificationType = $this->decision === 'approved'
            ? 'coin_claim_approved'
            : 'coin_claim_rejected';

        return [
            'notification_type' => $notificationType,
            'coin_claim_id' => (string) $this->claim->id,
            'claim_id' => (string) $this->claim->id,
            'activity_code' => (string) $this->claim->activity_code,
            'activity_label' => $activity['label'] ?? null,
            'decision' => $this->decision,
            'status' => $this->decision,
            'coins_awarded' => $this->coinsAwarded,
            'reason' => $this->decision === 'rejected' ? $this->reason : null,
            'reviewed_at' => optional($this->claim->reviewed_at)->toISOString(),
        ];
    }
}

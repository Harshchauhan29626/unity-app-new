<?php

namespace App\Services\CoinClaims;

use App\Events\UserNotificationCreated;
use App\Jobs\SendFcmNotificationJob;
use App\Models\CoinClaimRequest;
use App\Models\Notification;
use App\Support\CoinClaims\CoinClaimActivityRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CoinClaimUserNotificationService
{
    public function __construct(private readonly CoinClaimActivityRegistry $registry)
    {
    }

    public function sendApproved(CoinClaimRequest $claim): Notification
    {
        $activity = $this->registry->get((string) $claim->activity_code) ?? [];
        $activityLabel = (string) ($activity['label'] ?? $claim->activity_code);
        $coinsAwarded = $claim->coins_awarded !== null ? (int) $claim->coins_awarded : null;

        $payload = [
            'notification_type' => 'coin_claim_approved',
            'title' => 'Coin claim approved',
            'body' => 'Your claim for ' . $activityLabel . ' was approved. ' . ($coinsAwarded ?? 0) . ' coins added.',
            'coin_claim_id' => (string) $claim->id,
            'activity_code' => (string) $claim->activity_code,
            'coins_awarded' => $coinsAwarded,
            'reason' => null,
            'reviewed_at' => optional($claim->reviewed_at)->toISOString(),
        ];

        return $this->storeAndDispatch($claim, 'coin_claim_approved', $payload);
    }

    public function sendRejected(CoinClaimRequest $claim): Notification
    {
        $activity = $this->registry->get((string) $claim->activity_code) ?? [];
        $activityLabel = (string) ($activity['label'] ?? $claim->activity_code);
        $reason = (string) ($claim->admin_note ?? 'Not provided');

        $payload = [
            'notification_type' => 'coin_claim_rejected',
            'title' => 'Coin claim rejected',
            'body' => 'Your claim for ' . $activityLabel . ' was rejected. Reason: ' . $reason,
            'coin_claim_id' => (string) $claim->id,
            'activity_code' => (string) $claim->activity_code,
            'coins_awarded' => null,
            'reason' => $claim->admin_note,
            'reviewed_at' => optional($claim->reviewed_at)->toISOString(),
        ];

        return $this->storeAndDispatch($claim, 'coin_claim_rejected', $payload);
    }

    private function storeAndDispatch(CoinClaimRequest $claim, string $type, array $payload): Notification
    {
        Log::info('coin_claim.notification.store', [
            'claim_id' => (string) $claim->id,
            'user_id' => (string) $claim->user_id,
            'type' => $type,
        ]);

        try {
            $notification = Notification::create([
                'user_id' => $claim->user_id,
                'type' => $type,
                'payload' => $payload,
                'is_read' => false,
                'created_at' => now(),
                'read_at' => null,
            ]);

            DB::afterCommit(function () use ($notification, $payload, $claim, $type): void {
                event(new UserNotificationCreated((string) $claim->user_id, [
                    'id' => (string) $notification->id,
                    'type' => (string) $notification->type,
                    'payload' => $notification->payload,
                    'created_at' => optional($notification->created_at)->toISOString(),
                ]));

                SendFcmNotificationJob::dispatch(
                    (string) $claim->user_id,
                    (string) ($payload['title'] ?? 'Notification'),
                    (string) ($payload['body'] ?? 'You have a new notification'),
                    [
                        'notification_type' => $type,
                        'coin_claim_id' => (string) $claim->id,
                        'activity_code' => (string) $claim->activity_code,
                    ]
                );
            });

            return $notification;
        } catch (\Throwable $exception) {
            Log::error('coin_claim.notification.store_failed', [
                'claim_id' => (string) $claim->id,
                'user_id' => (string) $claim->user_id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}

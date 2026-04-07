<?php

namespace App\Services\Blocks;

use App\Models\PeerBlock;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PeerBlockService
{
    public function block(User $blocker, User $blocked, ?string $reason = null): PeerBlock
    {
        $this->assertNotSelf($blocker->id, $blocked->id);

        return PeerBlock::query()->firstOrCreate(
            [
                'blocker_user_id' => (string) $blocker->id,
                'blocked_user_id' => (string) $blocked->id,
            ],
            [
                'reason' => $reason,
            ]
        );
    }

    public function unblock(User $blocker, User $blocked): bool
    {
        $this->assertNotSelf($blocker->id, $blocked->id);

        return PeerBlock::query()
            ->where('blocker_user_id', (string) $blocker->id)
            ->where('blocked_user_id', (string) $blocked->id)
            ->delete() > 0;
    }

    public function hasBlocked(string $blockerId, string $blockedId): bool
    {
        return PeerBlock::query()
            ->where('blocker_user_id', $blockerId)
            ->where('blocked_user_id', $blockedId)
            ->exists();
    }

    public function isBlockedEitherWay(string $userAId, string $userBId): bool
    {
        return PeerBlock::query()
            ->where(function ($query) use ($userAId, $userBId): void {
                $query
                    ->where('blocker_user_id', $userAId)
                    ->where('blocked_user_id', $userBId);
            })
            ->orWhere(function ($query) use ($userAId, $userBId): void {
                $query
                    ->where('blocker_user_id', $userBId)
                    ->where('blocked_user_id', $userAId);
            })
            ->exists();
    }

    public function assertCanInteract(string $userAId, string $userBId): void
    {
        $this->assertNotSelf($userAId, $userBId);

        if ($this->isBlockedEitherWay($userAId, $userBId)) {
            throw ValidationException::withMessages([
                'peer' => ['You cannot interact with this peer.'],
            ]);
        }
    }

    public function blockedUserIdsFor(string $userId): array
    {
        return PeerBlock::query()
            ->where('blocker_user_id', $userId)
            ->pluck('blocked_user_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function usersWhoBlockedMeIdsFor(string $userId): array
    {
        return PeerBlock::query()
            ->where('blocked_user_id', $userId)
            ->pluck('blocker_user_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function blockedCountFor(string $userId): int
    {
        return PeerBlock::query()
            ->where('blocker_user_id', $userId)
            ->count();
    }

    private function assertNotSelf(string $userAId, string $userBId): void
    {
        if ($userAId === $userBId) {
            throw ValidationException::withMessages([
                'peer' => ['You cannot block yourself.'],
            ]);
        }
    }
}

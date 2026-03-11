<?php

namespace App\Services\CircleChat;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CircleChatAccessService
{
    public function ensureUserIsCircleMember(User $user, string $circleId): void
    {
        $isMember = CircleMember::query()
            ->where('circle_id', $circleId)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->exists();

        if (! $isMember) {
            throw new HttpException(403, 'You are not a member of this circle.');
        }
    }

    public function getCircleOrFail(string $circleId): Circle
    {
        $circle = Circle::query()->find($circleId);

        if (! $circle) {
            throw new HttpException(404, 'Circle not found.');
        }

        return $circle;
    }
}

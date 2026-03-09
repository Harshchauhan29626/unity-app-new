<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FollowResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'requested_at' => optional($this->requested_at)?->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)?->toIso8601String(),
            'follower' => new UserMiniResource($this->whenLoaded('follower')),
            'following' => new UserMiniResource($this->whenLoaded('following')),
        ];
    }
}

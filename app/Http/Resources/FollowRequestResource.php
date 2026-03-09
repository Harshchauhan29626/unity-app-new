<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FollowRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'follow_id' => $this->id,
            'status' => $this->status,
            'requested_at' => optional($this->requested_at)?->toIso8601String(),
            'from_user' => new UserMiniResource($this->whenLoaded('follower')),
        ];
    }
}

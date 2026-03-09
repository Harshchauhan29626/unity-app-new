<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollaborationMeetingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'status' => $this->status,
            'proposed_at' => optional($this->proposed_at)->toIso8601String(),
            'place' => $this->place,
            'note' => $this->note,
            'from_user' => [
                'id' => $this->fromUser?->id,
                'name' => $this->fromUser?->display_name,
                'city' => $this->fromUser?->city,
            ],
            'to_user' => [
                'id' => $this->toUser?->id,
                'name' => $this->toUser?->display_name,
                'city' => $this->toUser?->city,
            ],
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}

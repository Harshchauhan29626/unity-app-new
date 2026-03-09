<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class P2PMeetingRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['requester', 'invitee']);

        return [
            'id' => (string) $this->id,
            'status' => (string) $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'place' => (string) $this->place,
            'message' => $this->message,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'requester' => $this->requester?->publicProfileArray(),
            'invitee' => $this->invitee?->publicProfileArray(),
        ];
    }
}

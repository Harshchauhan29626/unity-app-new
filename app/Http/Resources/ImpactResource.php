<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImpactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'user_id' => (string) $this->user_id,
            'impacted_peer_id' => (string) $this->impacted_peer_id,
            'date' => optional($this->impact_date)?->toDateString(),
            'action' => (string) $this->action,
            'story_to_share' => (string) $this->story_to_share,
            'additional_remarks' => $this->additional_remarks,
            'requires_leadership_approval' => (bool) $this->requires_leadership_approval,
            'status' => (string) $this->status,
            'review_remarks' => $this->review_remarks,
            'approved_by' => $this->approved_by,
            'approved_at' => optional($this->approved_at)?->toISOString(),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => optional($this->rejected_at)?->toISOString(),
            'timeline_posted_at' => optional($this->timeline_posted_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => (string) $this->user->id,
                'display_name' => $this->user->display_name,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
            ]),
            'impacted_peer' => $this->whenLoaded('impactedPeer', fn () => [
                'id' => (string) $this->impactedPeer->id,
                'display_name' => $this->impactedPeer->display_name,
                'first_name' => $this->impactedPeer->first_name,
                'last_name' => $this->impactedPeer->last_name,
            ]),
        ];
    }
}

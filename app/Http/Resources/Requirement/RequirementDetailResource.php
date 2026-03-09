<?php

namespace App\Http\Resources\Requirement;

use Illuminate\Http\Request;

class RequirementDetailResource extends RequirementTimelineResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['interested_count'] = (int) ($this->interests_count ?? $this->interests?->count() ?? 0);

        if ($request->user() && (string) $request->user()->id === (string) $this->user_id) {
            $data['interested_peers'] = RequirementInterestResource::collection($this->whenLoaded('interests'));
        }

        return $data;
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockedPeerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fullName = trim((string) $this->blockedUser?->first_name . ' ' . (string) $this->blockedUser?->last_name);

        $profilePhotoUrl = $this->blockedUser?->profile_photo_file_id
            ? url('/api/v1/files/' . $this->blockedUser->profile_photo_file_id)
            : $this->blockedUser?->profile_photo_url;

        return [
            'id' => (string) $this->blocked_user_id,
            'display_name' => $this->blockedUser?->display_name ?: ($fullName !== '' ? $fullName : null),
            'first_name' => $this->blockedUser?->first_name,
            'last_name' => $this->blockedUser?->last_name,
            'company_name' => $this->blockedUser?->company_name,
            'designation' => $this->blockedUser?->designation,
            'profile_photo_url' => $profilePhotoUrl,
            'blocked_at' => optional($this->created_at)?->toISOString(),
            'reason' => $this->reason,
        ];
    }
}

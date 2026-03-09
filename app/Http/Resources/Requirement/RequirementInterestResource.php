<?php

namespace App\Http\Resources\Requirement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequirementInterestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->whenLoaded('user');

        return [
            'user_id' => $this->user_id,
            'name' => data_get($user, 'name')
                ?: data_get($user, 'full_name')
                ?: data_get($user, 'display_name')
                ?: trim((string) data_get($user, 'first_name', '') . ' ' . (string) data_get($user, 'last_name', '')),
            'company' => data_get($user, 'company') ?: data_get($user, 'company_name', ''),
            'city' => data_get($user, 'city', ''),
            'profile_photo_url' => $this->resolveProfilePhotoUrl($user),
            'source' => $this->source,
            'comment' => $this->comment,
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }

    private function resolveProfilePhotoUrl(mixed $user): ?string
    {
        if (! $user) {
            return null;
        }

        $profilePhotoId = data_get($user, 'profile_photo_id') ?: data_get($user, 'profile_photo_file_id');

        if ($profilePhotoId) {
            return url('/api/v1/files/' . $profilePhotoId);
        }

        return data_get($user, 'profile_photo_url');
    }
}

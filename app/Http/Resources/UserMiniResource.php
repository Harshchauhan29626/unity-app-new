<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class UserMiniResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $this->resource;

        $name = $user->name
            ?? $user->display_name
            ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        if (empty($name) && ! empty($user->email)) {
            $name = Str::before($user->email, '@');
        }

        return [
            'id' => $user->id,
            'name' => $name !== '' ? trim((string) $name) : null,
            'profile_image_url' => $this->buildProfileImageUrl(),
            'company_name' => $user->company_name,
            'city' => $user->city,
            'industry' => $user->industry ?? null,
        ];
    }

    private function buildProfileImageUrl(): ?string
    {
        $user = $this->resource;

        $fileId = $user->profile_image_id
            ?? $user->profile_photo_file_id
            ?? $user->profile_photo_id
            ?? null;

        if (! $fileId) {
            return null;
        }

        return url('/api/v1/files/'.$fileId);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CircleResource extends JsonResource
{
    public function toArray($request): array
    {
        $founder = $this->whenLoaded('founder');
        $director = $this->whenLoaded('director');
        $industryDirector = $this->whenLoaded('industryDirector');
        $ded = $this->whenLoaded('ded');
        $city = $this->whenLoaded('city');
        $currentMember = $this->whenLoaded('currentMember');

        $resolveUserCity = static function ($user): ?string {
            if (! $user) {
                return null;
            }

            $city = trim((string) ($user->city ?? ''));

            if ($city !== '') {
                return $city;
            }

            if ($user->relationLoaded('cityRelation')) {
                return $user->cityRelation?->name;
            }

            return null;
        };

        $userMini = static function ($user) use ($resolveUserCity) {
            if (! $user) {
                return null;
            }

            return [
                'id' => $user->id,
                'name' => $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'profile_photo_url' => $user->profile_photo_url,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'city' => $resolveUserCity($user),
                'company_name' => $user->company_name,
            ];
        };

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'purpose' => $this->purpose,
            'announcement' => $this->announcement,
            'status' => $this->status,
            'type' => $this->type,
            'country' => $this->country,
            'referral_score' => $this->referral_score,
            'visitor_count' => $this->visitor_count,
            'industry_tags' => $this->industry_tags,
            'calendar' => $this->calendar,
            'meeting_mode' => $this->meeting_mode,
            'meeting_frequency' => $this->meeting_frequency,
            'meeting_repeat' => $this->meeting_repeat,
            'launch_date' => $this->launch_date,
            'circle_stage' => $this->circle_stage,
            'circle_ranking' => $this->getCircleRanking(),
            'city_id' => $this->city_id,
            'city' => $city ? [
                'id' => $city->id,
                'name' => $city->name,
                'state' => $city->state,
                'district' => $city->district,
                'country' => $city->country,
                'country_code' => $city->country_code,
            ] : null,
            'founder_user_id' => $this->founder_user_id,
            'director_user_id' => $this->director_user_id,
            'industry_director_user_id' => $this->industry_director_user_id,
            'ded_user_id' => $this->ded_user_id,
            'founder' => $founder ? [
                'id' => $founder->id,
                'display_name' => $founder->display_name,
                'first_name' => $founder->first_name,
                'last_name' => $founder->last_name,
                'profile_photo_url' => $founder->profile_photo_url,
                'email' => $founder->email,
                'phone' => $founder->phone ?? null,
                'city' => $resolveUserCity($founder),
                'company_name' => $founder->company_name,
            ] : null,
            'director' => $userMini($director),
            'industry_director' => $userMini($industryDirector),
            'ded' => $userMini($ded),
            'cover_file_id' => $this->cover_file_id,
            'cover_image_url' => $this->cover_file_id
                ? url("/api/v1/files/{$this->cover_file_id}")
                : null,
            'members_count' => $this->members_count ?? null,
            'peers_count' => $this->peers_count ?? $this->members_count ?? null,
            'is_member' => $currentMember ? true : false,
            'member_status' => $currentMember->status ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

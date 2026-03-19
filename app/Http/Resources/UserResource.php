<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        $coverPhotoId = $this->cover_photo_file_id;
        $coverPhotoUrl = $coverPhotoId
            ? url('/api/v1/files/' . $coverPhotoId)
            : null;

        return [
            'id'                  => $this->id,
            'public_profile_slug' => $this->public_profile_slug,
            'profile_photo_id'    => $this->profile_photo_file_id,
            'cover_photo_id'      => $coverPhotoId,
            'first_name'          => $this->first_name,
            'last_name'           => $this->last_name,
            'display_name'        => $this->display_name,
            'company_name'        => $this->company_name,
            'designation'         => $this->designation,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'city'                => new CityResource($this->whenLoaded('city')),
            'membership_status'   => $this->effective_membership_status,
            'membership_expiry'   => $this->membership_ends_at ?? $this->membership_expiry,
            'membership_starts_at' => $this->membership_starts_at,
            'membership_ends_at' => $this->membership_ends_at,
            'zoho_plan_code' => $this->zoho_plan_code,
            'zoho_last_invoice_id' => $this->zoho_last_invoice_id,
            'active_circle_id' => $this->active_circle_id,
            'active_circle_addon_code' => $this->active_circle_addon_code,
            'active_circle_addon_name' => $this->active_circle_addon_name,
            'circle_joined_at' => $this->circle_joined_at,
            'circle_expires_at' => $this->circle_expires_at,
            'active_circle_subscription_id' => $this->active_circle_subscription_id,
            'active_circle' => $this->whenLoaded('activeCircle', function () {
                $circle = $this->activeCircle;

                if (! $circle) {
                    return null;
                }

                return [
                    'id' => $circle->id,
                    'name' => $circle->name,
                    'slug' => $circle->slug,
                    'city' => $circle->relationLoaded('cityRef') ? [
                        'id' => optional($circle->cityRef)->id,
                        'name' => optional($circle->cityRef)->name,
                    ] : null,
                ];
            }),
            'active_circle_name' => $this->relationLoaded('activeCircle')
                ? optional($this->activeCircle)->name
                : null,
            'coins_balance'       => $this->coins_balance,
            'business_type'       => $this->business_type,
            'turnover_range'      => $this->turnover_range,
            'gender'              => $this->gender,
            'dob'                 => optional($this->dob)?->format('Y-m-d'),
            'experience_years'    => $this->experience_years,
            'experience_summary'  => $this->experience_summary,
            'bio'                 => $this->short_bio,
            'long_bio_html'       => $this->long_bio_html,
            'industry_tags'       => $this->industry_tags ?? [],
            'skills'              => $this->skills ?? [],
            'interests'           => $this->interests ?? [],
            'target_regions'      => $this->target_regions ?? [],
            'target_business_categories' => $this->target_business_categories ?? [],
            'hobbies_interests'   => $this->hobbies_interests ?? [],
            'leadership_roles'    => $this->leadership_roles ?? [],
            'special_recognitions'=> $this->special_recognitions ?? [],
            'social_links'        => $this->resolveSocialLinks(),
            'profile_photo_url'   => $this->profile_photo_url,
            'cover_photo_url'     => $coverPhotoUrl,
            'address'             => $this->address ?? null,
            'state'               => $this->state ?? null,
            'country'             => $this->country ?? null,
            'pincode'             => $this->pincode ?? null,
            'is_verified'         => $this->is_verified ?? null,
            'is_sponsored_member' => $this->is_sponsored_member ?? null,
            'last_login_at'       => $this->last_login_at,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }

    private function resolveSocialLinks(): ?array
    {
        $platforms = ['facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'website'];
        $storedLinks = $this->social_links;

        if (is_string($storedLinks)) {
            $decoded = json_decode($storedLinks, true);
            $storedLinks = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        $links = [];
        foreach ($platforms as $platform) {
            $value = is_array($storedLinks) ? ($storedLinks[$platform] ?? null) : null;

            if (blank($value)) {
                $columnValue = $this->getAttribute($platform);
                $value = blank($columnValue) ? null : $columnValue;
            }

            $links[$platform] = $value;
        }

        return collect($links)->filter(fn ($link) => ! blank($link))->isNotEmpty()
            ? $links
            : null;
    }
}

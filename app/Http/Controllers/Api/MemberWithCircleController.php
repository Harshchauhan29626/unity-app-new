<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Support\Str;

class MemberWithCircleController extends BaseApiController
{
    public function index()
    {
        $members = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'display_name',
                'email',
                'phone',
                'designation',
                'company_name',
                'city_id',
                'city',
                'membership_status',
                'membership_expiry',
                'coins_balance',
                'public_profile_slug',
                'last_login_at',
                'status',
                'created_at',
                'updated_at',
                'profile_photo_file_id',
                'cover_photo_file_id',
                'membership_starts_at',
                'membership_ends_at',
                'zoho_plan_code',
                'zoho_last_invoice_id',
                'active_circle_id',
                'active_circle_addon_code',
                'active_circle_addon_name',
                'circle_joined_at',
                'circle_expires_at',
                'active_circle_subscription_id',
                'business_type',
                'turnover_range',
                'gender',
                'dob',
                'experience_years',
                'experience_summary',
                'short_bio',
                'long_bio_html',
                'industry_tags',
                'skills',
                'interests',
                'target_regions',
                'target_business_categories',
                'hobbies_interests',
                'leadership_roles',
                'special_recognitions',
                'social_links',
                'is_sponsored_member',
                'coin_medal_rank',
                'coin_milestone_title',
                'coin_milestone_meaning',
                'contribution_award_name',
                'contribution_award_recognition',
            ])
            ->with([
                'city:id,name',
                'activeCircle:id,name',
                'circleMembers' => function ($query) {
                    $query->select(['id', 'user_id', 'circle_id', 'role', 'status', 'deleted_at'])
                        ->whereNull('deleted_at')
                        ->with('circle:id,name');
                },
            ])
            ->orderByDesc('created_at')
            ->get();

        $items = $members->map(function (User $member): array {
            $fullName = trim((string) $member->first_name . ' ' . (string) $member->last_name);
            $name = $member->display_name ?: ($fullName !== '' ? $fullName : $member->email);

            $cityName = $member->city?->name ?? $member->getAttribute('city');
            $membershipStatus = $member->membership_status;
            $profilePhotoId = $member->profile_photo_file_id;
            $coverPhotoId = $member->cover_photo_file_id;

            $circles = $member->circleMembers
                ->map(function ($circleMember): array {
                    return [
                        'circle_member_id' => $circleMember->id,
                        'circle_id' => $circleMember->circle_id,
                        'circle_name' => $circleMember->circle?->name,
                        'role' => $circleMember->role,
                        'status' => $circleMember->status,
                    ];
                })
                ->values();

            return [
                'id' => $member->id,
                'public_profile_slug' => $member->public_profile_slug,
                'profile_photo_id' => $profilePhotoId,
                'cover_photo_id' => $coverPhotoId,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'display_name' => $member->display_name,
                'name' => $name,
                'company_name' => $member->company_name,
                'designation' => $member->designation,
                'email' => $member->email,
                'phone' => $member->phone,
                'city' => $cityName,
                'city_id' => $member->city_id,
                'city_name' => $cityName,
                'country_name' => null,
                'membership_status' => $membershipStatus,
                'membership_expiry' => $member->membership_expiry,
                'membership_status_label' => $this->membershipStatusLabel($membershipStatus),
                'membership_starts_at' => $member->membership_starts_at,
                'membership_ends_at' => $member->membership_ends_at,
                'zoho_plan_code' => $member->zoho_plan_code,
                'zoho_last_invoice_id' => $member->zoho_last_invoice_id,
                'active_circle_id' => $member->active_circle_id,
                'active_circle_addon_code' => $member->active_circle_addon_code,
                'active_circle_addon_name' => $member->active_circle_addon_name,
                'circle_joined_at' => $member->circle_joined_at,
                'circle_expires_at' => $member->circle_expires_at,
                'active_circle_subscription_id' => $member->active_circle_subscription_id,
                'active_circle' => $member->activeCircle
                    ? [
                        'id' => $member->activeCircle->id,
                        'name' => $member->activeCircle->name,
                    ]
                    : null,
                'circles_count' => $circles->count(),
                'circles' => $circles,
                'circle_memberships' => $circles,
                'coins_balance' => $member->coins_balance,
                'business_type' => $member->business_type,
                'turnover_range' => $member->turnover_range,
                'gender' => $member->gender,
                'dob' => optional($member->dob)?->format('Y-m-d'),
                'experience_years' => $member->experience_years,
                'experience_summary' => $member->experience_summary,
                'bio' => $member->short_bio,
                'long_bio_html' => $member->long_bio_html,
                'industry_tags' => $member->industry_tags ?? [],
                'skills' => $member->skills ?? [],
                'interests' => $member->interests ?? [],
                'target_regions' => $member->target_regions ?? [],
                'target_business_categories' => $member->target_business_categories ?? [],
                'hobbies_interests' => $member->hobbies_interests ?? [],
                'leadership_roles' => $member->leadership_roles ?? [],
                'special_recognitions' => $member->special_recognitions ?? [],
                'social_links' => $member->social_links,
                'profile_photo_url' => $profilePhotoId ? url('/api/v1/files/' . $profilePhotoId) : null,
                'profile_image_url' => $profilePhotoId ? url('/api/v1/files/' . $profilePhotoId) : null,
                'cover_photo_url' => $coverPhotoId ? url('/api/v1/files/' . $coverPhotoId) : null,
                'address' => null,
                'state' => null,
                'country' => null,
                'pincode' => null,
                'is_verified' => null,
                'is_sponsored_member' => (bool) $member->is_sponsored_member,
                'last_login_at' => $member->last_login_at,
                'status' => $member->status,
                'created_at' => $member->created_at,
                'updated_at' => $member->updated_at,
                'medal_rank' => $member->coin_medal_rank,
                'title' => $member->coin_milestone_title,
                'meaning_and_vibe' => $member->coin_milestone_meaning,
                'contribution_award_name' => $member->contribution_award_name,
                'contribution_recognition' => $member->contribution_award_recognition,
            ];
        })->values();

        return $this->success([
            'items' => $items,
        ]);
    }

    private function membershipStatusLabel(?string $status): ?string
    {
        if ($status === null || trim($status) === '') {
            return null;
        }

        return match ($status) {
            User::STATUS_FREE => 'Free Peer',
            User::STATUS_FREE_TRIAL => 'Free Trial Peer',
            default => Str::of($status)
                ->replace(['-', '_'], ' ')
                ->title()
                ->toString(),
        };
    }
}

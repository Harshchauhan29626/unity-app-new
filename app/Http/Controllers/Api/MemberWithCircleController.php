<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MemberWithCircleController extends BaseApiController
{
    public function index()
    {
        $members = User::query()
            ->select([
                'users.id',
                'users.display_name',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.public_profile_slug',
            ])
            ->orderByDesc('created_at')
            ->get();

        $items = $members
            ->map(fn (User $member): array => $this->transformListMember($member))
            ->values();

        return $this->success([
            'items' => $items,
        ]);
    }

    public function show(string $identifier)
    {
        $availableOptionalColumns = $this->availableOptionalColumns();
        $identifier = trim($identifier);

        $query = $this->baseMemberQuery($availableOptionalColumns);
        $user = null;

        if (Str::isUuid($identifier)) {
            $user = (clone $query)->where('users.id', $identifier)->first();
        }

        if (! $user) {
            $user = (clone $query)
                ->whereRaw('LOWER(users.public_profile_slug) = ?', [Str::lower($identifier)])
                ->first();
        }

        if (! $user) {
            return $this->error('Member not found.', 404);
        }

        return $this->success($this->transformMember($user, $availableOptionalColumns));
    }

    private function baseMemberQuery(array $availableOptionalColumns): Builder
    {
        return User::query()
            ->select(array_merge(
                array_map(fn (string $column): string => 'users.' . $column, $this->baseColumns()),
                array_map(fn (string $column): string => 'users.' . $column, $availableOptionalColumns)
            ))
            ->with([
                'city:id,name',
                'activeCircle:id,name',
                'circleMembers' => function ($query) {
                    $query->select(['id', 'user_id', 'circle_id', 'role', 'status', 'deleted_at'])
                        ->whereNull('deleted_at')
                        ->with('circle:id,name');
                },
            ]);
    }

    private function baseColumns(): array
    {
        return [
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
        ];
    }

    private function optionalColumns(): array
    {
        return [
            'updated_at',
            'profile_photo_file_id',
            'profile_photo_url',
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
            'address',
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
        ];
    }

    private function availableOptionalColumns(): array
    {
        return array_values(array_filter(
            $this->optionalColumns(),
            fn (string $column): bool => Schema::hasColumn('users', $column)
        ));
    }

    private function transformMember(User $member, array $availableOptionalColumns): array
    {
        $fullName = trim((string) $member->first_name . ' ' . (string) $member->last_name);
        $name = $member->display_name ?: ($fullName !== '' ? $fullName : $member->email);

        $cityName = $member->city?->name ?? $member->getAttribute('city');
        $membershipStatus = $member->membership_status;
        $profilePhotoId = $this->optionalValue($member, 'profile_photo_file_id', $availableOptionalColumns);
        $legacyProfilePhotoUrl = $this->optionalValue($member, 'profile_photo_url', $availableOptionalColumns);
        $coverPhotoId = $this->optionalValue($member, 'cover_photo_file_id', $availableOptionalColumns);
        $socialMedia = $this->optionalValue($member, 'social_links', $availableOptionalColumns);
        $businessDescription = $this->optionalValue($member, 'short_bio', $availableOptionalColumns)
            ?: $this->optionalValue($member, 'experience_summary', $availableOptionalColumns);
        $photoUrl = $profilePhotoId
            ? url('/api/v1/files/' . $profilePhotoId)
            : $legacyProfilePhotoUrl;

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
            'mobile' => $member->phone,
            'photo' => $photoUrl,
            'company_name' => $member->company_name,
            'company' => $member->company_name,
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
            'membership_starts_at' => $this->optionalValue($member, 'membership_starts_at', $availableOptionalColumns),
            'membership_ends_at' => $this->optionalValue($member, 'membership_ends_at', $availableOptionalColumns),
            'zoho_plan_code' => $this->optionalValue($member, 'zoho_plan_code', $availableOptionalColumns),
            'zoho_last_invoice_id' => $this->optionalValue($member, 'zoho_last_invoice_id', $availableOptionalColumns),
            'active_circle_id' => $this->optionalValue($member, 'active_circle_id', $availableOptionalColumns),
            'active_circle_addon_code' => $this->optionalValue($member, 'active_circle_addon_code', $availableOptionalColumns),
            'active_circle_addon_name' => $this->optionalValue($member, 'active_circle_addon_name', $availableOptionalColumns),
            'circle_joined_at' => $this->optionalValue($member, 'circle_joined_at', $availableOptionalColumns),
            'circle_expires_at' => $this->optionalValue($member, 'circle_expires_at', $availableOptionalColumns),
            'active_circle_subscription_id' => $this->optionalValue($member, 'active_circle_subscription_id', $availableOptionalColumns),
            'active_circle' => $member->activeCircle && $this->optionalValue($member, 'active_circle_id', $availableOptionalColumns)
                ? [
                    'id' => $member->activeCircle->id,
                    'name' => $member->activeCircle->name,
                ]
                : null,
            'active_circle_name' => $member->activeCircle?->name,
            'circles_count' => $circles->count(),
            'circles' => $circles,
            'circle_memberships' => $circles,
            'coins_balance' => $member->coins_balance,
            'business_type' => $this->optionalValue($member, 'business_type', $availableOptionalColumns),
            'turnover_range' => $this->optionalValue($member, 'turnover_range', $availableOptionalColumns),
            'gender' => $this->optionalValue($member, 'gender', $availableOptionalColumns),
            'dob' => optional($this->optionalValue($member, 'dob', $availableOptionalColumns))?->format('Y-m-d'),
            'experience_years' => $this->optionalValue($member, 'experience_years', $availableOptionalColumns),
            'experience_summary' => $this->optionalValue($member, 'experience_summary', $availableOptionalColumns),
            'bio' => $this->optionalValue($member, 'short_bio', $availableOptionalColumns),
            'business_description' => $businessDescription,
            'long_bio_html' => $this->optionalValue($member, 'long_bio_html', $availableOptionalColumns),
            'industry_tags' => $this->optionalValue($member, 'industry_tags', $availableOptionalColumns) ?? [],
            'skills' => $this->optionalValue($member, 'skills', $availableOptionalColumns) ?? [],
            'interests' => $this->optionalValue($member, 'interests', $availableOptionalColumns) ?? [],
            'target_regions' => $this->optionalValue($member, 'target_regions', $availableOptionalColumns) ?? [],
            'target_business_categories' => $this->optionalValue($member, 'target_business_categories', $availableOptionalColumns) ?? [],
            'hobbies_interests' => $this->optionalValue($member, 'hobbies_interests', $availableOptionalColumns) ?? [],
            'leadership_roles' => $this->optionalValue($member, 'leadership_roles', $availableOptionalColumns) ?? [],
            'special_recognitions' => $this->optionalValue($member, 'special_recognitions', $availableOptionalColumns) ?? [],
            'social_links' => $socialMedia,
            'social_media' => $socialMedia,
            'website' => $this->extractWebsite($socialMedia),
            'profile_photo_url' => $photoUrl,
            'profile_image_url' => $photoUrl,
            'cover_photo_url' => $coverPhotoId ? url('/api/v1/files/' . $coverPhotoId) : null,
            'address' => $this->optionalValue($member, 'address', $availableOptionalColumns),
            'state' => null,
            'country' => null,
            'pincode' => null,
            'is_verified' => null,
            'is_sponsored_member' => (bool) ($this->optionalValue($member, 'is_sponsored_member', $availableOptionalColumns) ?? false),
            'last_login_at' => $member->last_login_at,
            'status' => $member->status,
            'created_at' => $member->created_at,
            'updated_at' => $this->optionalValue($member, 'updated_at', $availableOptionalColumns),
            'medal_rank' => $this->optionalValue($member, 'coin_medal_rank', $availableOptionalColumns),
            'title' => $this->optionalValue($member, 'coin_milestone_title', $availableOptionalColumns),
            'meaning_and_vibe' => $this->optionalValue($member, 'coin_milestone_meaning', $availableOptionalColumns),
            'contribution_award_name' => $this->optionalValue($member, 'contribution_award_name', $availableOptionalColumns),
            'contribution_recognition' => $this->optionalValue($member, 'contribution_award_recognition', $availableOptionalColumns),
        ];
    }

    private function transformListMember(User $member): array
    {
        $displayName = trim((string) ($member->display_name ?? ''));
        $fullName = trim(trim((string) ($member->first_name ?? '')) . ' ' . trim((string) ($member->last_name ?? '')));

        return [
            'id' => $member->id,
            'name' => $displayName !== ''
                ? $displayName
                : ($fullName !== '' ? $fullName : $member->email),
            'slug' => $member->public_profile_slug,
        ];
    }

    private function optionalValue(User $member, string $field, array $availableOptionalColumns): mixed
    {
        if (! in_array($field, $availableOptionalColumns, true)) {
            return null;
        }

        return $member->getAttribute($field);
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

    private function extractWebsite(mixed $socialMedia): ?string
    {
        if (is_array($socialMedia)) {
            $website = $socialMedia['website'] ?? null;

            return is_string($website) && trim($website) !== '' ? $website : null;
        }

        return null;
    }
}

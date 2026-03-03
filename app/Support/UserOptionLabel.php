<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class UserOptionLabel
{
    public static function make(User $user): string
    {
        $name = self::normalize($user->name ?? $user->display_name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? '')))
            ?: 'Unknown';

        $company = self::normalize($user->company_name ?? $user->company ?? $user->business_name ?? '') ?: 'No Company';
        $city = self::normalize($user->city ?? '') ?: 'No City';

        $circles = self::circleNamesForUser($user);
        $circleText = $circles->isEmpty() ? 'No Circle' : $circles->implode(' | ');

        return "{$name}, {$company}, {$city}; {$circleText}";
    }

    public static function makeFromRow(array $row): string
    {
        $name = self::normalize(
            $row['name']
                ?? $row['display_name']
                ?? trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''))
        ) ?: 'Unknown';

        $company = self::normalize($row['company_name'] ?? $row['company'] ?? $row['business_name'] ?? '') ?: 'No Company';
        $city = self::normalize($row['city'] ?? '') ?: 'No City';

        $circleNames = self::normalize($row['circles'] ?? '');
        $circleText = $circleNames !== '' ? $circleNames : 'No Circle';

        return "{$name}, {$company}, {$city}; {$circleText}";
    }

    private static function circleNamesForUser(User $user): Collection
    {
        if ($user->relationLoaded('circles')) {
            return $user->circles
                ->pluck('name')
                ->map(fn ($name) => self::normalize($name))
                ->filter()
                ->values();
        }

        if ($user->relationLoaded('circleMembers')) {
            return $user->circleMembers
                ->map(fn ($member) => self::normalize(optional($member->circle)->name))
                ->filter()
                ->values();
        }

        return $user->circles()
            ->pluck('name')
            ->map(fn ($name) => self::normalize($name))
            ->filter()
            ->values();
    }

    private static function normalize(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}

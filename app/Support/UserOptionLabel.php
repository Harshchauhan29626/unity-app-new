<?php

namespace App\Support;

use App\Models\User;

class UserOptionLabel
{
    public static function make(User $user): string
    {
        $name = self::normalize(
            $user->name
                ?? $user->display_name
                ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''))
        ) ?: 'Unknown';

        $company = self::normalize($user->company_name ?? $user->company ?? '') ?: 'No Company';
        $city = self::normalize($user->city ?? '') ?: 'No City';

        $lastCircle = $user->circles()
            ->orderByDesc('circle_members.created_at')
            ->limit(1)
            ->pluck('circles.name')
            ->first();

        $circle = self::normalize($lastCircle) ?: 'No Circle';

        return "{$name}, {$company}, {$city}, {$circle}";
    }

    public static function makeFromRow(array $row): string
    {
        $name = self::normalize(
            $row['name']
                ?? $row['display_name']
                ?? trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''))
        ) ?: 'Unknown';

        $company = self::normalize($row['company_name'] ?? $row['company'] ?? '') ?: 'No Company';
        $city = self::normalize($row['city'] ?? '') ?: 'No City';
        $circle = self::normalize($row['circle'] ?? '') ?: 'No Circle';

        return "{$name}, {$company}, {$city}, {$circle}";
    }

    private static function normalize(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}

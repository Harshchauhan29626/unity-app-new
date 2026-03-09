<?php

namespace App\Support\CoinClaims;

use Carbon\Carbon;

class CoinClaimKeyFieldsFormatter
{
    public static function format(string $activityCode, array $payload): string
    {
        $registry = app(CoinClaimActivityRegistry::class);
        $activity = $registry->get($activityCode);

        if (! $activity) {
            return '-';
        }

        $fieldsPayload = (array) ($payload['fields'] ?? []);
        $filesPayload = (array) ($payload['files'] ?? []);
        $parts = [];

        foreach ((array) ($activity['fields'] ?? []) as $fieldDefinition) {
            $key = (string) ($fieldDefinition['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $label = (string) ($fieldDefinition['label'] ?? $key);
            $type = (string) ($fieldDefinition['type'] ?? 'text');

            if ($type === 'file') {
                $hasFile = ! empty($filesPayload[$key]) || ! empty($fieldsPayload[$key]);
                $parts[] = $label . ': ' . ($hasFile ? 'Attached' : 'Not attached');
                continue;
            }

            $value = $fieldsPayload[$key] ?? null;
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $parts[] = $label . ': ' . self::formatValueByType($type, $value);
        }

        return $parts !== [] ? implode(' • ', $parts) : '-';
    }

    private static function formatValueByType(string $type, mixed $value): string
    {
        if ($type === 'date') {
            try {
                return Carbon::parse((string) $value)->format('d M Y');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}

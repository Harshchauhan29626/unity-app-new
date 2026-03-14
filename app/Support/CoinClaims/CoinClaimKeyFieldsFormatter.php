<?php

namespace App\Support\CoinClaims;

use Carbon\Carbon;

class CoinClaimKeyFieldsFormatter
{
    private const ADMIN_HIDDEN_KEYS = [
        'new_member_mobile_normalized',
        'mobile_normalized',
        'phone_normalized',
        'normalized_mobile',
    ];

    private const ADMIN_LABELS = [
        'joining_date' => 'Joining Date',
        'new_member_name' => 'New Member Name',
        'new_member_email' => 'New Member Email',
        'new_member_mobile' => 'New Member Mobile',
        'peer_name' => 'Peer Name',
        'company_name' => 'Company Name',
        'city_name' => 'City',
        'amount' => 'Amount',
        'note' => 'Note',
        'description' => 'Description',
    ];

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

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public static function formatForAdminList(mixed $keyFields): array
    {
        $normalized = self::normalizeKeyFields($keyFields);

        if ($normalized === null) {
            $plainValue = trim((string) $keyFields);

            return $plainValue === ''
                ? []
                : [['label' => 'Details', 'value' => $plainValue]];
        }

        $rows = [];

        foreach ($normalized as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));

            if (in_array($normalizedKey, self::ADMIN_HIDDEN_KEYS, true)) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $stringValue = trim(self::stringifyValue($value));

            if ($stringValue === '') {
                continue;
            }

            $rows[] = [
                'label' => self::humanizeKey((string) $key),
                'value' => self::formatAdminValue((string) $key, $stringValue),
            ];
        }

        return $rows;
    }

    private static function normalizeKeyFields(mixed $keyFields): ?array
    {
        if (is_array($keyFields)) {
            return $keyFields;
        }

        if (is_string($keyFields)) {
            $trimmed = trim($keyFields);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return self::parseKeyValueString($trimmed);
        }

        return null;
    }

    private static function parseKeyValueString(string $keyFields): ?array
    {
        if (! str_contains($keyFields, ':')) {
            return null;
        }

        $result = [];

        foreach (preg_split('/\s*,\s*/', $keyFields) ?: [] as $pair) {
            if (! str_contains($pair, ':')) {
                continue;
            }

            [$key, $value] = array_pad(explode(':', $pair, 2), 2, null);
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $result[$key] = trim((string) $value);
        }

        return $result === [] ? null : $result;
    }

    private static function humanizeKey(string $key): string
    {
        $normalized = strtolower(trim($key));

        if ($normalized === '') {
            return 'Details';
        }

        if (array_key_exists($normalized, self::ADMIN_LABELS)) {
            return self::ADMIN_LABELS[$normalized];
        }

        return (string) str($normalized)->replace(['-', '_'], ' ')->title();
    }


    private static function formatAdminValue(string $key, string $value): string
    {
        if (str_ends_with(strtolower($key), '_date')) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private static function stringifyValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

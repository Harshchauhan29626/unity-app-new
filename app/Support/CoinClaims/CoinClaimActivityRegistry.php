<?php

namespace App\Support\CoinClaims;

class CoinClaimActivityRegistry
{
    public function all(): array
    {
        return config('coin_claims.activities', []);
    }

    public function has(string $code): bool
    {
        return array_key_exists($code, $this->all());
    }

    public function get(string $code): ?array
    {
        return $this->all()[$code] ?? null;
    }

    public function listForApi(): array
    {
        $items = [];

        foreach ($this->all() as $code => $definition) {
            $items[] = array_merge(['code' => $code], $definition);
        }

        return $items;
    }

    public function fieldMap(string $activityCode): array
    {
        $activity = $this->get($activityCode);

        if (! $activity) {
            return [];
        }

        $map = [];

        foreach (($activity['fields'] ?? []) as $field) {
            if (! empty($field['key'])) {
                $map[$field['key']] = $field;
            }
        }

        return $map;
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoinClaimActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => (string) ($this['code'] ?? ''),
            'label' => (string) ($this['label'] ?? ''),
            'coins' => (int) ($this['coins'] ?? 0),
            'fields' => array_map(static function (array $field): array {
                return [
                    'key' => (string) ($field['key'] ?? ''),
                    'label' => (string) ($field['label'] ?? ''),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'required' => (bool) ($field['required'] ?? false),
                ];
            }, $this['fields'] ?? []),
        ];
    }
}

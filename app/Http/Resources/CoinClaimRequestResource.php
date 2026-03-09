<?php

namespace App\Http\Resources;

use App\Support\CoinClaims\CoinClaimActivityRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoinClaimRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registry = app(CoinClaimActivityRegistry::class);
        $activity = $registry->get((string) $this->activity_code);

        return [
            'id' => (string) $this->id,
            'user_id' => (string) $this->user_id,
            'activity_code' => (string) $this->activity_code,
            'activity_label' => $activity['label'] ?? null,
            'coins' => (int) ($activity['coins'] ?? 0),
            'payload' => $this->payload,
            'status' => (string) $this->status,
            'coins_awarded' => $this->coins_awarded,
            'admin_note' => $this->admin_note,
            'reviewed_by_admin_id' => $this->reviewed_by_admin_id,
            'reviewed_at' => optional($this->reviewed_at)->toISOString(),
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $referredUser = $this->referredUser;

        return [
            'id' => (string) ($referredUser?->id ?? ''),
            'name' => (string) ($referredUser?->display_name ?? trim((string) (($referredUser?->first_name ?? '') . ' ' . ($referredUser?->last_name ?? '')))),
            'email' => $referredUser?->email,
            'business_name' => $referredUser?->company_name,
            'position' => $referredUser?->designation,
            'registered_at' => optional($referredUser?->created_at)->toISOString(),
            'referral_code' => $this->referral_code,
            'coins' => (int) ($this->coins ?? 0),
            'reward_status' => (string) ($this->reward_status ?? 'pending'),
        ];
    }
}

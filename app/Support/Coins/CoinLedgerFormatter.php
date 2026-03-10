<?php

namespace App\Support\Coins;

class CoinLedgerFormatter
{
    public static function why(?string $type): string
    {
        return match (trim((string) $type)) {
            'testimonial' => 'Testimonial',
            'referral' => 'Referral',
            'business_deal' => 'Business Deal',
            'p2p_meeting' => 'P2P Meeting',
            'requirement' => 'Requirement',
            default => 'Admin Adjustment',
        };
    }
}

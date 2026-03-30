<?php

namespace App\Services\Referrals;

use Illuminate\Support\Facades\DB;

class ReferralCodeService
{
    public function sanitizeNamePrefix(string $name, int $maxLength = 6): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $name));
        $trimmed = substr($clean, 0, max(1, min($maxLength, 10)));

        return $trimmed !== '' ? $trimmed : 'PEER';
    }

    public function generateUniqueCode(string $name): string
    {
        $prefix = $this->sanitizeNamePrefix($name, 6);
        $attempts = 0;

        do {
            $attempts++;
            $code = $prefix . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $exists = DB::table('referral_links')->where('referral_code', $code)->exists();
        } while ($exists && $attempts < 50);

        if ($exists) {
            $code = 'PEER' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    public function buildReferralLink(string $code): string
    {
        $base = (string) config('referrals.register_url', rtrim((string) config('app.url'), '/') . '/register');
        $param = (string) config('referrals.query_param', 'ref');

        return rtrim($base, '?&') . '?' . http_build_query([$param => $code]);
    }
}

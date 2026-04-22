<?php

namespace App\Services\Referrals;

use Illuminate\Support\Facades\DB;

class ReferralCodeService
{
    public function generateUniqueCode(string $name = '', ?string $codeColumn = null): string
    {
        $attempts = 0;
        $column = $codeColumn ?: 'token';

        do {
            $attempts++;
            $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));
            $exists = DB::table('referral_links')->where($column, $code)->exists();
        } while ($exists && $attempts < 50);

        if ($exists) {
            throw new \RuntimeException('Unable to generate a unique referral token.');
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

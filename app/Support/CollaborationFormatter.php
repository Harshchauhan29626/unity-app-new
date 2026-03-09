<?php

namespace App\Support;

use Illuminate\Support\Str;

class CollaborationFormatter
{
    public static function humanize(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        $v = trim($value);

        $map = [
            'startup_under_10l' => 'Startup (Under 10L)',
            'growing_10l_1cr' => 'Growing (10L–1Cr)',
            'above_1cr' => 'Above 1Cr',
            '0_3_years' => '0–3 Years',
            '3_7_years' => '3–7 Years',
            '7_plus_years' => '7+ Years',
            'lt_1_year' => 'Less Than 1 Year',
            '1_3_years' => '1–3 Years',
            '7_plus' => '7+ Years',
            'profit_sharing' => 'Profit Sharing',
            'revenue_share' => 'Revenue Share',
            'commission_based' => 'Commission Based',
            'equity' => 'Equity',
            'joint_venture' => 'Joint Venture',
            'fixed_fee' => 'Fixed Fee',
            'fixed_contract' => 'Fixed Contract',
            'open_for_discussion' => 'Open For Discussion',
            'local' => 'Local',
            'same_city' => 'Same City',
            'same_state' => 'Same State',
            'same_country' => 'Same Country',
            'national' => 'National',
            'international' => 'International',
            'active' => 'Active',
            'deleted' => 'Deleted',
            'inactive' => 'Inactive',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];

        if (isset($map[$v])) {
            return $map[$v];
        }

        $v = str_replace(['-', '_'], ' ', $v);
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;

        return Str::title($v);
    }
}

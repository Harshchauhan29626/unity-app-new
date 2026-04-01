<?php

return [
    'register_url' => env('REFERRAL_REGISTER_URL', rtrim((string) env('APP_URL', 'https://peersunity.com'), '/') . '/register'),
    'query_param' => env('REFERRAL_QUERY_PARAM', 'ref'),
];


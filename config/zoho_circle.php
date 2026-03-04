<?php

return [
    'circle_addon_prefix' => env('ZOHO_CIRCLE_ADDON_PREFIX', 'CIRCLE_'),
    'hostedpage_redirect_url_success' => env('ZOHO_CIRCLE_REDIRECT_SUCCESS', env('APP_URL') . '/circle/payment/success'),
    'hostedpage_redirect_url_cancel' => env('ZOHO_CIRCLE_REDIRECT_CANCEL', env('APP_URL') . '/circle/payment/cancel'),
    'webhook_secret' => env('ZOHO_CIRCLE_WEBHOOK_SECRET', ''),
    'webhook_token' => env('ZOHO_CIRCLE_WEBHOOK_TOKEN', ''),
    'product_id' => env('ZOHO_CIRCLE_PRODUCT_ID', ''),
];

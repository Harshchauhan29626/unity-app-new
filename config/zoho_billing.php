<?php

return [
    'region' => env('ZOHO_REGION', 'in'),
    'api_domain' => env('ZOHO_API_DOMAIN', 'https://www.zohoapis.in'),
    'base_url' => env('ZOHO_BILLING_BASE_URL', 'https://subscriptions.zoho.in/api/v1'),
    'org_id' => env('ZOHO_ORG_ID'),
    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
    'redirect_uri' => env('ZOHO_REDIRECT_URI'),
    'oauth_token_url' => env('ZOHO_OAUTH_TOKEN_URL', 'https://accounts.zoho.in/oauth/v2/token'),
    'webhook_secret' => env('ZOHO_WEBHOOK_SECRET'),
    'product_id' => env('ZOHO_BILLING_PRODUCT_ID', ''),
    'addon_code_start' => (int) env('ZOHO_BILLING_ADDON_CODE_START', 10),
    'addon_code_min_width' => (int) env('ZOHO_BILLING_ADDON_CODE_MIN_WIDTH', 2),
    'circle_base_plan_code' => env('ZOHO_CIRCLE_BASE_PLAN_CODE', ''),
    'portal_demo_email_prefix' => env('ZOHO_PORTAL_DEMO_EMAIL_PREFIX', 'demo'),
    'http_timeout' => (int) env('ZOHO_HTTP_TIMEOUT', 20),
    'http_retry_times' => (int) env('ZOHO_HTTP_RETRY_TIMES', 2),
    'http_retry_sleep_ms' => (int) env('ZOHO_HTTP_RETRY_SLEEP_MS', 200),
];

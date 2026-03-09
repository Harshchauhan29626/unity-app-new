<?php

namespace App\Support\Zoho;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoBillingTokenService
{
    public function getAccessToken(): string
    {
        $cachedToken = Cache::get('zoho_billing_access_token');

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $tokenData = $this->refreshAccessToken();
        $ttl = max(((int) ($tokenData['expires_in'] ?? 3600)) - 120, 60);

        Cache::put('zoho_billing_access_token', $tokenData['access_token'], now()->addSeconds($ttl));

        return $tokenData['access_token'];
    }

    public function refreshAccessToken(): array
    {
        $url = config('zoho_billing.oauth_token_url');

        try {
            $response = Http::asForm()
                ->timeout(config('zoho_billing.http_timeout', 20))
                ->retry(config('zoho_billing.http_retry_times', 2), config('zoho_billing.http_retry_sleep_ms', 200))
                ->post($url, [
                    'refresh_token' => config('zoho_billing.refresh_token'),
                    'client_id' => config('zoho_billing.client_id'),
                    'client_secret' => config('zoho_billing.client_secret'),
                    'redirect_uri' => config('zoho_billing.redirect_uri'),
                    'grant_type' => 'refresh_token',
                ])
                ->throw();
        } catch (RequestException $exception) {
            Log::error('Zoho token refresh failed', [
                'status' => optional($exception->response)->status(),
                'body' => optional($exception->response)->json() ?? optional($exception->response)->body(),
            ]);

            throw new RuntimeException('Unable to refresh Zoho access token.');
        }

        $json = $response->json();

        if (! is_array($json) || empty($json['access_token'])) {
            Log::error('Zoho token refresh returned invalid payload', [
                'payload_keys' => is_array($json) ? array_keys($json) : null,
            ]);

            throw new RuntimeException('Zoho token refresh response is invalid.');
        }

        return $json;
    }

    public function tokenMeta(): array
    {
        $cachedToken = Cache::get('zoho_billing_access_token');

        return [
            'has_cached_token' => (bool) $cachedToken,
            'cached_token_length' => $cachedToken ? strlen((string) $cachedToken) : 0,
            'org_id' => config('zoho_billing.org_id'),
            'base_url' => config('zoho_billing.base_url'),
            'token_endpoint' => config('zoho_billing.oauth_token_url'),
        ];
    }
}

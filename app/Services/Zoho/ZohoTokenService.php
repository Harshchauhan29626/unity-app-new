<?php

namespace App\Services\Zoho;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoTokenService
{
    public function getAccessToken(): string
    {
        $cached = Cache::get('zoho_circle_access_token');

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenData = $this->refreshToken();
        $expiresIn = max(((int) ($tokenData['expires_in'] ?? 3600)) - 120, 60);

        Cache::put('zoho_circle_access_token', $tokenData['access_token'], now()->addSeconds($expiresIn));

        return (string) $tokenData['access_token'];
    }

    public function refreshToken(): array
    {
        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post('https://accounts.zoho.in/oauth/v2/token', [
                    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
                    'client_id' => env('ZOHO_CLIENT_ID'),
                    'client_secret' => env('ZOHO_CLIENT_SECRET'),
                    'grant_type' => 'refresh_token',
                ])
                ->throw();
        } catch (RequestException $exception) {
            Log::error('zoho api error', [
                'context' => 'refresh_token',
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json() ?? $exception->response?->body(),
            ]);

            throw new RuntimeException('Unable to refresh Zoho access token.');
        }

        $json = $response->json();

        if (! is_array($json) || empty($json['access_token'])) {
            Log::error('zoho api error', [
                'context' => 'refresh_token_invalid_payload',
                'payload' => $json,
            ]);

            throw new RuntimeException('Invalid Zoho token response payload.');
        }

        return $json;
    }
}

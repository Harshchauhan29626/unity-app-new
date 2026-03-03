<?php

namespace App\Support\Zoho;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoBillingClient
{
    public function __construct(private readonly ZohoBillingTokenService $tokenService)
    {
    }

    public function request(string $method, string $path, array $payload = [], bool $asQuery = false): array
    {
        $url = rtrim((string) config('zoho_billing.base_url'), '/') . '/' . ltrim($path, '/');
        $token = $this->tokenService->getAccessToken();

        $request = Http::timeout(config('zoho_billing.http_timeout', 20))
            ->retry(config('zoho_billing.http_retry_times', 2), config('zoho_billing.http_retry_sleep_ms', 200))
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'X-com-zoho-subscriptions-organizationid' => (string) config('zoho_billing.org_id'),
            ]);

        Log::info('Zoho Billing request', [
            'method' => strtoupper($method),
            'path' => $path,
            'final_url' => $url,
            'query_keys' => $asQuery ? array_keys($payload) : [],
            'body_keys' => $asQuery ? [] : array_keys($payload),
        ]);

        try {
            $response = $asQuery
                ? $request->send(strtoupper($method), $url, ['query' => $payload])
                : $request->send(strtoupper($method), $url, ['json' => $payload]);

            if (! $response->successful()) {
                if ($path === '/hostedpages/newsubscription') {
                    $body = $response->json() ?? $response->body();

                    Log::error('ZOHO_NEW_SUBSCRIPTION_FAILED', [
                        'status' => $response->status(),
                        'body' => $body,
                        'payload' => $payload,
                    ]);

                    $message = (string) (data_get($body, 'message') ?? data_get($body, 'error.message') ?? (is_string($body) ? $body : 'Zoho API request failed.'));
                    throw new RuntimeException('Zoho newsubscription failed: ' . $message, $response->status());
                }

                $this->throwZohoException($response->status(), $response->json(), $response->body());
            }

            return $response->json() ?? [];
        } catch (RequestException $exception) {
            $json = optional($exception->response)->json();
            $status = optional($exception->response)->status() ?? 500;
            $body = optional($exception->response)->body();

            if ($path === '/hostedpages/newsubscription') {
                $failedBody = $json ?? $body;

                Log::error('ZOHO_NEW_SUBSCRIPTION_FAILED', [
                    'status' => optional($exception->response)->status(),
                    'body' => $failedBody,
                    'payload' => $payload,
                ]);

                $message = (string) (data_get($failedBody, 'message') ?? data_get($failedBody, 'error.message') ?? (is_string($failedBody) ? $failedBody : 'Zoho API request failed.'));
                throw new RuntimeException('Zoho newsubscription failed: ' . $message, $status, $exception);
            }

            $this->throwZohoException($status, $json, $body);
        }
    }

    private function throwZohoException(int $status, mixed $json, ?string $body = null): void
    {
        $code = data_get($json, 'code');
        $message = (string) (data_get($json, 'message') ?? data_get($json, 'error.message') ?? 'Zoho API request failed.');

        Log::error('Zoho API request failed', [
            'status' => $status,
            'zoho_code' => $code,
            'message' => $message,
            'response' => $json ?? $body,
        ]);

        $formattedCode = $code ? ' code ' . $code : '';
        throw new RuntimeException('Zoho API request failed' . $formattedCode . ': ' . $message, $status);
    }
}

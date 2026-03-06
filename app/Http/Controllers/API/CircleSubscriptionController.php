<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\ZohoCircleAddon;
use App\Services\Zoho\ZohoTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CircleSubscriptionController extends Controller
{
    public function __construct(private readonly ZohoTokenService $tokenService)
    {
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'circle_id' => ['required', 'uuid', 'exists:circles,id'],
            'interval_type' => ['required', 'in:monthly,quarterly,half_yearly,yearly'],
        ]);

        $addon = ZohoCircleAddon::query()
            ->where('circle_id', $validated['circle_id'])
            ->where('interval_type', $validated['interval_type'])
            ->first();

        if (! $addon) {
            return response()->json([
                'message' => 'No addon available for selected interval.',
            ], 404);
        }

        $baseUrl = rtrim((string) env('ZOHO_BILLING_BASE_URL'), '/');

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => 'Zoho-oauthtoken ' . $this->tokenService->getAccessToken(),
                    'X-com-zoho-subscriptions-organizationid' => (string) env('ZOHO_BILLING_ORG_ID'),
                ])
                ->post($baseUrl . '/hostedpages/newsubscription', [
                    'plan_code' => env('ZOHO_CIRCLE_BASE_PLAN_CODE'),
                    'addons' => [
                        [
                            'addon_code' => $addon->zoho_addon_code,
                        ],
                    ],
                ])
                ->throw();

            $checkoutUrl = (string) data_get($response->json(), 'hostedpage.url', '');

            if ($checkoutUrl === '') {
                return response()->json([
                    'message' => 'Unable to generate checkout URL.',
                ], 422);
            }

            $addon->checkout_url = $checkoutUrl;
            $addon->save();

            Log::info('checkout created', [
                'circle_id' => $validated['circle_id'],
                'interval_type' => $validated['interval_type'],
                'addon_code' => $addon->zoho_addon_code,
                'checkout_url' => $checkoutUrl,
            ]);

            return response()->json([
                'checkout_url' => $checkoutUrl,
            ]);
        } catch (RequestException $exception) {
            Log::error('zoho api error', [
                'context' => 'circle_checkout',
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json() ?? $exception->response?->body(),
            ]);

            return response()->json([
                'message' => 'Unable to create checkout at the moment.',
            ], 502);
        }
    }

    public function plans(string $circleId): JsonResponse
    {
        $circle = Circle::query()->findOrFail($circleId);

        return response()->json([
            'monthly' => $circle->price_monthly,
            'quarterly' => $circle->price_quarterly,
            'half_yearly' => $circle->price_half_yearly,
            'yearly' => $circle->price_yearly,
        ]);
    }
}

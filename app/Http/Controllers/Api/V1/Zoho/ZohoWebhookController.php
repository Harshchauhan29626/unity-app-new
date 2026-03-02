<?php

namespace App\Http\Controllers\Api\V1\Zoho;

use App\Http\Controllers\Controller;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZohoWebhookController extends Controller
{
    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function handle(Request $request)
    {
        $secret = (string) $request->query('secret', '');

        if (! hash_equals((string) config('zoho_billing.webhook_secret'), $secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook secret.',
                'data' => [],
            ], 401);
        }

        $event = $request->all();

        try {
            $updated = $this->zohoBillingService->applyWebhookEvent($event);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully.',
                'data' => [
                    'event_type' => $event['event_type'] ?? null,
                    'membership_updated' => $updated,
                ],
            ]);
        } catch (Throwable $throwable) {
            Log::error('Zoho webhook processing failed', [
                'event_type' => $event['event_type'] ?? null,
                'message' => $throwable->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $throwable->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\MembershipSyncService;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ZohoBillingWebhookController extends Controller
{
    public function __construct(
        private readonly ZohoBillingService $zohoBillingService,
        private readonly MembershipSyncService $membershipSyncService,
    ) {
    }

    public function handle(Request $request)
    {
        if (! $this->isValidWebhook($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->all();
        $subscriptionId = data_get($payload, 'subscription.subscription_id')
            ?? data_get($payload, 'data.subscription.subscription_id')
            ?? data_get($payload, 'subscription_id');
        $invoiceId = data_get($payload, 'invoice.invoice_id')
            ?? data_get($payload, 'data.invoice.invoice_id')
            ?? data_get($payload, 'invoice_id');
        $customerId = data_get($payload, 'customer.customer_id')
            ?? data_get($payload, 'data.customer.customer_id')
            ?? data_get($payload, 'customer_id');
        $email = data_get($payload, 'customer.email')
            ?? data_get($payload, 'data.customer.email')
            ?? data_get($payload, 'email');

        $user = User::query()
            ->when($subscriptionId, fn ($q) => $q->orWhere('zoho_subscription_id', $subscriptionId))
            ->when($customerId, fn ($q) => $q->orWhere('zoho_customer_id', $customerId))
            ->when($email, fn ($q) => $q->orWhere('email', $email))
            ->first();

        if (! $user) {
            Log::warning('Zoho webhook user not found', [
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
                'email_masked' => $this->maskEmail($email),
            ]);

            return response()->json(['success' => true, 'message' => 'No matching user']);
        }

        try {
            $subscription = [];
            $invoice = [];

            if ($subscriptionId) {
                $subscriptionResp = $this->zohoBillingService->getSubscription($subscriptionId);
                $subscription = $subscriptionResp['subscription'] ?? $subscriptionResp;
            }

            if ($invoiceId) {
                $invoiceResp = $this->zohoBillingService->getInvoice($invoiceId);
                $invoice = $invoiceResp['invoice'] ?? $invoiceResp;
            } elseif ($subscriptionId) {
                $invoiceList = $this->zohoBillingService->listInvoicesBySubscription($subscriptionId);
                $invoice = ($invoiceList['invoices'][0] ?? []);
            }

            $this->membershipSyncService->syncUserMembershipFromZoho($user, [
                'subscription' => $subscription,
                'invoice' => $invoice,
            ]);

            return response()->json(['success' => true]);
        } catch (Throwable $throwable) {
            Log::error('Zoho webhook sync failed', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'invoice_id' => $invoiceId,
                'error' => $throwable->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Webhook sync failed'], 500);
        }
    }

    private function isValidWebhook(Request $request): bool
    {
        $expected = (string) env('ZOHO_WEBHOOK_TOKEN', '');

        if ($expected === '') {
            return false;
        }

        $incoming = (string) ($request->header('X-Zoho-Webhook-Signature')
            ?? $request->bearerToken()
            ?? $request->query('token')
            ?? $request->input('token')
            ?? '');

        return hash_equals($expected, $incoming);
    }

    private function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        [$name, $domain] = explode('@', $email, 2);
        return substr($name, 0, 1) . '***@' . $domain;
    }
}

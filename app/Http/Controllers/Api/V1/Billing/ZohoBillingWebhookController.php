<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\CircleSubscription;
use App\Models\User;
use App\Services\Billing\MembershipSyncService;
use App\Services\Circles\CircleJoinRequestPaymentSyncService;
use App\Support\Zoho\ZohoBillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ZohoBillingWebhookController extends Controller
{
    public function __construct(
        private readonly ZohoBillingService $zohoBillingService,
        private readonly MembershipSyncService $membershipSyncService,
        private readonly CircleJoinRequestPaymentSyncService $circleJoinRequestPaymentSyncService,
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

    public function handleCircleSubscription(Request $request)
    {
        if (! $this->isValidWebhook($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->all();

        Log::info('circle customer payment webhook received', [
            'payload' => $payload,
        ]);

        try {
            // Customer Payment module is the primary activation trigger.
            // Addon module payloads are insufficient for reliable payment activation.
            $payloadType = $this->detectCircleWebhookPayloadType($payload);

            Log::info('circle webhook payload type detected', [
                'payload_type' => $payloadType,
            ]);

            $identifiers = $this->extractCircleWebhookIdentifiers($payload);

            Log::info('circle webhook extracted identifiers', $identifiers);

            $paymentStatus = strtolower((string) ($identifiers['payment_status'] ?? ''));
            if ($paymentStatus !== '' && ! in_array($paymentStatus, ['paid', 'success', 'completed', 'payment_success'], true)) {
                Log::info('unsupported/insufficient payload for activation', [
                    'reason' => 'non-success payment status',
                    'payment_status' => $paymentStatus,
                    'payload_type' => $payloadType,
                ]);

                return response()->json(['success' => true, 'message' => 'Ignored non-success payment status']);
            }

            $resolved = $this->findPendingCircleSubscription($identifiers);
            $subscription = $resolved['subscription'];
            $matchStrategy = $resolved['strategy'];

            if (! $subscription) {
                $subscription = $this->recoverPendingSubscriptionFromHostedPageStatus($identifiers, $payloadType);

                if ($subscription) {
                    $matchStrategy = 'hosted_page_status_recovery';

                    Log::info('pending circle subscription recovered using hosted page status', [
                        'circle_subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'circle_id' => $subscription->circle_id,
                    ]);
                }
            }

            if (! $subscription) {
                Log::warning('webhook received but no match found', [
                    'payload_type' => $payloadType,
                    'identifiers' => $identifiers,
                ]);

                return response()->json(['success' => true, 'message' => 'No matching circle subscription']);
            }

            Log::info('pending circle subscription matched', [
                'match_strategy' => $matchStrategy,
                'circle_subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'circle_id' => $subscription->circle_id,
            ]);

            $incomingPaymentId = (string) ($identifiers['payment_id'] ?? '');
            if (
                $subscription->status === 'active'
                && ($incomingPaymentId === '' || (string) ($subscription->zoho_payment_id ?? '') === $incomingPaymentId)
            ) {
                Log::info('duplicate/idempotent webhook ignored', [
                    'circle_subscription_id' => $subscription->id,
                    'incoming_payment_id' => $incomingPaymentId,
                ]);

                return response()->json(['success' => true, 'message' => 'Already processed']);
            }

            $startedAt = $this->parseDateTimeValue($identifiers['paid_at'] ?? null) ?? now();
            $paidAt = $this->parseDateTimeValue($identifiers['paid_at'] ?? null) ?? now();
            $durationMonths = (int) ($subscription->circle?->circle_duration_months ?: 12);
            $expiresAt = $startedAt->copy()->addMonths(max(1, $durationMonths));

            DB::transaction(function () use ($subscription, $identifiers, $startedAt, $paidAt, $expiresAt, $payload): void {
                $lockedSubscription = CircleSubscription::query()
                    ->where('id', $subscription->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedSubscription->forceFill([
                    'status' => 'active',
                    'zoho_customer_id' => $identifiers['customer_id'] ?: $lockedSubscription->zoho_customer_id,
                    'zoho_subscription_id' => $identifiers['subscription_id'] ?: $lockedSubscription->zoho_subscription_id,
                    'zoho_payment_id' => $identifiers['payment_id'] ?: $lockedSubscription->zoho_payment_id,
                    'zoho_hosted_page_id' => $identifiers['hostedpage_id'] ?: $lockedSubscription->zoho_hosted_page_id,
                    'started_at' => $startedAt,
                    'paid_at' => $paidAt,
                    'expires_at' => $expiresAt,
                    'raw_webhook_payload' => $payload,
                ])->save();

                if (Schema::hasColumn('circle_subscriptions', 'raw_final_payload')) {
                    $lockedSubscription->forceFill([
                        'raw_final_payload' => $payload,
                    ])->save();
                }

                if (Schema::hasColumn('circle_subscriptions', 'hostedpage_id') && ($identifiers['hostedpage_id'] ?? null)) {
                    $lockedSubscription->forceFill([
                        'hostedpage_id' => $identifiers['hostedpage_id'],
                    ])->save();
                }

                if (Schema::hasColumn('circle_subscriptions', 'decrypted_hosted_page_id') && ($identifiers['decrypted_hostedpage_id'] ?? null)) {
                    $lockedSubscription->forceFill([
                        'decrypted_hosted_page_id' => $identifiers['decrypted_hostedpage_id'],
                    ])->save();
                }

                Log::info('circle subscription payment activation persisted', [
                    'circle_subscription_id' => $lockedSubscription->id,
                    'status' => $lockedSubscription->status,
                    'paid_at' => optional($lockedSubscription->paid_at)?->toIso8601String(),
                    'started_at' => optional($lockedSubscription->started_at)?->toIso8601String(),
                    'expires_at' => optional($lockedSubscription->expires_at)?->toIso8601String(),
                    'zoho_payment_id' => $lockedSubscription->zoho_payment_id,
                ]);
            });

            $subscription->refresh();

            Log::info('circle subscription activated', [
                'circle_subscription_id' => $subscription->id,
                'payment_id' => $subscription->zoho_payment_id,
                'subscription_id' => $subscription->zoho_subscription_id,
            ]);

            $user = $subscription->user;
            $user->forceFill([
                'membership_status' => 'Circle Peer',
                'active_circle_id' => $subscription->circle_id,
                'active_circle_subscription_id' => $subscription->id,
                'circle_joined_at' => $startedAt,
                'circle_expires_at' => $expiresAt,
                'active_circle_addon_code' => $subscription->zoho_addon_code,
                'active_circle_addon_name' => $subscription->zoho_addon_name,
            ])->save();

            Log::info('user upgraded to Circle Peer', [
                'user_id' => $user->id,
                'circle_id' => $subscription->circle_id,
            ]);

            $this->circleJoinRequestPaymentSyncService->syncPaidSubscription($subscription, [
                'payment_id' => $identifiers['payment_id'] ?: null,
                'payment_reference' => $identifiers['reference_id'] ?? null,
                'payment_number' => $identifiers['payment_number'] ?? null,
                'amount' => $identifiers['amount'] ?? null,
                'currency_code' => $identifiers['currency_code'] ?? null,
            ]);

            return response()->json(['success' => true]);
        } catch (Throwable $throwable) {
            Log::error('circle webhook failed', [
                'message' => $throwable->getMessage(),
            ]);

            return response()->json(['success' => true, 'message' => 'Webhook received']);
        }
    }

    private function detectCircleWebhookPayloadType(array $payload): string
    {
        if (is_array(data_get($payload, 'customerpayment')) || is_array(data_get($payload, 'data.customerpayment'))) {
            return 'customerpayment';
        }

        if (is_array(data_get($payload, 'payment')) || is_array(data_get($payload, 'data.payment'))) {
            return 'payment';
        }

        if (is_array(data_get($payload, 'invoice_payment')) || is_array(data_get($payload, 'data.invoice_payment'))) {
            return 'invoice_payment';
        }

        if (is_array(data_get($payload, 'invoice')) || is_array(data_get($payload, 'data.invoice'))) {
            return 'invoice';
        }

        if (is_array(data_get($payload, 'subscription')) || is_array(data_get($payload, 'data.subscription'))) {
            return 'subscription';
        }

        if (is_array(data_get($payload, 'addon')) || is_array(data_get($payload, 'data.addon'))) {
            return 'addon';
        }

        return 'unknown';
    }

    private function extractCircleWebhookIdentifiers(array $payload): array
    {
        $paymentInvoice = data_get($payload, 'payment.invoices.0')
            ?? data_get($payload, 'data.payment.invoices.0')
            ?? [];

        return [
            'customer_id' => $this->firstString($payload, [
                'customer.customer_id', 'data.customer.customer_id',
                'customerpayment.customer_id', 'data.customerpayment.customer_id',
                'payment.customer_id', 'data.payment.customer_id',
                'invoice.customer_id', 'data.invoice.customer_id',
                'customer_id',
            ]),
            'payment_id' => $this->firstString($payload, [
                'customerpayment.payment_id', 'data.customerpayment.payment_id',
                'payment.payment_id', 'data.payment.payment_id',
                'invoice_payment.payment_id', 'data.invoice_payment.payment_id',
                'payment_id',
            ]),
            'payment_number' => $this->firstString($payload, [
                'customerpayment.payment_number', 'data.customerpayment.payment_number',
                'payment.payment_number', 'data.payment.payment_number',
                'payment_number',
            ]),
            'invoice_id' => $this->firstString($payload, [
                'payment.invoices.0.invoice_id', 'data.payment.invoices.0.invoice_id',
                'invoice.invoice_id', 'data.invoice.invoice_id',
                'customerpayment.invoice_id', 'data.customerpayment.invoice_id',
                'invoice_payment.invoice_id', 'data.invoice_payment.invoice_id',
                'invoice_id',
            ]),
            'invoice_number' => $this->firstString($payload, [
                'payment.invoices.0.invoice_number', 'data.payment.invoices.0.invoice_number',
                'invoice.invoice_number', 'data.invoice.invoice_number', 'invoice_number',
            ]),
            'subscription_id' => $this->firstString($payload, [
                'payment.invoices.0.subscription_ids.0', 'data.payment.invoices.0.subscription_ids.0',
                'subscription.subscription_id', 'data.subscription.subscription_id',
                'customerpayment.subscription_id', 'data.customerpayment.subscription_id',
                'payment.subscription_id', 'data.payment.subscription_id',
                'invoice.subscription_id', 'data.invoice.subscription_id',
                'subscription_id',
            ]),
            'reference_id' => $this->firstString($payload, [
                'reference_id', 'data.reference_id',
                'customerpayment.reference_id', 'data.customerpayment.reference_id',
                'payment.reference_id', 'data.payment.reference_id',
                'invoice.reference_id', 'data.invoice.reference_id',
                'subscription.reference_id', 'data.subscription.reference_id',
                'hostedpage.reference_id', 'data.hostedpage.reference_id',
            ]),
            'amount' => data_get($payload, 'customerpayment.amount')
                ?? data_get($payload, 'data.customerpayment.amount')
                ?? data_get($payload, 'payment.amount')
                ?? data_get($payload, 'data.payment.amount')
                ?? data_get($payload, 'payment.invoices.0.amount_applied')
                ?? data_get($payload, 'data.payment.invoices.0.amount_applied')
                ?? data_get($payload, 'invoice.total')
                ?? data_get($payload, 'data.invoice.total')
                ?? data_get($payload, 'amount'),
            'amount_applied' => data_get($payload, 'payment.invoices.0.amount_applied')
                ?? data_get($payload, 'data.payment.invoices.0.amount_applied'),
            'currency_code' => $this->firstString($payload, [
                'customerpayment.currency_code', 'data.customerpayment.currency_code',
                'payment.currency_code', 'data.payment.currency_code',
                'invoice.currency_code', 'data.invoice.currency_code',
                'currency_code', 'currency',
            ]),
            'payment_status' => strtolower($this->firstString($payload, [
                'customerpayment.payment_status', 'data.customerpayment.payment_status',
                'payment.payment_status', 'data.payment.payment_status',
                'payment.status', 'data.payment.status',
                'invoice_payment.status', 'data.invoice_payment.status',
                'invoice.status', 'data.invoice.status',
                'payment_status', 'status',
            ]) ?: ''),
            'paid_at' => $this->firstString($payload, [
                'payment.date', 'data.payment.date',
                'customerpayment.payment_time', 'data.customerpayment.payment_time',
                'customerpayment.paid_time', 'data.customerpayment.paid_time',
                'payment.payment_time', 'data.payment.payment_time',
                'payment.paid_time', 'data.payment.paid_time',
                'invoice_payment.payment_time', 'data.invoice_payment.payment_time',
                'paid_at', 'payment_time',
            ]),
            'transaction_type' => is_array($paymentInvoice)
                ? $this->firstString(['invoice' => $paymentInvoice], ['invoice.transaction_type'])
                : null,
            'addon_code' => $this->firstString($payload, [
                'addon.addon_code', 'data.addon.addon_code',
                'addons.0.addon_code', 'data.addons.0.addon_code',
                'subscription.addons.0.addon_code', 'data.subscription.addons.0.addon_code',
                'customerpayment.addon_code', 'data.customerpayment.addon_code',
            ]),
            'hostedpage_id' => $this->firstString($payload, [
                'payment.invoices.0.hosted_page_id', 'data.payment.invoices.0.hosted_page_id',
                'hostedpage.hostedpage_id', 'data.hostedpage.hostedpage_id',
                'hostedpage.decrypted_hostedpage_id', 'data.hostedpage.decrypted_hostedpage_id',
                'customerpayment.hostedpage_id', 'data.customerpayment.hostedpage_id',
                'payment.hostedpage_id', 'data.payment.hostedpage_id',
                'hostedpage_id',
            ]),
            'decrypted_hostedpage_id' => $this->firstString($payload, [
                'hostedpage.decrypted_hostedpage_id', 'data.hostedpage.decrypted_hostedpage_id',
                'decrypted_hostedpage_id',
            ]),
        ];
    }

    private function findPendingCircleSubscription(array $identifiers): array
    {
        $referenceId = (string) ($identifiers['reference_id'] ?? '');
        $hostedPageId = (string) ($identifiers['hostedpage_id'] ?? '');
        $decryptedHostedPageId = (string) ($identifiers['decrypted_hostedpage_id'] ?? '');
        $subscriptionId = (string) ($identifiers['subscription_id'] ?? '');
        $invoiceId = (string) ($identifiers['invoice_id'] ?? '');
        $customerId = (string) ($identifiers['customer_id'] ?? '');
        $addonCode = (string) ($identifiers['addon_code'] ?? '');

        $findByHostedPage = function (bool $pendingOnly = true) use ($hostedPageId, $decryptedHostedPageId): ?CircleSubscription {
            if ($hostedPageId === '' && $decryptedHostedPageId === '') {
                return null;
            }

            return CircleSubscription::query()
                ->when($pendingOnly, fn ($query) => $query->where('status', 'pending'))
                ->where(function ($query) use ($hostedPageId, $decryptedHostedPageId): void {
                    if ($hostedPageId !== '') {
                        $query->orWhere('zoho_hosted_page_id', $hostedPageId);
                        if (Schema::hasColumn('circle_subscriptions', 'hostedpage_id')) {
                            $query->orWhere('hostedpage_id', $hostedPageId);
                        }
                    }

                    if ($decryptedHostedPageId !== '') {
                        $query->orWhere('zoho_hosted_page_id', $decryptedHostedPageId);

                        if (Schema::hasColumn('circle_subscriptions', 'zoho_decrypted_hosted_page_id')) {
                            $query->orWhere('zoho_decrypted_hosted_page_id', $decryptedHostedPageId);
                        }
                        if (Schema::hasColumn('circle_subscriptions', 'decrypted_hosted_page_id')) {
                            $query->orWhere('decrypted_hosted_page_id', $decryptedHostedPageId);
                        }
                    }
                })
                ->latest('created_at')
                ->first();
        };

        $findByReference = function (bool $pendingOnly = true) use ($referenceId): ?CircleSubscription {
            if ($referenceId === '') {
                return null;
            }

            return CircleSubscription::query()
                ->when($pendingOnly, fn ($query) => $query->where('status', 'pending'))
                ->latest('created_at')
                ->get()
                ->first(function (CircleSubscription $subscription) use ($referenceId) {
                    return $referenceId === (string) ($subscription->reference_id ?? '')
                        || $referenceId === (string) data_get($subscription->raw_checkout_response, 'reference_id')
                        || $referenceId === (string) data_get($subscription->raw_checkout_response, 'hostedpage.reference_id')
                        || $referenceId === (string) data_get($subscription->raw_checkout_response, 'hostedpage.data.reference_id')
                        || $referenceId === (string) data_get($subscription->raw_checkout_response, '_circle_checkout.reference_id');
                });
        };

        $findBySubscription = function (bool $pendingOnly = true) use ($subscriptionId, $addonCode): ?CircleSubscription {
            if ($subscriptionId === '') {
                return null;
            }

            return CircleSubscription::query()
                ->when($pendingOnly, fn ($query) => $query->where('status', 'pending'))
                ->where('zoho_subscription_id', $subscriptionId)
                ->when($addonCode !== '', fn ($query) => $query->where('zoho_addon_code', $addonCode))
                ->latest('created_at')
                ->first();
        };

        if ($hostedPageId !== '') {
            $byHostedPage = $findByHostedPage(true);

            if ($byHostedPage) {
                return ['subscription' => $byHostedPage, 'strategy' => 'hosted_page_pending'];
            }
        }

        if ($referenceId !== '') {
            $byReference = $findByReference(true);

            if ($byReference) {
                return ['subscription' => $byReference, 'strategy' => 'reference_id_pending'];
            }
        }

        if ($subscriptionId !== '') {
            $bySubscription = $findBySubscription(true);

            if ($bySubscription) {
                return ['subscription' => $bySubscription, 'strategy' => 'subscription_id_pending'];
            }
        }

        if ($invoiceId !== '') {
            $byInvoice = CircleSubscription::query()
                ->latest('created_at')
                ->get()
                ->first(function (CircleSubscription $subscription) use ($invoiceId) {
                    return $invoiceId === (string) data_get($subscription->raw_webhook_payload, 'invoice_id')
                        || $invoiceId === (string) data_get($subscription->raw_webhook_payload, 'invoice.invoice_id')
                        || $invoiceId === (string) data_get($subscription->raw_webhook_payload, 'payment.invoices.0.invoice_id')
                        || $invoiceId === (string) data_get($subscription->raw_checkout_response, 'invoice.invoice_id');
                });

            if ($byInvoice) {
                return ['subscription' => $byInvoice, 'strategy' => 'invoice_lookup'];
            }
        }

        $user = null;
        if ($customerId !== '') {
            $user = User::query()->where('zoho_customer_id', $customerId)->first();
        }

        if ($user) {
            $byUserPending = CircleSubscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->when($addonCode !== '', fn ($query) => $query->where('zoho_addon_code', $addonCode))
                ->latest('created_at')
                ->first();

            if ($byUserPending) {
                return ['subscription' => $byUserPending, 'strategy' => 'user_pending'];
            }

            $byUserLatest = CircleSubscription::query()
                ->where('user_id', $user->id)
                ->when($addonCode !== '', fn ($query) => $query->where('zoho_addon_code', $addonCode))
                ->latest('created_at')
                ->first();

            if ($byUserLatest) {
                return ['subscription' => $byUserLatest, 'strategy' => 'user_latest'];
            }
        }

        $byHostedPage = $findByHostedPage(false);
        if ($byHostedPage) {
            return ['subscription' => $byHostedPage, 'strategy' => 'hosted_page_any_status'];
        }

        $byReference = $findByReference(false);
        if ($byReference) {
            return ['subscription' => $byReference, 'strategy' => 'reference_id_any_status'];
        }

        $bySubscription = $findBySubscription(false);
        if ($bySubscription) {
            return ['subscription' => $bySubscription, 'strategy' => 'subscription_id_any_status'];
        }

        return ['subscription' => null, 'strategy' => 'no_match'];
    }

    private function recoverPendingSubscriptionFromHostedPageStatus(array $identifiers, string $payloadType): ?CircleSubscription
    {
        $addonCode = (string) ($identifiers['addon_code'] ?? '');
        $subscriptionId = (string) ($identifiers['subscription_id'] ?? '');

        $candidates = CircleSubscription::query()
            ->where('status', 'pending')
            ->whereNotNull('zoho_hosted_page_id')
            ->when($addonCode !== '', fn ($query) => $query->where('zoho_addon_code', $addonCode))
            ->when($subscriptionId !== '', fn ($query) => $query->where('zoho_subscription_id', $subscriptionId))
            ->latest('created_at')
            ->limit(15)
            ->get();

        foreach ($candidates as $candidate) {
            try {
                $hostedPage = $this->zohoBillingService->getHostedPage((string) $candidate->zoho_hosted_page_id);
                $parsed = $this->zohoBillingService->parseHostedPageForMembership($hostedPage);

                Log::info('circle hosted page recovery probe', [
                    'payload_type' => $payloadType,
                    'circle_subscription_id' => $candidate->id,
                    'hostedpage_id' => $candidate->zoho_hosted_page_id,
                    'parsed_status' => $parsed['status'] ?? null,
                    'is_paid' => $parsed['is_paid'] ?? false,
                    'parsed_subscription_id' => $parsed['subscription_id'] ?? null,
                ]);

                if (! ($parsed['is_paid'] ?? false)) {
                    continue;
                }

                return $candidate;
            } catch (Throwable $throwable) {
                Log::warning('circle hosted page recovery probe failed', [
                    'payload_type' => $payloadType,
                    'circle_subscription_id' => $candidate->id,
                    'hostedpage_id' => $candidate->zoho_hosted_page_id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function firstString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if ($value === null) {
                continue;
            }

            $value = is_string($value) ? trim($value) : (string) $value;

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function parseDateTimeValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function isValidWebhook(Request $request): bool
    {
        $expected = (string) config('services.zoho.webhook_token', env('ZOHO_WEBHOOK_TOKEN', ''));

        if ($expected === '') {
            Log::warning('Zoho webhook token missing from configuration');

            return false;
        }

        $incoming = (string) ($request->header('X-Webhook-Token')
            ?? $request->header('X-Zoho-Webhook-Signature')
            ?? $request->bearerToken()
            ?? $request->query('token')
            ?? $request->input('token')
            ?? '');

        $isValid = $incoming !== '' && hash_equals($expected, $incoming);

        Log::info('Zoho webhook authentication evaluated', [
            'token_present' => $incoming !== '',
            'valid' => $isValid,
        ]);

        return $isValid;
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
        if (Schema::hasColumn('circle_subscriptions', 'reference_id') && $referenceId !== '') {
            $byReferenceColumn = CircleSubscription::query()
                ->where('status', 'pending')
                ->where('reference_id', $referenceId)
                ->latest('created_at')
                ->first();

            if ($byReferenceColumn) {
                return ['subscription' => $byReferenceColumn, 'strategy' => 'reference_column_pending'];
            }
        }

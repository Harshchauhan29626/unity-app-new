<?php

namespace App\Support\Zoho;

use App\Models\User;
use App\Support\Membership\MembershipUpdater;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ZohoBillingService
{
    private const FALLBACK_PHONE = '9999999999';

    public function __construct(
        private readonly ZohoBillingClient $client,
        private readonly MembershipUpdater $membershipUpdater
    ) {
    }

    public function listActivePlans(): array
    {
        $response = $this->client->request('get', '/plans');
        $plans = Arr::get($response, 'plans', []);

        return collect($plans)
            ->filter(fn (array $plan): bool => Str::lower((string) ($plan['status'] ?? '')) === 'active')
            ->map(fn (array $plan): array => [
                'plan_code' => $plan['plan_code'] ?? null,
                'name' => $plan['name'] ?? null,
                'price' => isset($plan['price']) ? (float) $plan['price'] : null,
                'interval' => $plan['interval'] ?? ($plan['interval_unit'] ?? null),
                'status' => $plan['status'] ?? null,
                'description' => $plan['description'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function getOrganization(): array
    {
        return $this->client->request('get', '/organizations');
    }

    public function ensureCustomerForUser(User $user, ?string $resolvedPhone = null): array
    {
        $resolvedPhone ??= $this->resolvePhoneForZoho($user);

        if (! empty($user->zoho_customer_id)) {
            $this->ensurePortalAndContactPerson($user, (string) $user->zoho_customer_id, $this->buildPortalEmail($user), $resolvedPhone);

            return ['customer_id' => $user->zoho_customer_id, 'email' => $this->buildPortalEmail($user)];
        }

        $portalEmail = $this->buildPortalEmail($user);
        $customer = $this->findCustomerByEmail($portalEmail);

        if ($customer === null) {
            $payload = [
                'display_name' => $this->displayName($user),
                'first_name' => (string) ($user->first_name ?? ''),
                'last_name' => (string) ($user->last_name ?? ''),
                'company_name' => (string) ($user->company_name ?? ''),
                'email' => $portalEmail,
                'mobile' => $resolvedPhone,
                'phone' => $resolvedPhone,
                'is_portal_enabled' => true,
                'billing_address' => [
                    'city' => (string) ($user->city ?? ''),
                    'state' => (string) ($user->state ?? ''),
                ],
            ];

            try {
                $created = $this->client->request('post', '/customers', $payload);
            } catch (ZohoBillingException $exception) {
                if (! $this->isPhoneFormatError($exception)) {
                    throw $exception;
                }

                $payload['mobile'] = self::FALLBACK_PHONE;
                $payload['phone'] = self::FALLBACK_PHONE;
                $created = $this->client->request('post', '/customers', $payload);
            }

            $customer = Arr::get($created, 'customer', []);
        }

        $customerId = (string) ($customer['customer_id'] ?? '');
        if ($customerId === '') {
            throw new ZohoBillingException('Unable to resolve Zoho customer id after ensureCustomerForUser.');
        }

        $user->forceFill(['zoho_customer_id' => $customerId])->save();

        $this->ensurePortalAndContactPerson($user, $customerId, $portalEmail, $resolvedPhone);

        return ['customer_id' => $customerId, 'email' => $portalEmail];
    }

    public function createHostedPageForSubscription(User $user, string $planCode): array
    {
        $resolvedPhone = $this->resolvePhoneForZoho($user);

        Log::info('Resolved phone for Zoho', [
            'user_id' => $user->id,
            'phone_masked' => $this->maskPhone($resolvedPhone),
        ]);

        $customer = $this->ensureCustomerForUser($user, $resolvedPhone);
        $payload = [
            'customer_id' => $customer['customer_id'],
            'plan' => [
                'plan_code' => $planCode,
            ],
        ];

        $response = $this->client->request('post', '/hostedpages/newsubscription', $payload);
        $hostedPage = Arr::get($response, 'hostedpage', []);

        return [
            'hostedpage_id' => $hostedPage['hostedpage_id'] ?? null,
            'checkout_url' => $hostedPage['url'] ?? null,
            'expires_at' => $hostedPage['expire_time'] ?? null,
            'zoho_customer_id' => $customer['customer_id'],
            'hosted_page' => $hostedPage,
        ];
    }

    public function getHostedPage(string $hostedpageId): array
    {
        return $this->client->request('get', '/hostedpages/' . $hostedpageId);
    }

    public function syncUserMembershipFromHostedPage(User $user, array $hostedPageResponse): bool
    {
        $hostedPage = Arr::get($hostedPageResponse, 'hostedpage', $hostedPageResponse);
        $subscription = Arr::get($hostedPage, 'subscription', Arr::get($hostedPageResponse, 'subscription', []));
        $invoice = Arr::get($hostedPage, 'invoice', Arr::get($hostedPageResponse, 'invoice', []));

        $updates = [
            'zoho_customer_id' => Arr::get($hostedPage, 'customer_id', Arr::get($subscription, 'customer_id', $user->zoho_customer_id)),
            'zoho_subscription_id' => Arr::get($subscription, 'subscription_id', $user->zoho_subscription_id),
            'zoho_plan_code' => Arr::get($subscription, 'plan.plan_code', Arr::get($subscription, 'plan_code', $user->zoho_plan_code)),
            'zoho_last_invoice_id' => Arr::get($invoice, 'invoice_id', Arr::get($hostedPage, 'invoice_id', $user->zoho_last_invoice_id)),
        ];

        $filtered = array_filter($updates, fn ($v) => $v !== null && $v !== '');
        if ($filtered !== []) {
            $user->forceFill($filtered)->save();
        }

        $status = Str::lower((string) Arr::get($subscription, 'status', Arr::get($hostedPage, 'status', '')));
        $paymentStatus = Str::lower((string) Arr::get($hostedPage, 'payment_status', Arr::get($invoice, 'status', '')));

        $isActive = in_array($status, ['active', 'live', 'trial'], true)
            || in_array($paymentStatus, ['paid', 'success', 'processed'], true)
            || (string) Arr::get($hostedPage, 'action') === 'success';

        if (! $isActive) {
            return false;
        }

        return $this->membershipUpdater->activatePaidMembership($user, [
            'membership_starts_at' => Arr::get($subscription, 'created_time', Arr::get($subscription, 'start_date')),
            'membership_ends_at' => Arr::get($subscription, 'next_billing_at', Arr::get($subscription, 'expires_at')),
            'last_payment_at' => now(),
        ]);
    }

    public function applyWebhookEvent(array $event): bool
    {
        $eventType = Str::lower((string) ($event['event_type'] ?? $event['eventType'] ?? ''));
        $payload = (array) ($event['payload'] ?? []);

        $customerId = Arr::get($payload, 'customer.customer_id')
            ?? Arr::get($payload, 'subscription.customer_id')
            ?? Arr::get($payload, 'invoice.customer_id')
            ?? Arr::get($payload, 'customer_id');

        $subscriptionId = Arr::get($payload, 'subscription.subscription_id')
            ?? Arr::get($payload, 'subscription_id');

        $userQuery = User::query();
        if (! empty($subscriptionId)) {
            $userQuery->orWhere('zoho_subscription_id', (string) $subscriptionId);
        }
        if (! empty($customerId)) {
            $userQuery->orWhere('zoho_customer_id', (string) $customerId);
        }

        $user = $userQuery->first();
        if (! $user) {
            Log::warning('Zoho webhook user not found', ['event_type' => $eventType, 'payload' => $payload]);

            return false;
        }

        $force = [
            'zoho_customer_id' => $customerId ?: $user->zoho_customer_id,
            'zoho_subscription_id' => $subscriptionId ?: $user->zoho_subscription_id,
            'zoho_plan_code' => Arr::get($payload, 'subscription.plan.plan_code', Arr::get($payload, 'subscription.plan_code', $user->zoho_plan_code)),
            'zoho_last_invoice_id' => Arr::get($payload, 'invoice.invoice_id', Arr::get($payload, 'invoice_id', $user->zoho_last_invoice_id)),
        ];

        $user->forceFill(array_filter($force, fn ($v) => $v !== null && $v !== ''))->save();

        $activatingEvents = [
            'payment_thankyou',
            'subscription_created',
            'subscription_activation',
            'subscription_activated',
        ];

        if (in_array($eventType, $activatingEvents, true)) {
            return $this->membershipUpdater->activatePaidMembership($user, [
                'membership_starts_at' => Arr::get($payload, 'subscription.created_time', Arr::get($payload, 'subscription.start_date')),
                'membership_ends_at' => Arr::get($payload, 'subscription.next_billing_at', Arr::get($payload, 'subscription.expires_at')),
                'last_payment_at' => now(),
            ]);
        }

        if ($eventType === 'invoice_created') {
            return true;
        }

        return false;
    }

    private function ensurePortalAndContactPerson(User $user, string $customerId, string $portalEmail, string $resolvedPhone): void
    {
        $existing = $this->client->request('get', '/contactpersons', query: ['customer_id' => $customerId]);
        $contactPeople = Arr::get($existing, 'contact_persons', []);

        $alreadyExists = collect($contactPeople)
            ->contains(fn (array $person): bool => Str::lower((string) ($person['email'] ?? '')) === Str::lower($portalEmail));

        if (! $alreadyExists) {
            $this->createContactPerson($customerId, $portalEmail, $user, $resolvedPhone);
        }

        $payload = [
            'is_portal_enabled' => true,
            'email' => $portalEmail,
            'mobile' => $resolvedPhone,
            'phone' => $resolvedPhone,
        ];

        try {
            $this->client->request('put', '/customers/' . $customerId, $payload);
        } catch (ZohoBillingException $exception) {
            if ($this->isPhoneFormatError($exception)) {
                $payload['mobile'] = self::FALLBACK_PHONE;
                $payload['phone'] = self::FALLBACK_PHONE;

                try {
                    $this->client->request('put', '/customers/' . $customerId, $payload);
                    return;
                } catch (ZohoBillingException $retryException) {
                    Log::warning('Zoho customer phone update failed after fallback retry', [
                        'customer_id' => $customerId,
                        'code' => $retryException->zohoCode(),
                        'message' => $retryException->getMessage(),
                    ]);

                    return;
                }
            }

            Log::warning('Zoho customer update failed before checkout', [
                'customer_id' => $customerId,
                'code' => $exception->zohoCode(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function createContactPerson(string $customerId, string $email, User $user, string $resolvedPhone): void
    {
        $payload = [
            'customer_id' => $customerId,
            'email' => $email,
            'first_name' => (string) ($user->first_name ?? 'Unity'),
            'last_name' => (string) ($user->last_name ?? 'User'),
            'mobile' => $resolvedPhone,
            'phone' => $resolvedPhone,
            'is_portal_enabled' => true,
        ];

        try {
            $this->client->request('post', '/contactpersons', $payload);
        } catch (ZohoBillingException $exception) {
            if ($exception->zohoCode() === '31027') {
                $retryEmail = $this->buildPortalEmail($user, true);
                $payload['email'] = $retryEmail;
                $this->client->request('post', '/contactpersons', $payload);

                return;
            }

            if (! $this->isPhoneFormatError($exception)) {
                throw $exception;
            }

            $payload['mobile'] = self::FALLBACK_PHONE;
            $payload['phone'] = self::FALLBACK_PHONE;
            $this->client->request('post', '/contactpersons', $payload);
        }
    }

    private function findCustomerByEmail(string $email): ?array
    {
        try {
            $response = $this->client->request('get', '/customers', query: ['email' => $email]);
        } catch (ZohoBillingException $exception) {
            Log::warning('Zoho customer lookup by email failed; falling back to list lookup', [
                'code' => $exception->zohoCode(),
                'message' => $exception->getMessage(),
            ]);

            $response = $this->client->request('get', '/customers', query: ['per_page' => 200]);
        }

        $customers = Arr::get($response, 'customers', []);

        return collect($customers)
            ->first(fn (array $customer): bool => Str::lower((string) ($customer['email'] ?? '')) === Str::lower($email));
    }

    private function buildPortalEmail(User $user, bool $withTimestamp = false): string
    {
        $prefix = (string) config('zoho_billing.portal_demo_email_prefix', 'demo');
        $idPrefix = Str::lower(Str::substr(Str::replace('-', '', (string) $user->id), 0, 12));

        $email = sprintf('%s+%s@gmail.com', $prefix, $idPrefix);

        if ($withTimestamp) {
            $email = sprintf('%s+%s+%s@gmail.com', $prefix, $idPrefix, now()->timestamp);
        }

        return Str::lower($email);
    }

    private function displayName(User $user): string
    {
        $name = trim((string) ($user->display_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $name = trim(((string) ($user->first_name ?? '')) . ' ' . ((string) ($user->last_name ?? '')));

        return $name !== '' ? $name : 'Unity User';
    }

    private function resolvePhoneForZoho(User $user): string
    {
        $candidates = [
            $user->phone ?? null,
            $user->mobile ?? null,
            $user->phone_number ?? null,
            $user->contact_number ?? null,
            $user->whatsapp_number ?? null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeIndiaPhone((string) $candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return self::FALLBACK_PHONE;
    }

    private function normalizeIndiaPhone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        if (strlen($digits) < 10) {
            return null;
        }

        return $digits;
    }

    private function maskPhone(string $phone): string
    {
        return '******' . substr($phone, -2);
    }

    private function isPhoneFormatError(ZohoBillingException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return Str::contains($message, ['phone', 'mobile']);
    }
}

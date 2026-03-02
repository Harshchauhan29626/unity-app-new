<?php

namespace App\Support\Zoho;

use App\Models\User;
use App\Support\Membership\MembershipUpdater;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ZohoBillingService
{
    private const FALLBACK_PHONE = '9999999999';
    private const HOSTEDPAGE_NEW_SUBSCRIPTION_PATH = '/hostedpages/newsubscription';

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
        $email = $this->resolveBillingEmail($user);
        $resolvedPhone ??= $this->resolveZohoPhone($user);

        $customerId = $this->resolveOrCreateCustomerId($user, $email, $resolvedPhone);

        $this->ensureCustomerPortalAndPhone($customerId, $email, $resolvedPhone);
        $this->ensureContactPerson($user, $customerId, $email, $resolvedPhone);

        return [
            'customer_id' => $customerId,
            'email' => $email,
        ];
    }

    public function createHostedPageForSubscription(User $user, string $planCode): array
    {
        $resolvedPhone = $this->resolveZohoPhone($user);

        Log::info('Resolved phone for Zoho checkout', [
            'user_id' => $user->id,
            'phone_masked' => $this->maskPhone($resolvedPhone),
            'email_masked' => $this->maskEmail($this->resolveBillingEmail($user)),
        ]);

        $customer = $this->ensureCustomerForUser($user, $resolvedPhone);
        $payload = [
            'customer_id' => $customer['customer_id'],
            'plan' => [
                'plan_code' => $planCode,
            ],
        ];

        try {
            $response = $this->client->request('post', self::HOSTEDPAGE_NEW_SUBSCRIPTION_PATH, $payload);
        } catch (ZohoBillingException $exception) {
            Log::error('Zoho hostedpage creation failed', [
                'path' => self::HOSTEDPAGE_NEW_SUBSCRIPTION_PATH,
                'status' => $exception->statusCode(),
                'zoho_code' => $exception->zohoCode(),
                'response' => $exception->payload(),
            ]);

            throw $exception;
        }

        $hostedPage = Arr::get($response, 'hostedpage', []);
        $hostedPageId = (string) ($hostedPage['hostedpage_id'] ?? '');
        $checkoutUrl = (string) ($hostedPage['url'] ?? '');

        if ($hostedPageId === '' || $checkoutUrl === '') {
            Log::error('Zoho hostedpage response missing required fields', [
                'path' => self::HOSTEDPAGE_NEW_SUBSCRIPTION_PATH,
                'response' => $response,
            ]);

            throw new RuntimeException('Zoho hostedpage response did not include hostedpage_id or url.');
        }

        return [
            'hostedpage_id' => $hostedPageId,
            'checkout_url' => $checkoutUrl,
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

    private function resolveOrCreateCustomerId(User $user, string $email, string $phone): string
    {
        $existingId = trim((string) ($user->zoho_customer_id ?? ''));

        if ($existingId !== '') {
            if ($this->customerExists($existingId)) {
                return $existingId;
            }

            Log::warning('Stored Zoho customer id not found; recreating customer mapping', [
                'user_id' => $user->id,
                'zoho_customer_id' => $existingId,
            ]);

            $user->forceFill(['zoho_customer_id' => null])->save();
        }

        $customer = $this->findCustomerByEmail($email);

        if (! $customer) {
            $customer = $this->createCustomer($user, $email, $phone);
        }

        $customerId = (string) ($customer['customer_id'] ?? '');

        if ($customerId === '') {
            throw ValidationException::withMessages([
                'billing' => ['Unable to resolve Zoho customer id.'],
            ]);
        }

        $user->forceFill(['zoho_customer_id' => $customerId])->save();

        return $customerId;
    }

    private function customerExists(string $customerId): bool
    {
        try {
            $this->client->request('get', '/customers/' . $customerId);

            return true;
        } catch (ZohoBillingException $exception) {
            return false;
        }
    }

    private function createCustomer(User $user, string $email, string $phone): array
    {
        $payload = [
            'display_name' => $this->displayName($user),
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'company_name' => (string) ($user->company_name ?? ''),
            'email' => $email,
            'mobile' => $phone,
            'phone' => $phone,
            'is_portal_enabled' => true,
            'billing_address' => [
                'city' => (string) ($user->city ?? ''),
                'state' => (string) ($user->state ?? ''),
            ],
        ];

        $created = $this->requestWithPhoneFallback('post', '/customers', $payload);

        return Arr::get($created, 'customer', []);
    }

    private function ensureCustomerPortalAndPhone(string $customerId, string $email, string $phone): void
    {
        $payload = [
            'is_portal_enabled' => true,
            'email' => $email,
            'mobile' => $phone,
            'phone' => $phone,
        ];

        try {
            $this->requestWithPhoneFallback('put', '/customers/' . $customerId, $payload);
        } catch (\Throwable $throwable) {
            Log::warning('Zoho customer update failed before checkout; continuing', [
                'customer_id' => $customerId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function ensureContactPerson(User $user, string $customerId, string $email, string $phone): void
    {
        $existing = $this->client->request('get', '/contactpersons', query: ['customer_id' => $customerId]);
        $contactPeople = Arr::get($existing, 'contact_persons', []);

        $contact = collect($contactPeople)
            ->first(fn (array $person): bool => Str::lower((string) ($person['email'] ?? '')) === Str::lower($email));

        if ($contact) {
            $contactPersonId = (string) ($contact['contact_person_id'] ?? '');
            if ($contactPersonId !== '') {
                $this->ensureContactPersonPortalAndPhone($contactPersonId, $phone);
            }

            return;
        }

        $payload = [
            'customer_id' => $customerId,
            'first_name' => (string) ($user->first_name ?? 'Unity'),
            'last_name' => (string) ($user->last_name ?? 'User'),
            'email' => $email,
            'mobile' => $phone,
            'phone' => $phone,
            'is_portal_enabled' => true,
        ];

        try {
            $this->requestWithPhoneFallback('post', '/contactpersons', $payload);
        } catch (ZohoBillingException $exception) {
            if ($exception->zohoCode() !== '31027') {
                throw $exception;
            }

            $refetched = $this->client->request('get', '/contactpersons', query: ['customer_id' => $customerId]);
            $refetchedContacts = Arr::get($refetched, 'contact_persons', []);
            $existingByEmail = collect($refetchedContacts)
                ->first(fn (array $person): bool => Str::lower((string) ($person['email'] ?? '')) === Str::lower($email));

            if ($existingByEmail) {
                $contactPersonId = (string) ($existingByEmail['contact_person_id'] ?? '');
                if ($contactPersonId !== '') {
                    $this->ensureContactPersonPortalAndPhone($contactPersonId, $phone);
                }

                return;
            }

            throw ValidationException::withMessages([
                'billing' => ['Zoho contact person email already exists but could not be linked for this customer.'],
            ]);
        }
    }

    private function ensureContactPersonPortalAndPhone(string $contactPersonId, string $phone): void
    {
        $payload = [
            'is_portal_enabled' => true,
            'mobile' => $phone,
            'phone' => $phone,
        ];

        try {
            $this->requestWithPhoneFallback('put', '/contactpersons/' . $contactPersonId, $payload);
        } catch (\Throwable $throwable) {
            Log::warning('Zoho contact person update failed; continuing', [
                'contact_person_id' => $contactPersonId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function requestWithPhoneFallback(string $method, string $path, array $payload): array
    {
        try {
            return $this->client->request($method, $path, $payload);
        } catch (ZohoBillingException $exception) {
            if (! $this->isPhoneFormatError($exception)) {
                throw $exception;
            }

            $retryPayload = $payload;
            $retryPayload['mobile'] = self::FALLBACK_PHONE;
            $retryPayload['phone'] = self::FALLBACK_PHONE;

            return $this->client->request($method, $path, $retryPayload);
        }
    }

    private function findCustomerByEmail(string $email): ?array
    {
        try {
            $response = $this->client->request('get', '/customers', query: ['email' => $email]);
        } catch (ZohoBillingException $exception) {
            Log::warning('Zoho customer lookup by email failed; falling back to paginated list lookup', [
                'code' => $exception->zohoCode(),
                'message' => $exception->getMessage(),
            ]);

            $response = $this->client->request('get', '/customers', query: ['page' => 1, 'per_page' => 200]);
        }

        $customers = Arr::get($response, 'customers', []);

        return collect($customers)
            ->first(fn (array $customer): bool => Str::lower((string) ($customer['email'] ?? '')) === Str::lower($email));
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

    private function resolveBillingEmail(User $user): string
    {
        $email = Str::lower(trim((string) ($user->email ?? '')));

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => ['User email is required for billing.'],
            ]);
        }

        return $email;
    }

    private function resolveZohoPhone(User $user): string
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
            return self::FALLBACK_PHONE;
        }

        return $digits;
    }

    private function maskPhone(string $phone): string
    {
        return '******' . substr($phone, -2);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $name = $parts[0];
        $domain = $parts[1];

        $maskedName = strlen($name) <= 2
            ? str_repeat('*', strlen($name))
            : substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 2)) . substr($name, -1);

        return $maskedName . '@' . $domain;
    }

    private function isPhoneFormatError(ZohoBillingException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return Str::contains($message, ['phone', 'mobile']);
    }
}

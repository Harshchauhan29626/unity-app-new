<?php

namespace App\Support\Zoho;

use App\Models\User;
use App\Support\Membership\MembershipUpdater;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ZohoBillingService
{
    public function __construct(
        private readonly ZohoBillingClient $client,
        private readonly MembershipUpdater $membershipUpdater,
    ) {
    }

    public function listActivePlans(): array
    {
        $response = $this->client->request('GET', '/plans', [
            'page' => 1,
            'per_page' => 200,
        ], true);
        $plans = $response['plans'] ?? [];

        return collect(array_values(array_filter($plans, fn (array $plan) => strtolower((string) ($plan['status'] ?? '')) === 'active')))
            ->map(fn (array $plan) => [
                'plan_code' => $plan['plan_code'] ?? null,
                'name' => $plan['name'] ?? null,
                'price' => $plan['recurring_price']
                    ?? $plan['price']
                    ?? data_get($plan, 'plan_item.price')
                    ?? data_get($plan, 'item.price')
                    ?? null,
                'interval' => $plan['interval'] ?? null,
                'status' => $plan['status'] ?? null,
                'description' => $plan['description'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function getOrganization(): array
    {
        $response = $this->client->request('GET', '/organizations');

        return $response['organizations'][0] ?? $response;
    }

    public function ensureCustomerForUser(User $user): string
    {
        $email = trim((string) ($user->email ?? ''));
        $phone = $this->resolveUserPhone($user);

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'User email missing',
            ]);
        }

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => 'User phone missing',
            ]);
        }

        $existingCustomerId = $this->findCustomerByEmail($email);

        if ($existingCustomerId !== null) {
            if ((string) $user->zoho_customer_id !== (string) $existingCustomerId) {
                $user->forceFill(['zoho_customer_id' => $existingCustomerId])->save();
            }

            $this->ensurePortalEnabled($user, $existingCustomerId, $email, $phone);

            return $existingCustomerId;
        }

        $name = trim((string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))));

        $payload = [
            'display_name' => $name !== '' ? $name : ($user->company_name ?: $email),
            'company_name' => $user->company_name,
            'email' => $email,
            'mobile' => $phone,
            'phone' => $phone,
            'is_portal_enabled' => true,
            'contact_persons' => [$this->buildPrimaryContactPerson($user, $email, $phone)],
            'billing_address' => [
                'city' => $user->city ?? '',
                'state' => '',
            ],
        ];

        $response = $this->client->request('POST', '/customers', $payload);
        $customerId = (string) ($response['customer']['customer_id'] ?? '');

        if ($customerId === '') {
            throw new RuntimeException('Unable to create Zoho customer.');
        }

        $user->forceFill(['zoho_customer_id' => $customerId])->save();

        $this->ensurePortalEnabled($user, $customerId, $email, $phone);

        return $customerId;
    }

    public function createHostedPageForSubscription(User $user, string $planCode): array
    {
        $customerId = $this->ensureCustomerForUser($user);

        $response = $this->client->request('POST', '/hostedpages/newsubscription', [
            'customer_id' => $customerId,
            'plan' => [
                'plan_code' => $planCode,
            ],
        ]);

        $hostedPage = $response['hostedpage'] ?? [];
        $hostedPageId = $hostedPage['hostedpage_id'] ?? null;
        $checkoutUrl = $hostedPage['url'] ?? null;

        if (! is_string($checkoutUrl) || $checkoutUrl === '' || ! is_string($hostedPageId) || $hostedPageId === '') {
            Log::error('Zoho hosted page response missing checkout details', [
                'response' => $response,
                'customer_id' => $customerId,
                'plan_code' => $planCode,
            ]);

            throw new RuntimeException('Failed to generate checkout URL.');
        }

        return [
            'hostedpage_id' => $hostedPageId,
            'checkout_url' => $checkoutUrl,
        ];
    }

    public function getHostedPage(string $hostedpageId): array
    {
        return $this->client->request('GET', '/hostedpages/' . $hostedpageId);
    }

    public function syncMembershipFromHostedPage(User $user, array $hostedPageResponse): bool
    {
        $hostedPage = $hostedPageResponse['hostedpage'] ?? [];
        $subscription = $hostedPage['subscription'] ?? [];

        $status = strtolower((string) ($subscription['status'] ?? $hostedPage['status'] ?? ''));
        $isPaid = in_array($status, ['active', 'live', 'paid', 'payment_success', 'success'], true);

        if (! $isPaid && ! isset($subscription['subscription_id'])) {
            return false;
        }

        return $this->membershipUpdater->applyPaidMembership($user, [
            'zoho_subscription_id' => $subscription['subscription_id'] ?? null,
            'zoho_plan_code' => $subscription['plan']['plan_code'] ?? null,
            'zoho_last_invoice_id' => $hostedPage['invoice']['invoice_id'] ?? ($subscription['invoice_id'] ?? null),
            'membership_starts_at' => $subscription['start_date'] ?? $subscription['created_time'] ?? null,
            'membership_ends_at' => $subscription['next_billing_at'] ?? $subscription['expires_at'] ?? null,
            'last_payment_at' => now(),
        ]);
    }

    public function applyWebhookEvent(array $event): bool
    {
        $eventType = strtolower((string) ($event['event_type'] ?? Arr::get($event, 'eventType', '')));
        $payload = Arr::get($event, 'payload', $event['data'] ?? []);

        $customerId = Arr::get($payload, 'customer.customer_id')
            ?? Arr::get($payload, 'subscription.customer_id')
            ?? Arr::get($payload, 'invoice.customer_id')
            ?? Arr::get($payload, 'customer_id');

        $subscriptionId = Arr::get($payload, 'subscription.subscription_id')
            ?? Arr::get($payload, 'subscription_id')
            ?? Arr::get($payload, 'invoice.subscription_id');

        $user = $this->resolveUserByZoho($customerId, $subscriptionId);

        if (! $user) {
            Log::warning('Zoho webhook user not found', [
                'event_type' => $eventType,
                'customer_id' => $customerId,
                'subscription_id' => $subscriptionId,
            ]);

            return false;
        }

        $invoiceId = Arr::get($payload, 'invoice.invoice_id') ?? Arr::get($payload, 'invoice_id');

        if (in_array($eventType, ['invoice_created'], true) && $invoiceId) {
            $user->forceFill(['zoho_last_invoice_id' => $invoiceId])->save();
        }

        if (in_array($eventType, ['payment_thankyou', 'subscription_created', 'subscription_activation', 'subscription_activated'], true)) {
            return $this->membershipUpdater->applyPaidMembership($user, [
                'zoho_customer_id' => $customerId,
                'zoho_subscription_id' => $subscriptionId,
                'zoho_plan_code' => Arr::get($payload, 'subscription.plan.plan_code') ?? Arr::get($payload, 'plan.plan_code'),
                'zoho_last_invoice_id' => $invoiceId,
                'membership_starts_at' => Arr::get($payload, 'subscription.start_date'),
                'membership_ends_at' => Arr::get($payload, 'subscription.next_billing_at'),
                'last_payment_at' => now(),
            ]);
        }

        return false;
    }

    private function findCustomerByEmail(string $email): ?string
    {
        $response = $this->client->request('GET', '/customers', ['email' => $email], true);
        $customers = $response['customers'] ?? [];

        foreach ($customers as $customer) {
            if (strtolower((string) ($customer['email'] ?? '')) === strtolower($email)) {
                return $customer['customer_id'] ?? null;
            }
        }

        return null;
    }

    private function ensurePortalEnabled(User $user, string $customerId, string $email, string $phone): void
    {
        $this->client->request('PUT', '/customers/' . $customerId, [
            'is_portal_enabled' => true,
            'email' => $email,
            'mobile' => $phone,
            'phone' => $phone,
            'contact_persons' => [$this->buildPrimaryContactPerson($user, $email, $phone)],
        ]);
    }

    private function buildPrimaryContactPerson(User $user, string $email, string $phone): array
    {
        return [
            'first_name' => $user->first_name ?: ($user->display_name ?: 'Unity'),
            'last_name' => $user->last_name ?: '',
            'email' => $email,
            'phone' => $phone,
            'mobile' => $phone,
            'is_primary_contact' => true,
        ];
    }

    private function resolveUserPhone(User $user): string
    {
        $phone = trim((string) ($user->phone ?? $user->getAttribute('mobile') ?? ''));

        if ($phone !== '') {
            return $phone;
        }

        return trim((string) (data_get($user->toArray(), 'mobile') ?? ''));
    }

    private function resolveUserByZoho(?string $customerId, ?string $subscriptionId): ?User
    {
        return User::query()
            ->when($customerId, fn ($query) => $query->orWhere('zoho_customer_id', $customerId))
            ->when($subscriptionId, fn ($query) => $query->orWhere('zoho_subscription_id', $subscriptionId))
            ->first();
    }
}

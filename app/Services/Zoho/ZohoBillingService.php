<?php

namespace App\Services\Zoho;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ZohoBillingService
{
    public function getAccessToken(): string
    {
        $cacheKey = $this->tokenCacheKey();
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenData = $this->refreshAccessToken();
        Cache::put($cacheKey, $tokenData['access_token'], now()->addMinutes(55));

        return $tokenData['access_token'];
    }

    public function billingBaseUrl(): string
    {
        return 'https://www.zohoapis.'.$this->regionTld().'/billing/v1';
    }

    public function tokenMeta(): array
    {
        $tokenData = $this->refreshAccessToken();
        Cache::put($this->tokenCacheKey(), $tokenData['access_token'], now()->addMinutes(55));

        return $tokenData;
    }

    public function zohoRequest(string $method, string $path, array $query = [], array $json = []): Response
    {
        $query = array_merge([
            'organization_id' => (string) config('services.zoho.org_id'),
        ], $query);

        $url = rtrim($this->billingBaseUrl(), '/').'/'.ltrim($path, '/');
        $request = Http::withToken($this->getAccessToken(), 'Zoho-oauthtoken');

        $response = strtoupper($method) === 'GET'
            ? $request->get($url, $query)
            : $request->send(strtoupper($method), $url.'?'.http_build_query($query), ['json' => $json]);

        if (! $response->successful()) {
            $this->logZohoError('Zoho API request failed', $response);
            throw new RuntimeException('Zoho API request failed: '.$response->body());
        }

        return $response;
    }

    public function findCustomerByEmail(string $email): ?array
    {
        $payload = $this->zohoRequest('GET', '/customers', ['email_contains' => trim($email)])->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid Zoho customers response.');
        }

        $customers = $payload['customers'] ?? [];

        if (! is_array($customers) || $customers === []) {
            return null;
        }

        $needle = $this->normalizeEmail($email);

        foreach ($customers as $customer) {
            if (is_array($customer) && $this->normalizeEmail((string) ($customer['email'] ?? '')) === $needle) {
                return $customer;
            }
        }

        return is_array($customers[0] ?? null) ? $customers[0] : null;
    }

    public function createCustomerWithContactPerson(User $user): array
    {
        $customerEmail = trim((string) $user->email);

        if ($customerEmail === '') {
            $customerEmail = $this->contactPersonEmailForUser($user);
        }

        $firstName = trim((string) ($user->first_name ?? ''));
        $lastName = trim((string) ($user->last_name ?? ''));
        $displayName = trim((string) ($user->display_name ?? ''));

        if ($displayName === '') {
            $displayName = trim($firstName.' '.$lastName);
        }
        if ($displayName === '') {
            $displayName = $customerEmail;
        }

        $payload = [
            'customer_name' => $displayName,
            'display_name' => $displayName,
            'company_name' => trim((string) ($user->company_name ?? '')) ?: $displayName,
            'email' => $customerEmail,
            'phone' => $user->phone ?? null,
            'contact_persons' => [[
                'first_name' => $firstName !== '' ? $firstName : $displayName,
                'last_name' => $lastName,
                'email' => $customerEmail,
                'is_primary_contact' => true,
            ]],
        ];

        $response = Http::withToken($this->getAccessToken(), 'Zoho-oauthtoken')
            ->post($this->billingBaseUrl().'/customers', array_merge($payload, [
                'organization_id' => (string) config('services.zoho.org_id'),
            ]));

        if (! $response->successful()) {
            Log::error('Zoho customer create failed', [
                'payload' => $payload,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Zoho API request failed: '.$response->body());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('Invalid Zoho customer create response.');
        }

        $customer = $body['customer'] ?? null;

        if (! is_array($customer) || empty($customer['customer_id'])) {
            throw new RuntimeException(json_encode($body));
        }

        return $customer;
    }

    public function getCustomer(string $customerId): array
    {
        $payload = $this->zohoRequest('GET', '/customers/'.$customerId)->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid Zoho customer response.');
        }

        $customer = $payload['customer'] ?? null;

        if (! is_array($customer) || empty($customer['customer_id'])) {
            throw new RuntimeException(json_encode($payload));
        }

        return $customer;
    }

    public function listContactPersons(string $customerId): array
    {
        $payload = $this->zohoRequest('GET', '/customers/'.$customerId.'/contactpersons')->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid Zoho contact persons response.');
        }

        $contacts = $payload['contact_persons'] ?? [];

        return is_array($contacts) ? $contacts : [];
    }

    public function createContactPerson(string $customerId, User $user): array
    {
        $email = $this->contactPersonEmailForUser($user);

        $body = $this->zohoRequest('POST', '/customers/'.$customerId.'/contactpersons', [], [
            'first_name' => trim((string) ($user->first_name ?? '')),
            'last_name' => trim((string) ($user->last_name ?? '')),
            'email' => $email,
            'is_primary_contact' => true,
        ])->json();

        if (! is_array($body)) {
            throw new RuntimeException('Invalid Zoho contact person response.');
        }

        $contact = $body['contact_person'] ?? null;

        if (! is_array($contact) || empty($contact['contact_person_id'])) {
            throw new RuntimeException(json_encode($body));
        }

        return $contact;
    }

    public function updateContactPersonPortalAndPrimary(string $customerId, string $contactPersonId): array
    {
        $body = $this->zohoRequest('PUT', '/customers/'.$customerId.'/contactpersons/'.$contactPersonId, [], [
            'is_primary_contact' => true,
        ])->json();

        if (! is_array($body)) {
            throw new RuntimeException('Invalid Zoho update contact person response.');
        }

        return $body['contact_person'] ?? $body;
    }

    public function ensureContactPerson(string $zohoCustomerId, User $user): array
    {
        $email = $this->contactPersonEmailForUser($user);
        $normalizedEmail = $this->normalizeEmail($email);

        $contacts = $this->listContactPersons($zohoCustomerId);
        $existing = $this->findContactPersonByEmailFromList($contacts, $normalizedEmail);

        if (is_array($existing)) {
            Log::info('Zoho contact person reused/created', [
                'zoho_customer_id' => $zohoCustomerId,
                'email_used' => $email,
                'action' => 'reused',
            ]);

            return $this->updateContactPersonPortalAndPrimary($zohoCustomerId, (string) $existing['contact_person_id']);
        }

        try {
            $created = $this->createContactPerson($zohoCustomerId, $user);

            Log::info('Zoho contact person reused/created', [
                'zoho_customer_id' => $zohoCustomerId,
                'email_used' => $email,
                'action' => 'created',
            ]);

            return $created;
        } catch (RuntimeException $e) {
            $decoded = json_decode($e->getMessage(), true);
            $errorCode = is_array($decoded) ? (string) ($decoded['code'] ?? '') : '';
            $errorMessage = is_array($decoded) ? (string) ($decoded['message'] ?? '') : $e->getMessage();

            Log::info('Zoho contact person create failed', [
                'zoho_customer_id' => $zohoCustomerId,
                'email_used' => $email,
                'zoho_error_code' => $errorCode,
                'zoho_error_message' => $errorMessage,
            ]);

            if ($errorCode !== '31027') {
                throw $e;
            }

            $contacts = $this->listContactPersons($zohoCustomerId);
            $existing = $this->findContactPersonByEmailFromList($contacts, $normalizedEmail);

            if (! is_array($existing)) {
                throw $e;
            }

            Log::info('Zoho contact person reused/created', [
                'zoho_customer_id' => $zohoCustomerId,
                'email_used' => $email,
                'action' => 'reused_after_31027',
            ]);

            return $this->updateContactPersonPortalAndPrimary($zohoCustomerId, (string) $existing['contact_person_id']);
        }
    }

    public function ensurePrimaryPortalContactPerson(string $customerId, User $user): array
    {
        return $this->ensureContactPerson($customerId, $user);
    }

    public function ensureZohoCustomerForUser(User $user): string
    {
        $customerId = trim((string) ($user->zoho_customer_id ?? ''));

        if ($customerId !== '') {
            try {
                $existingCustomer = $this->getCustomer($customerId);
                $customerId = (string) ($existingCustomer['customer_id'] ?? '');
            } catch (RuntimeException) {
                $customerId = '';
            }
        }

        if ($customerId === '') {
            $found = $this->findCustomerByEmail((string) $user->email);

            if (is_array($found) && ! empty($found['customer_id'])) {
                $customerId = (string) $found['customer_id'];
            } else {
                $created = $this->createCustomerWithContactPerson($user);
                $customerId = (string) ($created['customer_id'] ?? '');
            }
        }

        if ($customerId === '') {
            throw new RuntimeException('Unable to resolve Zoho customer_id.');
        }

        if (Schema::hasColumn('users', 'zoho_customer_id')) {
            $user->zoho_customer_id = $customerId;
            $user->save();
        }

        $contactPerson = $this->ensureContactPerson($customerId, $user);

        if (Schema::hasColumn('users', 'zoho_contact_person_id')) {
            $contactPersonId = (string) ($contactPerson['contact_person_id'] ?? '');
            if ($contactPersonId !== '') {
                $user->zoho_contact_person_id = $contactPersonId;
                $user->save();
            }
        }

        return $customerId;
    }

    public function getPlanByCode(string $planCode): ?array
    {
        $payload = $this->zohoRequest('GET', '/plans')->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid Zoho plans response.');
        }

        foreach (($payload['plans'] ?? []) as $plan) {
            if (is_array($plan) && (string) ($plan['plan_code'] ?? '') === $planCode) {
                return $plan;
            }
        }

        return null;
    }

    public function createInvoiceForPlan(string $customerId, array $plan): array
    {
        $rate = $plan['recurring_price'] ?? null;

        if ($rate === null && isset($plan['price_brackets'][0]['price'])) {
            $rate = $plan['price_brackets'][0]['price'];
        }

        if ($rate === null) {
            throw new RuntimeException('Plan price not available for invoice creation.');
        }

        $payload = [
            'customer_id' => $customerId,
            'reference_number' => $plan['plan_code'] ?? null,
            'line_items' => [[
                'name' => $plan['name'] ?? ($plan['plan_code'] ?? 'Plan'),
                'description' => $plan['description'] ?? null,
                'rate' => $rate,
                'quantity' => 1,
            ]],
        ];

        $body = $this->zohoRequest('POST', '/invoices', [], $payload)->json();

        if (! is_array($body)) {
            throw new RuntimeException('Invalid Zoho invoice create response.');
        }

        if (($body['code'] ?? null) !== 0) {
            throw new RuntimeException(json_encode($body));
        }

        $invoice = $body['invoice'] ?? null;

        if (! is_array($invoice) || empty($invoice['invoice_id'])) {
            throw new RuntimeException(json_encode($body));
        }

        return $invoice;
    }

    public function createInvoicePaymentLink(string $invoiceId): array
    {
        $body = $this->zohoRequest('POST', '/invoices/'.$invoiceId.'/paymentlink')->json();

        if (! is_array($body)) {
            throw new RuntimeException('Invalid Zoho payment link response.');
        }

        if (($body['code'] ?? null) !== 0) {
            throw new RuntimeException(json_encode($body));
        }

        return $body['payment_link'] ?? $body;
    }

    public function findUserByZohoCustomerId(string $customerId): ?User
    {
        if (! Schema::hasColumn('users', 'zoho_customer_id')) {
            return null;
        }

        return User::query()->where('zoho_customer_id', $customerId)->first();
    }

    public function computeMembershipEndAt(string $planCode): ?\Carbon\Carbon
    {
        $plan = $this->getPlanByCode($planCode);

        if (! is_array($plan)) {
            return null;
        }

        $interval = (int) ($plan['interval'] ?? 1);
        $unit = strtolower((string) ($plan['interval_unit'] ?? 'years'));

        $now = now();

        return match ($unit) {
            'day', 'days' => $now->copy()->addDays(max($interval, 1)),
            'month', 'months' => $now->copy()->addMonths(max($interval, 1)),
            default => $now->copy()->addYears(max($interval, 1)),
        };
    }
    private function findContactPersonByEmailFromList(array $contacts, string $normalizedEmail): ?array
    {
        foreach ($contacts as $contact) {
            if (is_array($contact) && $this->normalizeEmail((string) ($contact['email'] ?? '')) === $normalizedEmail) {
                return $contact;
            }
        }

        return null;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function contactPersonEmailForUser(User $user): string
    {
        $userEmail = trim((string) $user->email);

        if ($userEmail !== '') {
            return $userEmail;
        }

        return 'demo+'.$user->id.'@gmail.com';
    }

    private function refreshAccessToken(): array
    {
        $response = Http::asForm()->post(
            'https://accounts.zoho.'.$this->regionTld().'/oauth/v2/token',
            [
                'refresh_token' => (string) config('services.zoho.refresh_token'),
                'client_id' => (string) config('services.zoho.client_id'),
                'client_secret' => (string) config('services.zoho.client_secret'),
                'grant_type' => 'refresh_token',
            ]
        );

        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload)) {
            throw new RuntimeException('Zoho token refresh failed: '.json_encode($payload));
        }

        $accessToken = $payload['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Zoho token refresh failed: access_token missing.');
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => $payload['expires_in'] ?? ($payload['expires_in_sec'] ?? 3600),
            'api_domain' => 'https://www.zohoapis.'.$this->regionTld(),
        ];
    }

    private function logZohoError(string $context, Response $response): void
    {
        Log::error($context, [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    private function regionTld(): string
    {
        return match ((string) config('services.zoho.dc', 'in')) {
            'us' => 'com',
            'eu' => 'eu',
            default => 'in',
        };
    }

    private function tokenCacheKey(): string
    {
        return 'zoho.billing.access_token.'.(string) config('services.zoho.org_id', 'default');
    }
}

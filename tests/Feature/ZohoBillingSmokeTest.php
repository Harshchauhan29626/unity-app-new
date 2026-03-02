<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZohoBillingSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_endpoint_returns_success_shape(): void
    {
        Http::fake([
            'https://accounts.zoho.in/oauth/v2/token' => Http::response([
                'access_token' => 'token-123',
                'expires_in' => 3600,
            ]),
            'https://subscriptions.zoho.in/api/v1/plans*' => Http::response([
                'plans' => [
                    ['plan_code' => '01', 'name' => 'Starter', 'price' => 100, 'interval' => 'month', 'status' => 'active'],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/zoho/plans');

        $response->assertOk()->assertJson([
            'success' => true,
        ]);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $response = $this->postJson('/api/v1/zoho/webhook?secret=invalid', [
            'event_type' => 'payment_thankyou',
            'payload' => [],
        ]);

        $response->assertStatus(401)->assertJson([
            'success' => false,
        ]);
    }

    public function test_checkout_status_endpoint_updates_membership_using_payment_mapping_without_auth(): void
    {
        Http::fake([
            'https://accounts.zoho.in/oauth/v2/token' => Http::response([
                'access_token' => 'token-123',
                'expires_in' => 3600,
            ]),
            'https://subscriptions.zoho.in/api/v1/hostedpages/hp_123' => Http::response([
                'hostedpage' => [
                    'status' => 'completed',
                    'invoice' => ['invoice_id' => 'inv_001'],
                    'subscription' => [
                        'subscription_id' => 'sub_001',
                        'status' => 'active',
                        'current_term_starts_at' => '2026-01-01 00:00:00',
                        'current_term_ends_at' => '2026-02-01 00:00:00',
                        'plan_code' => '01',
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create([
            'email' => 'member@example.com',
            'phone' => '9999999999',
            'membership_status' => 'free_peer',
        ]);

        $payment = new Payment();
        $payment->id = (string) Str::uuid();
        $payment->forceFill([
            'user_id' => $user->id,
            'status' => 'pending',
            'provider' => 'zoho',
            'zoho_plan_code' => '01',
            'zoho_hostedpage_id' => 'hp_123',
        ]);
        $payment->save();

        $response = $this->getJson('/api/v1/billing/checkout/hp_123/status');

        $response->assertOk()->assertJson([
            'success' => true,
            'data' => [
                'zoho_subscription_id' => 'sub_001',
                'zoho_last_invoice_id' => 'inv_001',
                'zoho_plan_code' => '01',
            ],
        ]);

        $this->assertDatabaseHas('payments', [
            'zoho_hostedpage_id' => 'hp_123',
            'status' => 'paid',
        ]);
    }

    public function test_zoho_billing_webhook_rejects_missing_token(): void
    {
        $response = $this->postJson('/api/v1/billing/zoho/webhook', [
            'event_type' => 'subscription_activated',
        ]);

        $response->assertStatus(401)->assertJson([
            'success' => false,
        ]);
    }

}

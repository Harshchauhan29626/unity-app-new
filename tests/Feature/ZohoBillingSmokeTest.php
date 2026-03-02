<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            'https://www.zohoapis.in/billing/v1/plans*' => Http::response([
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
}

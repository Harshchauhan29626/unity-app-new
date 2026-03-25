<?php

namespace Tests\Feature;

use App\Models\CircleJoinRequest;
use App\Models\CircleSubscription;
use App\Models\User;
use App\Services\Circles\CircleJoinRequestPaymentSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CircleMultiMembershipFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchema();
    }

    public function test_circle_list_and_detail_return_categories(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $circleId = $this->uuid();
        $categoryId = 11;

        \DB::table('circles')->insert([
            'id' => $circleId,
            'name' => 'Alpha Circle',
            'slug' => 'alpha-circle',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('categories')->insert([
            'id' => $categoryId,
            'category_name' => 'Manufacturing',
            'sector' => 'Industrial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('circle_category_mappings')->insert([
            'circle_id' => $circleId,
            'category_id' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/circles')
            ->assertOk()
            ->assertJsonPath('data.items.0.categories.0.name', 'Manufacturing');

        $this->getJson('/api/v1/circles/' . $circleId)
            ->assertOk()
            ->assertJsonPath('data.categories.0.sector', 'Industrial');
    }

    public function test_join_request_accepts_valid_category_and_rejects_unmapped_category(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $circleId = $this->uuid();

        \DB::table('circles')->insert([
            'id' => $circleId,
            'name' => 'Beta Circle',
            'slug' => 'beta-circle',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('categories')->insert([
            ['id' => 21, 'category_name' => 'IT', 'sector' => 'Services', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'category_name' => 'Finance', 'sector' => 'Services', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('circle_category_mappings')->insert([
            'circle_id' => $circleId,
            'category_id' => 21,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/circle-join-requests', [
            'circle_id' => $circleId,
            'reason_for_joining' => 'Interested in joining',
            'category_id' => 21,
        ])->assertCreated()
            ->assertJsonPath('data.category_id', 21);

        $anotherUser = User::factory()->create();
        Sanctum::actingAs($anotherUser);

        $this->postJson('/api/v1/circle-join-requests', [
            'circle_id' => $circleId,
            'reason_for_joining' => 'Wrong category',
            'category_id' => 22,
        ])->assertStatus(422)
            ->assertJsonPath('errors.category_id.0', 'The selected category is not mapped to this circle.');
    }

    public function test_payment_success_maps_join_request_and_profile_returns_multi_circle_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        \DB::table('roles')->insert([
            'id' => $this->uuid(),
            'key' => 'member',
            'name' => 'Member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $circleOne = $this->uuid();
        $circleTwo = $this->uuid();

        \DB::table('circles')->insert([
            ['id' => $circleOne, 'name' => 'Circle One', 'slug' => 'circle-one', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $circleTwo, 'name' => 'Circle Two', 'slug' => 'circle-two', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('circle_members')->insert([
            'id' => $this->uuid(),
            'circle_id' => $circleTwo,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $joinRequest = CircleJoinRequest::query()->create([
            'user_id' => $user->id,
            'circle_id' => $circleOne,
            'status' => CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
            'requested_at' => now(),
        ]);

        $subscription = CircleSubscription::query()->create([
            'user_id' => $user->id,
            'circle_id' => $circleOne,
            'zoho_addon_code' => 'ADDON-1',
            'zoho_addon_name' => 'Circle One Plan',
            'zoho_subscription_id' => 'sub_1',
            'zoho_payment_id' => 'pay_1',
            'status' => 'active',
            'started_at' => now(),
            'paid_at' => now(),
            'expires_at' => now()->addMonths(12),
        ]);

        app(CircleJoinRequestPaymentSyncService::class)->syncPaidSubscription($subscription, [
            'payment_reference' => 'pay_1',
        ]);

        $joinRequest->refresh();
        $this->assertSame('paid', $joinRequest->payment_status);
        $this->assertNotNull($joinRequest->circle_subscription_id);

        $this->getJson('/api/v1/members/' . $user->id)
            ->assertOk()
            ->assertJsonPath('data.active_circle_id', $circleOne)
            ->assertJsonCount(2, 'data.circles')
            ->assertJsonPath('data.circle_join_requests.0.circle_id', $circleOne);
    }

    private function setUpSchema(): void
    {
        foreach ([
            'personal_access_tokens',
            'users',
            'circles',
            'categories',
            'circle_category_mappings',
            'circle_join_requests',
            'circle_subscriptions',
            'circle_members',
            'roles',
            'cities',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('membership_status')->nullable();
            $table->uuid('active_circle_id')->nullable();
            $table->string('active_circle_addon_code')->nullable();
            $table->string('active_circle_addon_name')->nullable();
            $table->uuid('active_circle_subscription_id')->nullable();
            $table->timestamp('circle_joined_at')->nullable();
            $table->timestamp('circle_expires_at')->nullable();
            $table->string('public_profile_slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('city')->nullable();
            $table->string('zoho_addon_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('category_name');
            $table->string('sector')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_category_mappings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('circle_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('role')->default('member');
            $table->uuid('role_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['circle_id', 'user_id']);
        });

        Schema::create('circle_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->string('zoho_subscription_id')->nullable();
            $table->string('zoho_payment_id')->nullable();
            $table->string('zoho_addon_code')->nullable();
            $table->string('zoho_addon_name')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('zoho_decrypted_hosted_page_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('raw_checkout_response')->nullable();
            $table->json('raw_webhook_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->uuid('circle_subscription_id')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_status')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->uuid('approved_membership_id')->nullable();
            $table->text('reason_for_joining')->nullable();
            $table->string('status');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('fee_marked_at')->nullable();
            $table->timestamp('fee_paid_at')->nullable();
            $table->json('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tokenable_type');
            $table->uuid('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function uuid(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}

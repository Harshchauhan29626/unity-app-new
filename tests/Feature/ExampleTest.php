<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Console\Commands\ExpireTrialUsers;
use App\Console\Commands\SyncMembershipExpiryFields;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }


    public function test_user_resource_returns_trial_membership_label(): void
    {
        $user = new User([
            'membership_status' => User::STATUS_FREE_TRIAL,
            'membership_ends_at' => Carbon::parse('2026-03-26T00:00:00Z'),
            'membership_expiry' => Carbon::parse('2026-03-22T01:36:00Z'),
        ]);

        $resource = (new UserResource($user))->toArray(request());

        $this->assertSame(User::STATUS_FREE_TRIAL, $resource['membership_status']);
        $this->assertSame('Free Trial Peer', $resource['membership_status_label']);
        $this->assertTrue($user->membership_ends_at->equalTo($resource['membership_expiry']));
    }

    public function test_user_model_syncs_membership_expiry_to_membership_ends_at(): void
    {
        $user = new User([
            'membership_ends_at' => Carbon::parse('2026-03-26T00:00:00Z'),
            'membership_expiry' => Carbon::parse('2026-03-22T01:36:00Z'),
        ]);

        $user->syncMembershipExpiryAttributes();

        $this->assertTrue($user->membership_ends_at->equalTo($user->membership_expiry));
    }

    public function test_sync_membership_expiry_command_name_is_registered(): void
    {
        $this->assertSame('users:sync-membership-expiry', (new SyncMembershipExpiryFields())->getName());
    }

    public function test_expire_trial_command_downgrades_only_expired_trial_users(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-23 12:00:00'));

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('public_profile_slug', 80)->nullable();
            $table->string('membership_status', 50)->default('visitor');
            $table->timestamp('membership_ends_at')->nullable();
            $table->timestamp('membership_expiry')->nullable();
            $table->timestamp('membership_starts_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        User::query()->create([
            'id' => (string) Str::uuid(),
            'membership_status' => User::STATUS_FREE_TRIAL,
            'membership_ends_at' => now()->copy()->subMinute(),
        ]);

        $activeTrial = User::query()->create([
            'id' => (string) Str::uuid(),
            'membership_status' => User::STATUS_FREE_TRIAL,
            'membership_ends_at' => now()->copy()->addMinute(),
        ]);

        $freePeer = User::query()->create([
            'id' => (string) Str::uuid(),
            'membership_status' => User::STATUS_FREE,
            'membership_ends_at' => now()->copy()->subMinute(),
        ]);

        Artisan::resolve(ExpireTrialUsers::class);
        $this->artisan('users:expire-trial')->assertExitCode(0);

        $expiredStatuses = User::query()->pluck('membership_status')->all();

        $this->assertContains(User::STATUS_FREE, $expiredStatuses);
        $this->assertSame(User::STATUS_FREE_TRIAL, $activeTrial->fresh()->membership_status);
        $this->assertSame(User::STATUS_FREE, $freePeer->fresh()->membership_status);

        Carbon::setTestNow();
    }

    public function test_member_resource_returns_extended_profile_fields(): void
    {
        $user = new User([
            'id' => '1f6a2c40-57b0-4b7b-8c5d-879f9d8f2ea7',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'display_name' => 'Jane Doe',
            'company_name' => 'Acme Inc',
            'designation' => 'Product Lead',
            'email' => 'jane@example.com',
            'phone' => '1234567890',
            'membership_status' => 'premium',
            'membership_expiry' => Carbon::parse('2025-01-01T00:00:00Z'),
            'coins_balance' => 150,
            'business_type' => 'b2b',
            'turnover_range' => '1-5Cr',
            'gender' => 'female',
            'dob' => Carbon::parse('1990-05-12'),
            'experience_years' => 10,
            'experience_summary' => 'Leading product teams',
            'short_bio' => 'Short bio',
            'long_bio_html' => '<p>Long bio</p>',
            'industry_tags' => ['it-services'],
            'skills' => ['sales'],
            'interests' => ['travel'],
            'target_regions' => ['IN'],
            'target_business_categories' => ['SaaS'],
            'hobbies_interests' => ['reading'],
            'leadership_roles' => ['founder'],
            'special_recognitions' => ['award'],
            'social_links' => ['linkedin' => 'https://linkedin.com/in/jane', 'facebook' => null],
            'profile_photo_file_id' => 'profile-file',
            'cover_photo_file_id' => 'cover-file',
            'address' => '123 Street',
            'state' => 'KA',
            'country' => 'India',
            'pincode' => '560001',
            'is_verified' => true,
            'is_sponsored_member' => false,
            'last_login_at' => Carbon::parse('2024-01-01T10:00:00Z'),
            'created_at' => Carbon::parse('2024-01-02T10:00:00Z'),
            'updated_at' => Carbon::parse('2024-02-02T10:00:00Z'),
        ]);

        $user->twitter = 'https://twitter.com/jane';

        $resource = (new UserResource($user))->toArray(request());

        $this->assertSame('Product Lead', $resource['designation']);
        $this->assertSame(['sales'], $resource['skills']);
        $this->assertSame('1990-05-12', $resource['dob']);
        $this->assertSame(url('/api/v1/files/cover-file'), $resource['cover_photo_url']);
        $this->assertSame('https://twitter.com/jane', $resource['social_links']['twitter']);
        $this->assertNull($resource['social_links']['youtube']);
        $this->assertSame('Short bio', $resource['bio']);
    }
}

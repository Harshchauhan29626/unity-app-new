<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'phone',
        'password',
        'password_hash',
        'company_name',
        'designation',
        'gender',
        'dob',
        'experience_years',
        'experience_summary',
        'city_id',
        'city',
        'skills',
        'interests',
        'social_links',
        'profile_photo_id',
        'cover_photo_id',
        'membership_status',
        'membership_expiry',
        'coins_balance',
        'profile_photo_url',
        'short_bio',
        'long_bio_html',
        'industry_tags',
        'business_type',
        'turnover_range',
        'introduced_by',
        'members_introduced_count',
        'influencer_stars',
        'target_regions',
        'target_business_categories',
        'hobbies_interests',
        'leadership_roles',
        'is_sponsored_member',
        'public_profile_slug',
        'special_recognitions',
        'gdpr_deleted_at',
        'anonymized_at',
        'is_gdpr_exported',
        'last_login_at',
        'profile_photo_file_id',
        'cover_photo_file_id',
        'zoho_customer_id',
        'zoho_subscription_id',
        'zoho_plan_code',
        'zoho_last_invoice_id',
        'membership_starts_at',
        'membership_ends_at',
        'last_payment_at',
    ];

    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'industry_tags' => 'array',
        'target_regions' => 'array',
        'target_business_categories' => 'array',
        'hobbies_interests' => 'array',
        'leadership_roles' => 'array',
        'special_recognitions' => 'array',
        'membership_expiry' => 'datetime',
        'gdpr_deleted_at' => 'datetime',
        'anonymized_at' => 'datetime',
        'last_login_at' => 'datetime',
        'membership_starts_at' => 'datetime',
        'membership_ends_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'dob' => 'date',
        'skills' => 'array',
        'interests' => 'array',
        'social_links' => 'array',
        'coins_balance' => 'integer',
        'is_sponsored_member' => 'boolean',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (empty($user->id)) {
                $user->id = Str::uuid()->toString();
            }

            if (empty($user->display_name)) {
                $user->display_name = trim($user->first_name . ' ' . ($user->last_name ?? ''));
            }

            if (empty($user->public_profile_slug)) {
                $base = Str::slug(
                    $user->display_name
                    ?: trim($user->first_name . ' ' . ($user->last_name ?? ''))
                    ?: $user->email
                    ?: 'user'
                );

                if ($base === '') {
                    $base = 'user';
                }

                $slug = $base;
                $i = 1;

                while (static::where('public_profile_slug', $slug)->exists()) {
                    $slug = $base . '-' . $i;
                    $i++;
                }

                $user->public_profile_slug = $slug;
            }
        });
    }

    public function links(): HasMany
    {
        return $this->hasMany(UserLink::class);
    }

    public function userLinks(): HasMany
    {
        return $this->hasMany(UserLink::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function cityRelation(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function introducedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'introduced_by');
    }

    public function foundedCircles(): HasMany
    {
        return $this->hasMany(Circle::class, 'founder_user_id');
    }

    public function circleMembers(): HasMany
    {
        return $this->hasMany(CircleMember::class);
    }

    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_members', 'user_id', 'circle_id')
            ->withPivot(['status', 'joined_at', 'deleted_at'])
            ->wherePivot('status', 'approved')
            ->wherePivotNull('deleted_at')
            ->orderByPivot('joined_at', 'desc');
    }

    public function adminDisplayName(): string
    {
        $displayName = trim((string) ($this->display_name ?? ''));

        if ($displayName !== '') {
            return $displayName;
        }

        $fullName = trim(trim((string) ($this->first_name ?? '')).' '.trim((string) ($this->last_name ?? '')));

        return $fullName !== '' ? $fullName : 'Unknown';
    }

    public function adminCompanyLabel(): string
    {
        $company = trim((string) ($this->company_name ?? ''));

        if ($company !== '') {
            return $company;
        }

        $businessName = trim((string) ($this->business_name ?? ''));

        return $businessName !== '' ? $businessName : 'No Company';
    }

    public function adminCityLabel(): string
    {
        $city = trim((string) ($this->city ?? ''));

        return $city !== '' ? $city : 'No City';
    }

    public function adminCircleLabel(): string
    {
        if ($this->relationLoaded('circleMembers')) {
            $name = trim((string) optional($this->circleMembers->first()?->circle)->name);
            return $name !== '' ? $name : 'No Circle';
        }

        if ($this->relationLoaded('circles')) {
            $name = trim((string) optional($this->circles->first())->name);
            return $name !== '' ? $name : 'No Circle';
        }

        try {
            $member = $this->circleMembers()
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->with(['circle:id,name'])
                ->orderByDesc('joined_at')
                ->first();

            $name = trim((string) optional($member?->circle)->name);

            return $name !== '' ? $name : 'No Circle';
        } catch (\Throwable $e) {
            return 'No Circle';
        }
    }

    public function adminFounderOptionLabel(): string
    {
        return implode(PHP_EOL, [
            $this->adminDisplayName(),
            $this->adminCompanyLabel(),
            $this->adminCityLabel(),
            $this->adminCircleLabel(),
        ]);
    }

    public function adminName(): string
    {
        $displayName = trim((string) ($this->display_name ?? ''));

        if ($displayName !== '') {
            return $displayName;
        }

        $fullName = trim(
            trim((string) ($this->first_name ?? '')).' '.trim((string) ($this->last_name ?? ''))
        );

        if ($fullName !== '') {
            return $fullName;
        }

        return $this->adminDisplayName();
    }

    public function adminCompany(): string
    {
        return $this->adminCompanyLabel();
    }

    public function adminCity(): string
    {
        return $this->adminCityLabel();
    }

    public function adminCircleName(): string
    {
        return $this->adminCircleLabel();
    }

    public function adminDisplayParts(): array
    {
        return [
            $this->adminName(),
            $this->adminCompany(),
            $this->adminCity(),
            $this->adminCircleName(),
        ];
    }

    public function adminDisplayLabel(): string
    {
        return implode(PHP_EOL, $this->adminDisplayParts());
    }

    public function adminDisplayInlineLabel(): string
    {
        return implode(' — ', $this->adminDisplayParts());
    }

    public function requestedConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'requester_id');
    }

    public function receivedConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'addressee_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function savedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_saves', 'user_id', 'post_id')->withTimestamps();
    }

    public function postComments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by_user_id');
    }

    public function eventRsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function relatedActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'related_user_id');
    }

    public function verifiedActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'verified_by_admin_id');
    }

    public function coinLedgers(): HasMany
    {
        return $this->hasMany(CoinLedger::class);
    }

    public function coinsLedger(): HasMany
    {
        return $this->hasMany(CoinsLedger::class, 'user_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function supportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class);
    }

    public function chatsInitiated(): HasMany
    {
        return $this->hasMany(Chat::class, 'user1_id');
    }

    public function chatsReceived(): HasMany
    {
        return $this->hasMany(Chat::class, 'user2_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function pushTokens(): HasMany
    {
        return $this->hasMany(UserPushToken::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_user_roles', 'user_id', 'role_id');
    }

    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(FileModel::class, 'uploader_user_id');
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_user_id');
    }

    public function referralLinks(): HasMany
    {
        return $this->hasMany(ReferralLink::class, 'referrer_user_id');
    }

    public function visitorLeads(): HasMany
    {
        return $this->hasMany(VisitorLead::class, 'converted_user_id');
    }

    public function dataExports(): HasMany
    {
        return $this->hasMany(DataExport::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function profilePhotoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'profile_photo_file_id');
    }

    public function coverPhotoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_photo_file_id');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (! $this->profile_photo_file_id) {
            return null;
        }

        return url('/api/v1/files/' . $this->profile_photo_file_id);
    }

    public function isFreeMember(): bool
    {
        return (string) $this->membership_status === 'free_peer';
    }

    public function isPaidMember(): bool
    {
        return ! $this->isFreeMember();
    }

}

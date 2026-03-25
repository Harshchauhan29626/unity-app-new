<?php

namespace App\Models;

use App\Support\AdminAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CircleJoinRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING_CD_APPROVAL = 'pending_cd_approval';
    public const STATUS_PENDING_ID_APPROVAL = 'pending_id_approval';
    public const STATUS_PENDING_CIRCLE_FEE = 'pending_circle_fee';
    public const STATUS_CIRCLE_MEMBER = 'circle_member';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED_BY_CD = 'rejected_by_cd';
    public const STATUS_REJECTED_BY_ID = 'rejected_by_id';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING_CD_APPROVAL,
        self::STATUS_PENDING_ID_APPROVAL,
        self::STATUS_PENDING_CIRCLE_FEE,
        self::STATUS_CIRCLE_MEMBER,
        self::STATUS_PAID,
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'circle_id',
        'reason_for_joining',
        'status',
        'category_id',
        'circle_subscription_id',
        'payment_id',
        'payment_reference',
        'payment_status',
        'paid_at',
        'approved_membership_id',
        'requested_at',
        'cd_approved_by',
        'cd_approved_at',
        'cd_rejected_by',
        'cd_rejected_at',
        'cd_rejection_reason',
        'id_approved_by',
        'id_approved_at',
        'id_rejected_by',
        'id_rejected_at',
        'id_rejection_reason',
        'fee_marked_at',
        'fee_paid_at',
        'notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'cd_approved_at' => 'datetime',
        'cd_rejected_at' => 'datetime',
        'id_approved_at' => 'datetime',
        'id_rejected_at' => 'datetime',
        'fee_marked_at' => 'datetime',
        'fee_paid_at' => 'datetime',
        'paid_at' => 'datetime',
        'notes' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (! $request->id) {
                $request->id = (string) Str::uuid();
            }
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING_CD_APPROVAL,
            self::STATUS_PENDING_ID_APPROVAL,
            self::STATUS_PENDING_CIRCLE_FEE,
        ]);
    }

    public function scopeForCircle(Builder $query, string $circleId): Builder
    {
        return $query->where('circle_id', $circleId);
    }

    public function scopeVisibleToAdminUser(Builder $query, ?AdminUser $adminUser): Builder
    {
        if (! $adminUser) {
            return $query->whereRaw('1=0');
        }

        if (AdminAccess::isSuper($adminUser)) {
            return $query;
        }

        $allowedCircleIds = AdminAccess::allowedCircleIds($adminUser);

        if ($allowedCircleIds === []) {
            return $query->whereRaw('1=0');
        }

        return $query->whereIn('circle_id', $allowedCircleIds);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function circleSubscription(): BelongsTo
    {
        return $this->belongsTo(CircleSubscription::class, 'circle_subscription_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function cdApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cd_approved_by');
    }

    public function cdRejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cd_rejected_by');
    }

    public function idApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_approved_by');
    }

    public function idRejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_rejected_by');
    }
}

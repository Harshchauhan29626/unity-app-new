<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CircleMember extends Model
{
    protected $table = 'circle_members';

    use HasFactory;
    use SoftDeletes;

    public const ROLE_OPTIONS = [
        'member',
        'founder',
        'director',
        'chair',
        'vice_chair',
        'secretary',
        'committee_leader',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circle_id',
        'user_id',
        'role',
        'role_id',
        'status',
        'joined_via',
        'payment_id',
        'paid_at',
        'meta',
        'substitute_count',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public static function roleOptions(): array
    {
        return self::ROLE_OPTIONS;
    }

    protected static function booted(): void
    {
        static::saving(function (CircleMember $member): void {
            if (! $member->role) {
                return;
            }

            if ($member->role_id && ! $member->isDirty('role')) {
                return;
            }

            try {
                $member->role_id = Role::mustIdByKey($member->role);
            } catch (RuntimeException $exception) {
                Log::error('Circle member role key missing in roles table.', [
                    'circle_member_id' => $member->id,
                    'circle_id' => $member->circle_id,
                    'user_id' => $member->user_id,
                    'role' => $member->role,
                ]);

                throw $exception;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CircleJoinPayment::class, 'payment_id');
    }

    public function roleRef(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}

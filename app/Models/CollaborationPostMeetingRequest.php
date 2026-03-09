<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CollaborationPostMeetingRequest extends Model
{
    use HasFactory;

    protected $table = 'collaboration_post_meeting_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'id',
        'post_id',
        'from_user_id',
        'to_user_id',
        'proposed_at',
        'place',
        'note',
        'status',
    ];

    protected $casts = [
        'proposed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $meeting): void {
            if (blank($meeting->id)) {
                $meeting->id = (string) Str::uuid();
            }

            $meeting->status ??= self::STATUS_PENDING;
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CollaborationPost::class, 'post_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}

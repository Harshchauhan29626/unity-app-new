<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CollaborationPostInterest extends Model
{
    use HasFactory;

    protected $table = 'collaboration_post_interests';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'post_id',
        'from_user_id',
        'to_user_id',
        'message',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $interest): void {
            if (blank($interest->id)) {
                $interest->id = (string) Str::uuid();
            }
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

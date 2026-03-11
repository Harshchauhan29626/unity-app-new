<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CircleChatMessageRead extends Model
{
    use HasFactory;

    protected $table = 'circle_chat_message_reads';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $read): void {
            if (blank($read->id)) {
                $read->id = (string) Str::uuid();
            }
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CircleChatMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

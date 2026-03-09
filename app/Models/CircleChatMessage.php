<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CircleChatMessage extends Model
{
    use HasFactory;

    protected $table = 'circle_chat_messages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circle_id',
        'sender_id',
        'message_type',
        'message_text',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'thumbnail_path',
        'reply_to_message_id',
        'is_deleted_for_all',
        'deleted_for_all_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_deleted_for_all' => 'boolean',
        'deleted_for_all_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if (blank($message->id)) {
                $message->id = (string) Str::uuid();
            }
        });
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(CircleChatMessageRead::class, 'message_id');
    }

    public function deletions(): HasMany
    {
        return $this->hasMany(CircleChatMessageDeletion::class, 'message_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'circle_chat_message_reads', 'message_id', 'user_id')
            ->withPivot('read_at')
            ->withTimestamps();
    }
}

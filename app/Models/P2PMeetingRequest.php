<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class P2PMeetingRequest extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'p2p_meeting_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'requester_id',
        'invitee_id',
        'scheduled_at',
        'place',
        'message',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'responded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralData extends Model
{
    protected $table = 'referraldata';

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'referral_code',
        'referrer_email',
        'coins',
        'reward_status',
        'used_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'coins' => 'integer',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}

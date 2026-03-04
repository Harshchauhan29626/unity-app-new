<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircleJoinPayment extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'circle_id',
        'circle_fee_id',
        'provider',
        'status',
        'zoho_hostedpage_id',
        'zoho_hostedpage_url',
        'zoho_subscription_id',
        'zoho_invoice_id',
        'zoho_payment_id',
        'zoho_addon_id',
        'amount',
        'currency',
        'raw_payload',
        'paid_at',
        'failed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_payload' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function circleFee(): BelongsTo
    {
        return $this->belongsTo(CircleFee::class, 'circle_fee_id');
    }
}

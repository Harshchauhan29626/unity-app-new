<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CircleSubscription extends Model
{
    use HasFactory;

    protected $table = 'circle_subscriptions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'circle_id',
        'zoho_customer_id',
        'zoho_subscription_id',
        'zoho_payment_id',
        'zoho_hosted_page_id',
        'zoho_decrypted_hosted_page_id',
        'hostedpage_id',
        'decrypted_hosted_page_id',
        'reference_id',
        'zoho_addon_id',
        'zoho_addon_code',
        'zoho_addon_name',
        'amount',
        'currency_code',
        'status',
        'started_at',
        'expires_at',
        'paid_at',
        'raw_checkout_response',
        'raw_webhook_payload',
        'raw_final_payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'raw_checkout_response' => 'array',
        'raw_webhook_payload' => 'array',
        'raw_final_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (empty($subscription->id)) {
                $subscription->id = (string) Str::uuid();
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
}

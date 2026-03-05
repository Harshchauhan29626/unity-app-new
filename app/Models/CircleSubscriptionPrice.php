<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CircleSubscriptionPrice extends Model
{
    use HasFactory;

    protected $table = 'circle_subscription_prices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circle_id',
        'duration_months',
        'price',
        'currency',
        'zoho_addon_id',
        'zoho_addon_code',
        'zoho_addon_name',
        'zoho_addon_interval_unit',
        'payload',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_months' => 'integer',
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CircleSubscriptionPrice $price): void {
            if (! $price->id) {
                $price->id = (string) Str::uuid();
            }
        });
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ZohoCircleAddon extends Model
{
    use HasFactory;

    protected $table = 'zoho_circle_addons';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circle_id',
        'interval_type',
        'price',
        'zoho_addon_id',
        'zoho_addon_code',
        'product_id',
        'checkout_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $addon): void {
            if (empty($addon->id)) {
                $addon->id = Str::uuid()->toString();
            }
        });
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }
}

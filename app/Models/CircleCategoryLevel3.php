<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircleCategoryLevel3 extends Model
{
    use HasFactory;

    protected $table = 'circle_category_level3';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function circleCategory(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, 'circle_category_id');
    }

    public function level2Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategoryLevel2::class, 'level2_id');
    }
}

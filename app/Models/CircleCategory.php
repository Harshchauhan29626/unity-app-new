<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CircleCategory extends Model
{
    use HasFactory;

    protected $table = 'circle_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'level',
        'circle_key',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
    ];

    public function circleMappings(): HasMany
    {
        return $this->hasMany(CircleCategoryMapping::class, 'category_id');
    }

    public function level2Categories(): HasMany
    {
        return $this->hasMany(CircleCategoryLevel2::class, 'circle_category_id');
    }

    public function level3Categories(): HasMany
    {
        return $this->hasMany(CircleCategoryLevel3::class, 'circle_category_id');
    }

    public function level4Categories(): HasMany
    {
        return $this->hasMany(CircleCategoryLevel4::class, 'circle_category_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_name',
        'sector',
        'remarks',
    ];

    public function circleMappings(): HasMany
    {
        return $this->hasMany(CircleCategoryMapping::class);
    }

    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_category_mappings', 'category_id', 'circle_id')
            ->withTimestamps();
    }

    public function eventGalleries(): HasMany
    {
        return $this->hasMany(EventGallery::class);
    }
}

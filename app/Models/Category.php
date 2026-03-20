<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sector_id',
        'category_name',
        'sector',
        'remarks',
    ];

    public function getNameAttribute(): ?string
    {
        return $this->attributes['name'] ?? $this->attributes['category_name'] ?? null;
    }

    public function getSectorIdAttribute(): ?string
    {
        return $this->attributes['sector_id'] ?? $this->attributes['sector'] ?? null;
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

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
        return $this->hasMany(EventGallery::class, 'circle_category_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventGallery extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'event_galleries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'event_name',
        'event_date',
        'description',
        'category_id',
        'category',
        'created_by_admin_id',
    ];

    protected $casts = [
        'event_date' => 'date',
        'category_id' => 'integer',
    ];


    public function categoryRef(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(EventGalleryMedia::class, 'event_gallery_id');
    }

    public function getCoverUrlAttribute(): ?string
    {
        $media = $this->relationLoaded('media')
            ? $this->media->firstWhere('media_type', 'image')
            : $this->media()
                ->where('media_type', 'image')
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->first();

        if (! $media || ! $media->file_id) {
            return null;
        }

        return '/api/v1/files/' . $media->file_id;
    }
}

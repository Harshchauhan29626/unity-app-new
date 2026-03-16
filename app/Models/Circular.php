<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Circular extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const CATEGORY_OPTIONS = [
        'event',
        'announcement',
        'update',
        'opportunity',
        'recognition',
        'policy',
    ];

    public const PRIORITY_OPTIONS = [
        'normal',
        'important',
        'urgent',
    ];

    public const AUDIENCE_OPTIONS = [
        'all_members',
        'circle_members',
        'fempreneur',
        'greenpreneur',
    ];

    protected $table = 'circulars';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'title',
        'summary',
        'category',
        'priority',
        'featured_image_file_id',
        'featured_image_url',
        'content',
        'attachment_file_id',
        'attachment_url',
        'video_url',
        'audience_type',
        'city_id',
        'circle_id',
        'send_push_notification',
        'allow_comments',
        'is_pinned',
        'status',
        'publish_date',
        'expiry_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'send_push_notification' => 'boolean',
        'allow_comments' => 'boolean',
        'is_pinned' => 'boolean',
        'publish_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    public function featuredImageFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'featured_image_file_id');
    }

    public function attachmentFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'attachment_file_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('publish_date', '<=', now());
    }

    public function scopeVisibleNow(Builder $query): Builder
    {
        return $query->active()
            ->published()
            ->where(function (Builder $query): void {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>', now());
            });
    }

    public function scopeOrderedForFeed(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'important' THEN 2 ELSE 3 END")
            ->orderByDesc('publish_date');
    }

    public function getFeaturedImageResolvedUrlAttribute(): ?string
    {
        if ($this->featured_image_file_id) {
            return url('/api/v1/files/' . $this->featured_image_file_id);
        }

        return $this->featured_image_url;
    }

    public function getAttachmentResolvedUrlAttribute(): ?string
    {
        if ($this->attachment_file_id) {
            return url('/api/v1/files/' . $this->attachment_file_id);
        }

        return $this->attachment_url;
    }
}

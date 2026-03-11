<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Circle extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_OPTIONS = ['pending', 'active', 'archived'];
    public const TYPE_OPTIONS = ['public', 'private'];
    public const MEETING_MODE_OPTIONS = ['online', 'offline', 'hybrid'];
    public const MEETING_FREQUENCY_OPTIONS = ['monthly', 'quarterly'];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'purpose',
        'announcement',
        'founder_user_id',
        'director_user_id',
        'industry_director_user_id',
        'ded_user_id',
        'template_id',
        'status',
        'calendar',
        'city_id',
        'city',
        'industry_tags',
        'meeting_mode',
        'meeting_frequency',
        'meeting_repeat',
        'launch_date',
        'cover_file_id',
        'referral_score',
        'visitor_count',
        'type',
        'country',
        'zoho_addon_code',
        'zoho_addon_id',
        'zoho_addon_name',
        'circle_price_amount',
        'circle_price_currency',
        'circle_duration_months',
    ];

    protected $casts = [
        'calendar' => 'array',
        'industry_tags' => 'array',
        'meeting_repeat' => 'array',
        'launch_date' => 'date',
        'circle_price_amount' => 'decimal:2',
        'circle_duration_months' => 'integer',
    ];

    protected $appends = ['cover_image_url', 'city_display'];

    protected static function booted()
    {
        static::creating(function (Circle $circle) {
            if (empty($circle->id)) {
                $circle->id = Str::uuid()->toString();
            }

            if (empty($circle->slug)) {
                $base = Str::slug($circle->name ?: 'circle');
                if ($base === '') {
                    $base = 'circle';
                }

                $circle->slug = static::generateUniqueSlug($base);
            }

            if (empty($circle->status)) {
                $circle->status = 'pending';
            }
        });
    }


    public function calendarGet(string $path, $default = null)
    {
        $calendar = is_array($this->calendar) ? $this->calendar : [];

        return data_get($calendar, $path, $default);
    }

    public function calendarSet(string $path, $value): void
    {
        $calendar = is_array($this->calendar) ? $this->calendar : [];
        data_set($calendar, $path, $value);
        $this->calendar = $calendar;
    }

    public function getMeetingModeAttribute(): ?string
    {
        $value = $this->calendarGet('settings.meeting_mode');
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function getMeetingFrequencyAttribute(): ?string
    {
        $value = $this->calendarGet('settings.meeting_frequency');
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function getLaunchDateAttribute($value): ?string
    {
        if ($value) {
            return is_string($value) ? $value : (string) $value;
        }

        $calendarDate = $this->calendarGet('settings.launch_date');

        return is_string($calendarDate) && trim($calendarDate) !== '' ? trim($calendarDate) : null;
    }

    public function getDirectorUserIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('leadership.director_user_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getIndustryDirectorUserIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('leadership.industry_director_user_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getDedUserIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('leadership.ded_user_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getCoverFileIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('cover.file_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getMeetingScheduleAttribute(): array
    {
        $value = $this->calendarGet('meeting_schedule', []);

        return is_array($value) ? $value : [];
    }

    public function getCityDisplayAttribute(): ?string
    {
        if ($this->relationLoaded('cityRef') && $this->cityRef) {
            return $this->cityRef->name ?? null;
        }

        $raw = $this->getAttribute('city');

        if ($raw === null || $raw === '') {
            if (! empty($this->getAttribute('city_id'))) {
                $city = $this->cityRef()->first();

                return $city?->name;
            }

            return null;
        }

        if (is_array($raw)) {
            return $raw['name'] ?? $raw['district'] ?? null;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);

            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $decoded = json_decode($trimmed, true);

                if (is_array($decoded)) {
                    return $decoded['name'] ?? $decoded['district'] ?? null;
                }
            }

            return $raw;
        }

        return null;
    }

    public static function normalizeCityPayload(string $cityName, ?array $existing = null): array
    {
        $existing = is_array($existing) ? $existing : [];
        $existing['name'] = $cityName;

        return $existing;
    }

    public function founder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'founder_user_id');
    }

    public function founderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'founder_user_id');
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_user_id');
    }

    public function industryDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'industry_director_user_id');
    }

    public function ded(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ded_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CircleTemplate::class, 'template_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }


    public function cityRef(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }


    public function chatMessages(): HasMany
    {
        return $this->hasMany(CircleChatMessage::class, 'circle_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CircleMember::class, 'circle_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CircleMember::class);
    }

    public function circleSubscriptions(): HasMany
    {
        return $this->hasMany(CircleSubscription::class, 'circle_id');
    }


    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'circle_members', 'circle_id', 'user_id')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }


    public function coverFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_file_id) {
            return null;
        }

        if ($this->relationLoaded('coverFile') && $this->coverFile && isset($this->coverFile->url)) {
            return $this->coverFile->url;
        }

        return url('/api/v1/files/' . $this->cover_file_id);
    }

    public static function generateUniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'circle';
        }

        $slug = $base;
        $i = 1;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}

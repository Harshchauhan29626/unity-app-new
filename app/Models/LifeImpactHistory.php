<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LifeImpactHistory extends Model
{
    use HasFactory;

    private const ACTIVITY_TYPE_IMPACT_MAP = [
        'business_deal' => 5,
        'testimonial' => 5,
        'referral' => 1,
        'visitor_registration' => 1,
    ];

    protected $table = 'life_impact_histories';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'triggered_by_user_id',
        'activity_type',
        'activity_id',
        'impact_value',
        'life_impacted',
        'counted_in_total',
        'impact_category',
        'action_key',
        'action_label',
        'remarks',
        'title',
        'description',
        'meta',
    ];

    protected $casts = [
        'impact_value' => 'integer',
        'life_impacted' => 'integer',
        'counted_in_total' => 'boolean',
        'meta' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $schema = (string) config('database.connections.' . config('database.default') . '.search_path', 'public');
        $schema = trim((string) explode(',', $schema)[0], " \t\n\r\0\x0B\"");

        if ($schema !== '') {
            $this->table = $schema . '.life_impact_histories';
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function triggeredByUser()
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function resolveImpactValue(): int
    {
        if (array_key_exists('impact_value', $this->attributes) && $this->attributes['impact_value'] !== null) {
            return (int) $this->attributes['impact_value'];
        }

        $metaImpactValue = data_get($this->meta, 'impact_value');
        if (is_numeric($metaImpactValue)) {
            return (int) $metaImpactValue;
        }

        return (int) (self::ACTIVITY_TYPE_IMPACT_MAP[(string) $this->activity_type] ?? 0);
    }

    public function resolveImpactValueSource(): string
    {
        if (array_key_exists('impact_value', $this->attributes) && $this->attributes['impact_value'] !== null) {
            return 'column:impact_value';
        }

        if (is_numeric(data_get($this->meta, 'impact_value'))) {
            return 'meta:impact_value';
        }

        return array_key_exists((string) $this->activity_type, self::ACTIVITY_TYPE_IMPACT_MAP)
            ? 'mapping:activity_type'
            : 'default:zero';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircleMemberCategorySelection extends Model
{
    use HasFactory;

    protected $table = 'circle_member_category_selections';

    protected $fillable = [
        'circle_member_id',
        'user_id',
        'circle_id',
        'level1_category_id',
        'level2_category_id',
        'level3_category_id',
        'level4_category_id',
    ];

    public function circleMember(): BelongsTo
    {
        return $this->belongsTo(CircleMember::class, 'circle_member_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function level1Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, 'level1_category_id');
    }

    public function level2Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategoryLevel2::class, 'level2_category_id');
    }

    public function level3Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategoryLevel3::class, 'level3_category_id');
    }

    public function level4Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategoryLevel4::class, 'level4_category_id');
    }
}

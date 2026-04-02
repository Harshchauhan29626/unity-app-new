<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PartnerWithUsSubmission extends Model
{
    use HasUuids;

    protected $table = 'partner_with_us_submissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'full_name',
        'mobile_number',
        'email_id',
        'city',
        'brand_or_company_name',
        'website_or_social_media_link',
        'industry',
        'about_your_business',
        'partnership_goal',
        'why_partner_with_peers_global',
        'status',
        'notes',
    ];
}

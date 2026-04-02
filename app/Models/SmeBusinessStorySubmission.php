<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SmeBusinessStorySubmission extends Model
{
    use HasUuids;

    protected $table = 'sme_business_story_submissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'full_name',
        'email',
        'contact_number',
        'business_name',
        'company_introduction',
        'co_founders_and_partners_details',
        'status',
        'notes',
    ];
}

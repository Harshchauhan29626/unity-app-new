<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeadershipCertificationSubmission extends Model
{
    use HasUuids;

    protected $table = 'leadership_certification_submissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'full_name',
        'business_name',
        'email',
        'contact_no',
        'status',
        'notes',
    ];
}

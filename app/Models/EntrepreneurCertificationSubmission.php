<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EntrepreneurCertificationSubmission extends Model
{
    use HasUuids;

    protected $table = 'entrepreneur_certification_submissions';

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

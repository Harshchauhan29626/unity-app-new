<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BecomeMentorSubmission extends Model
{
    use HasUuids;

    protected $table = 'become_mentor_submissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'city',
        'linkedin_profile',
        'status',
        'notes',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BecomeSpeakerSubmission extends Model
{
    use HasUuids;

    protected $table = 'become_speaker_submissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'city',
        'linkedin_profile_url',
        'company_name',
        'brief_bio',
        'topics_to_speak_on',
        'image_file_id',
        'status',
        'notes',
    ];
}

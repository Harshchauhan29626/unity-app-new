<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GenerateReferralCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'regenerate' => ['nullable', 'boolean'],
        ];
    }
}


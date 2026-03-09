<?php

namespace App\Http\Requests\Requirements;

use Illuminate\Foundation\Http\FormRequest;

class InterestRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'in:timeline_comment,timeline_tag,interest_button'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}

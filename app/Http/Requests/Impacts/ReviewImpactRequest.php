<?php

namespace App\Http\Requests\Impacts;

use Illuminate\Foundation\Http\FormRequest;

class ReviewImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

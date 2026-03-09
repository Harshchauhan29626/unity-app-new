<?php

namespace App\Http\Requests\Requirements;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'media' => ['nullable', 'array'],
            'media.*.type' => ['nullable', 'string', 'max:50'],
            'media.*.file_id' => ['nullable', 'uuid'],
            'region_filter' => ['nullable', 'array'],
            'region_filter.*' => ['string', 'max:100'],
            'category_filter' => ['nullable', 'array'],
            'category_filter.*' => ['string', 'max:100'],
        ];
    }
}

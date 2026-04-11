<?php

namespace App\Http\Requests\Api\CircleJoinRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCircleJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'circle_id' => ['required', 'uuid', 'exists:circles,id'],
            'reason_for_joining' => ['nullable', 'string', 'max:2000'],
            'level1_category_id' => ['nullable', 'integer', 'exists:circle_categories,id'],
            'level2_category_id' => ['nullable', 'integer', 'exists:circle_category_level2,id'],
            'level3_category_id' => ['nullable', 'integer', 'exists:circle_category_level3,id'],
            'level4_category_id' => ['nullable', 'integer', 'exists:circle_category_level4,id'],
        ];
    }
}

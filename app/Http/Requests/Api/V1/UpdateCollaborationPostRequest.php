<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Industry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollaborationPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('scope') !== 'international') {
            $this->merge(['countries_of_interest' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'collaboration_type' => ['sometimes', 'required', Rule::in(StoreCollaborationPostRequest::COLLABORATION_TYPES)],
            'title' => ['sometimes', 'required', 'string', 'max:80'],
            'description' => ['sometimes', 'required', 'string', 'min:500'],
            'scope' => ['sometimes', 'required', Rule::in(StoreCollaborationPostRequest::SCOPES)],
            'countries_of_interest' => ['nullable', 'array', 'required_if:scope,international', 'min:1'],
            'countries_of_interest.*' => ['string', 'max:100'],
            'preferred_model' => ['nullable', Rule::in(StoreCollaborationPostRequest::MODELS)],
            'industry_id' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('industries', 'id')->where(fn ($q) => $q->where('is_active', true)),
                function ($attribute, $value, $fail): void {
                    $hasChildren = Industry::query()->where('parent_id', $value)->where('is_active', true)->exists();
                    if ($hasChildren) {
                        $fail('Please select a leaf industry.');
                    }
                },
            ],
            'business_stage' => ['sometimes', 'required', Rule::in(StoreCollaborationPostRequest::BUSINESS_STAGES)],
            'years_in_operation' => ['sometimes', 'required', Rule::in(StoreCollaborationPostRequest::YEARS)],
            'urgency' => ['sometimes', 'required', Rule::in(StoreCollaborationPostRequest::URGENCIES)],
        ];
    }
}

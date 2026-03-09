<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivitiesExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_type' => ['required', Rule::in([
                'testimonials',
                'referrals',
                'business_deals',
                'p2p_meetings',
                'requirements',
                'become_a_leader',
                'recommend_peer',
                'register_visitor',
                'summary',
            ])],
            'scope' => ['required', Rule::in(['selected', 'all'])],
            'selected_member_ids' => ['nullable', 'array'],
            'selected_member_ids.*' => ['uuid'],
            'q' => ['nullable', 'string'],
            'membership_status' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'circle_id' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('selected_member_ids', ['required', 'min:1'], function ($input) {
            return ($input->scope ?? null) === 'selected';
        });
    }

    public function messages(): array
    {
        return [
            'selected_member_ids.required' => 'Please select at least one peer.',
            'selected_member_ids.min' => 'Please select at least one peer.',
        ];
    }
}

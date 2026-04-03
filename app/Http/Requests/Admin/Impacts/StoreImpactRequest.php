<?php

namespace App\Http\Requests\Admin\Impacts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('date')) {
            $this->merge(['date' => now()->toDateString()]);
        }
    }

    public function rules(): array
    {
        $actions = (array) config('impact.actions', []);

        return [
            'date' => ['required', 'date'],
            'action' => ['required', 'string', Rule::in($actions)],
            'impacted_peer_id' => ['required', 'uuid', 'exists:users,id'],
            'story_to_share' => ['required', 'string', 'max:5000'],
            'additional_remarks' => ['nullable', 'string', 'max:2000'],
            'life_impacted' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

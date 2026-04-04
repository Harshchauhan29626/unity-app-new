<?php

namespace App\Http\Requests\Admin\Impacts;

use App\Models\Impact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $actions = Impact::availableActions();

        return [
            'date' => ['required', 'date'],
            'action' => ['required', 'string', Rule::in($actions)],
            'impacted_peer_id' => ['required', 'uuid', 'exists:users,id'],
            'story_to_share' => ['required', 'string', 'max:5000'],
            'additional_remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('impacted_peer_id') === (string) $this->user('admin')?->getAuthIdentifier()) {
                $validator->errors()->add('impacted_peer_id', 'You cannot create an impact for yourself.');
            }
        });
    }
}

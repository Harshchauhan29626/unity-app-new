<?php

namespace App\Http\Requests\Impacts;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ((string) $this->user()?->id === (string) $this->input('impacted_peer_id')) {
                $validator->errors()->add('impacted_peer_id', 'You cannot submit impact for yourself.');
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}

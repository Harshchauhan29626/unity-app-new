<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCircleFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fee = $this->route('fee');

        return [
            'interval_key' => [
                'sometimes',
                'string',
                Rule::in(['monthly', 'quarterly', 'half_yearly', 'yearly']),
                Rule::unique('circle_fees', 'interval_key')
                    ->ignore($fee?->id)
                    ->where(fn ($q) => $q->where('circle_id', $fee?->circle_id)->whereNull('deleted_at')),
            ],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

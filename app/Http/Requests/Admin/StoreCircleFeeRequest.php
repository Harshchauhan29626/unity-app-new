<?php

namespace App\Http\Requests\Admin;

use App\Models\Circle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCircleFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $circle = $this->route('circle');
        $circleId = $circle instanceof Circle ? (string) $circle->id : (string) $circle;

        return [
            'interval_key' => [
                'required',
                'string',
                Rule::in(['monthly', 'quarterly', 'half_yearly', 'yearly']),
                Rule::unique('circle_fees', 'interval_key')->where(fn ($q) => $q->where('circle_id', $circleId)->whereNull('deleted_at')),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

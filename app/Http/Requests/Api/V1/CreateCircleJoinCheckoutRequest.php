<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateCircleJoinCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'interval_key' => ['nullable', 'string', 'in:monthly,quarterly,half_yearly,yearly', 'required_without:circle_fee_id'],
            'circle_fee_id' => ['nullable', 'uuid', 'required_without:interval_key'],
        ];
    }
}

<?php

namespace App\Http\Requests\CoinClaims;

use Illuminate\Foundation\Http\FormRequest;

class RejectCoinClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('admin_note') && ! $this->has('admin_notes')) {
            $this->merge([
                'admin_notes' => $this->input('admin_note'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

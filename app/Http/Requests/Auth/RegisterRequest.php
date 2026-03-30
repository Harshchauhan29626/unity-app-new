<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('referral_code')) {
            $this->merge([
                'referral_code' => strtoupper(trim((string) $this->input('referral_code'))),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],

            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],

            // PHONE IS REQUIRED + UNIQUE TO AVOID DB UNIQUE VIOLATION
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],

            // PASSWORD WITH CONFIRMATION
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // NEW OPTIONAL FIELDS FOR REGISTRATION
            'company_name' => ['nullable', 'string', 'max:255'],
            'designation'  => ['nullable', 'string', 'max:255'],
            'referral_code' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[A-Z]+\d{4}$/',
                Rule::exists('referral_links', 'referral_code'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already registered.',
            'referral_code.regex' => 'Referral code format is invalid.',
            'referral_code.exists' => 'The selected referral code is invalid.',
        ];
    }
}

<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $incomingReferralCode = $this->input('referral_code');

        if (blank($incomingReferralCode) && $this->has('referralCode')) {
            $incomingReferralCode = $this->input('referralCode');
        }

        $level1 = $this->input('level_1_category_id', $this->input('level1_category_id'));
        $level2 = $this->input('level_2_category_id', $this->input('level2_category_id'));
        $level3 = $this->input('level_3_category_id', $this->input('level3_category_id'));
        $level4 = $this->input('level_4_category_id', $this->input('level4_category_id', $this->input('category_id')));

        $payload = [
            'level_1_category_id' => $level1,
            'level_2_category_id' => $level2,
            'level_3_category_id' => $level3,
            'level_4_category_id' => $level4,
            // keep legacy keys populated for backward compatibility with existing code paths
            'level1_category_id' => $level1,
            'level2_category_id' => $level2,
            'level3_category_id' => $level3,
            'level4_category_id' => $level4,
        ];

        if (! blank($incomingReferralCode)) {
            $payload['referral_code'] = strtoupper(trim((string) $incomingReferralCode));
        }

        $this->merge($payload);
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
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'level_1_category_id' => ['nullable', 'integer', 'exists:circle_categories,id'],
            'level_2_category_id' => ['nullable', 'integer', 'exists:circle_category_level2,id'],
            'level_3_category_id' => ['nullable', 'integer', 'exists:circle_category_level3,id'],
            'level_4_category_id' => ['nullable', 'integer', 'exists:circle_category_level4,id'],
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

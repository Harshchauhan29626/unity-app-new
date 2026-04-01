<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
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
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already registered.',
        ];
    }
}

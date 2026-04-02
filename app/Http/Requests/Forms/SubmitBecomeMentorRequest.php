<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class SubmitBecomeMentorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->trimValue($this->input('first_name')),
            'last_name' => $this->trimValue($this->input('last_name')),
            'email' => $this->trimValue($this->input('email')),
            'phone' => $this->trimValue($this->input('phone')),
            'city' => $this->trimValue($this->input('city')),
            'linkedin_profile' => $this->trimValue($this->input('linkedin_profile')),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:150'],
            'linkedin_profile' => ['required', 'url', 'max:500'],
        ];
    }

    private function trimValue(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}

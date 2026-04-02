<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSmeBusinessStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->trimValue($this->input('full_name')),
            'email' => $this->trimValue($this->input('email')),
            'contact_number' => $this->trimValue($this->input('contact_number')),
            'business_name' => $this->trimValue($this->input('business_name')),
            'company_introduction' => $this->trimValue($this->input('company_introduction')),
            'co_founders_and_partners_details' => $this->trimValue($this->input('co_founders_and_partners_details')),
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'business_name' => ['required', 'string', 'max:255'],
            'company_introduction' => ['required', 'string'],
            'co_founders_and_partners_details' => ['nullable', 'string'],
        ];
    }

    private function trimValue(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}

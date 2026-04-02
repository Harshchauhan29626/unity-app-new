<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class SubmitBecomeSpeakerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        Log::info('BecomeSpeakerRequest incoming payload', [
            'headers' => $this->headers->all(),
            'all' => $this->all(),
            'all_files' => array_keys($this->allFiles()),
            'content_type' => $this->header('Content-Type'),
        ]);

        $this->merge([
            'first_name' => $this->trimValue($this->input('first_name')),
            'last_name' => $this->trimValue($this->input('last_name')),
            'email' => $this->trimValue($this->input('email')),
            'phone' => $this->trimValue($this->input('phone')),
            'city' => $this->trimValue($this->input('city')),
            'linkedin_profile_url' => $this->trimValue($this->input('linkedin_profile_url')),
            'company_name' => $this->trimValue($this->input('company_name')),
            'brief_bio' => $this->trimValue($this->input('brief_bio')),
            'topics_to_speak_on' => $this->trimValue($this->input('topics_to_speak_on')),
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
            'linkedin_profile_url' => ['required', 'url', 'max:500'],
            'company_name' => ['required', 'string', 'max:255'],
            'brief_bio' => ['required', 'string'],
            'topics_to_speak_on' => ['required', 'string'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    private function trimValue(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}

<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPartnerWithUsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->trimValue($this->input('full_name')),
            'mobile_number' => $this->trimValue($this->input('mobile_number')),
            'email_id' => $this->trimValue($this->input('email_id')),
            'city' => $this->trimValue($this->input('city')),
            'brand_or_company_name' => $this->trimValue($this->input('brand_or_company_name')),
            'website_or_social_media_link' => $this->trimValue($this->input('website_or_social_media_link')),
            'industry' => $this->trimValue($this->input('industry')),
            'about_your_business' => $this->trimValue($this->input('about_your_business')),
            'partnership_goal' => $this->trimValue($this->input('partnership_goal')),
            'why_partner_with_peers_global' => $this->trimValue($this->input('why_partner_with_peers_global')),
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'email_id' => ['required', 'email', 'max:255'],
            'city' => ['required', 'string', 'max:150'],
            'brand_or_company_name' => ['required', 'string', 'max:255'],
            'website_or_social_media_link' => ['nullable', 'url', 'max:500'],
            'industry' => ['required', 'string', 'max:150'],
            'about_your_business' => ['required', 'string'],
            'partnership_goal' => ['required', 'string'],
            'why_partner_with_peers_global' => ['required', 'string'],
        ];
    }

    private function trimValue(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}

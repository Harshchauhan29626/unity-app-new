<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollaborationPostRequest extends FormRequest
{
    public const SCOPES = ['same_city', 'same_state', 'same_country', 'international'];
    public const PREFERRED_MODELS = ['revenue_share', 'commission_based', 'equity', 'profit_sharing', 'fixed_contract', 'open_for_discussion'];
    public const BUSINESS_STAGES = ['idea_stage', 'early_revenue', 'growing_10l_1cr', 'scaling_1cr_10cr', 'established_10cr_plus'];
    public const YEARS_IN_OPERATION = ['lt_1_year', '1_3_years', '3_7_years', '7_plus'];
    public const URGENCY_LEVELS = ['immediate_30_days', '1_3_months', '3_6_months', 'exploratory'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('scope') !== 'international') {
            $this->merge(['countries_of_interest' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'collaboration_type_id' => [
                'nullable',
                'uuid',
                'required_without:collaboration_type',
                Rule::exists('collaboration_types', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'collaboration_type' => ['nullable', 'string', 'required_without:collaboration_type_id'],
            'title' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string'],
            'scope' => ['required', Rule::in(self::SCOPES)],
            'countries_of_interest' => ['nullable', 'array', 'required_if:scope,international', 'min:1'],
            'countries_of_interest.*' => ['string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'preferred_model' => ['nullable', Rule::in(self::PREFERRED_MODELS)],
            'industry_id' => [
                'required',
                'uuid',
                Rule::exists('industries', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'business_stage' => ['required', Rule::in(self::BUSINESS_STAGES)],
            'years_in_operation' => ['required', Rule::in(self::YEARS_IN_OPERATION)],
            'urgency' => ['required', Rule::in(self::URGENCY_LEVELS)],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin\Circles;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'announcement' => ['nullable', 'string'],
            'city_id' => ['required', 'uuid', 'exists:cities,id'],
            'country' => ['required', 'string', 'max:100'],
            'founder_user_id' => ['required', 'uuid', 'exists:users,id'],
            'director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'industry_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'ded_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'cover_file_id' => ['nullable', 'uuid'],
            'type' => ['required', Rule::in(Circle::TYPE_OPTIONS)],
            'status' => ['required', Rule::in(Circle::STATUS_OPTIONS)],
            'industry_tags' => ['nullable', 'array'],
            'industry_tags.*' => ['string', 'max:50'],
            'meeting_mode' => ['nullable', Rule::in(Circle::MEETING_MODE_OPTIONS)],
            'meeting_frequency' => ['nullable', Rule::in(Circle::MEETING_FREQUENCY_OPTIONS)],
            'launch_date' => ['nullable', 'date'],
            'meeting_repeat' => ['nullable', 'array'],
            'calendar_meetings' => ['nullable', 'array'],
            'calendar_meetings.*.frequency' => ['nullable', Rule::in(['weekly', 'monthly', 'quarterly'])],
            'calendar_meetings.*.default_meet_day' => ['nullable', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'calendar_meetings.*.default_meet_time' => ['nullable', 'date_format:H:i'],
            'calendar_meetings.*.monthly_rule' => ['nullable', Rule::in(['first', 'second', 'third', 'fourth', 'last'])],

            'circle_payment_enabled' => ['nullable', 'boolean'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'quarterly_price' => ['nullable', 'numeric', 'min:0'],
            'half_yearly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('circle_payment_enabled')) {
                return;
            }

            $prices = [
                (float) ($this->input('monthly_price') ?? 0),
                (float) ($this->input('quarterly_price') ?? 0),
                (float) ($this->input('half_yearly_price') ?? 0),
                (float) ($this->input('yearly_price') ?? 0),
            ];

            if (collect($prices)->every(static fn (float $value) => $value <= 0)) {
                $validator->errors()->add('circle_payment_enabled', 'When circle payment is enabled, at least one price must be greater than 0.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'circle_payment_enabled' => $this->boolean('circle_payment_enabled'),
        ];

        if ($this->filled('industry_tags') && is_string($this->input('industry_tags'))) {
            $payload['industry_tags'] = array_values(array_filter(array_map('trim', explode(',', $this->input('industry_tags')))));
        }

        if ($this->filled('meeting_repeat') && is_string($this->input('meeting_repeat'))) {
            $decoded = json_decode($this->input('meeting_repeat'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload['meeting_repeat'] = $decoded;
            }
        }

        $this->merge($payload);

        if ($this->filled('founder_user_id')) {
            return;
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return;
        }

        $defaultFounder = User::query()->where('email', $admin->email)->first();

        if ($defaultFounder) {
            $this->merge([
                'founder_user_id' => $defaultFounder->id,
            ]);
        }
    }
}

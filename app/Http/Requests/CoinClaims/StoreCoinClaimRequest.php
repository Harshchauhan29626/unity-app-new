<?php

namespace App\Http\Requests\CoinClaims;

use App\Support\CoinClaims\CoinClaimActivityRegistry;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCoinClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'activity_code' => ['required', 'string'],
            'fields' => ['nullable', 'array'],
            'files' => ['nullable', 'array'],
            'files.*' => ['nullable', 'file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $registry = app(CoinClaimActivityRegistry::class);
            $activityCode = (string) $this->input('activity_code', '');

            if (! $registry->has($activityCode)) {
                $validator->errors()->add('activity_code', 'The selected activity code is invalid.');

                return;
            }

            $fieldMap = $registry->fieldMap($activityCode);
            $fields = (array) $this->input('fields', []);
            $files = $this->file('files', []);

            foreach ($fieldMap as $key => $definition) {
                $type = (string) ($definition['type'] ?? 'text');
                $required = (bool) ($definition['required'] ?? false);
                $value = $fields[$key] ?? null;
                $file = $files[$key] ?? null;

                if ($type === 'file') {
                    if ($required && ! $file) {
                        $validator->errors()->add("files.$key", 'This file is required.');
                    }

                    continue;
                }

                if ($required && ($value === null || trim((string) $value) === '')) {
                    $validator->errors()->add("fields.$key", 'This field is required.');

                    continue;
                }

                if ($value === null || trim((string) $value) === '') {
                    continue;
                }

                $this->validateTypedField($validator, $key, $type, (string) $value);
            }
        });
    }


    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }

    private function validateTypedField(Validator $validator, string $key, string $type, string $value): void
    {
        $ok = match ($type) {
            'date' => (bool) strtotime($value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'phone' => preg_match('/^[0-9+\-\s]{7,20}$/', $value) === 1,
            default => true,
        };

        if (! $ok) {
            $validator->errors()->add("fields.$key", "The $key format is invalid.");
        }
    }
}

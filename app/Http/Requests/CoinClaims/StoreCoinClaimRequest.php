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
            'files.*' => ['nullable', 'file', 'max:51200'],
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

            $fieldKeys = array_keys($fieldMap);
            $providedFieldKeys = array_keys($fields);
            $unknownFieldKeys = array_diff($providedFieldKeys, $fieldKeys);

            foreach ($unknownFieldKeys as $unknownFieldKey) {
                $suggestedKey = $this->closestKey((string) $unknownFieldKey, $fieldKeys);

                if ($suggestedKey !== null) {
                    $validator->errors()->add(
                        "fields.$unknownFieldKey",
                        "Unknown field '$unknownFieldKey'. Did you mean '$suggestedKey'?"
                    );

                    continue;
                }

                $validator->errors()->add("fields.$unknownFieldKey", "Unknown field '$unknownFieldKey'.");
            }

            foreach ($fieldMap as $key => $definition) {
                $type = (string) ($definition['type'] ?? 'text');
                $required = (bool) ($definition['required'] ?? false);
                $label = $this->fieldLabel($key, $definition);
                $value = $fields[$key] ?? null;
                $file = $files[$key] ?? null;

                if ($type === 'file') {
                    if ($required && ! $file) {
                        $validator->errors()->add("files.$key", "$label is required.");
                    }

                    continue;
                }

                if ($required && ($value === null || trim((string) $value) === '')) {
                    $validator->errors()->add("fields.$key", "$label is required.");

                    continue;
                }

                if ($value === null || trim((string) $value) === '') {
                    continue;
                }

                $this->validateTypedField($validator, $key, $type, (string) $value, $label);
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

    private function validateTypedField(Validator $validator, string $key, string $type, string $value, string $label): void
    {
        $ok = match ($type) {
            'date' => (bool) strtotime($value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'phone' => preg_match('/^[0-9+\-\s]{7,20}$/', $value) === 1,
            default => true,
        };

        if ($ok) {
            return;
        }

        $message = match ($type) {
            'date' => "$label must be a valid date.",
            'email' => "$label must be a valid email address.",
            'url' => "$label must be a valid URL.",
            'phone' => "$label must be a valid phone number.",
            default => "$label format is invalid.",
        };

        $validator->errors()->add("fields.$key", $message);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function fieldLabel(string $key, array $definition): string
    {
        $label = trim((string) ($definition['label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function closestKey(string $inputKey, array $candidates): ?string
    {
        $bestKey = null;
        $bestDistance = null;

        foreach ($candidates as $candidate) {
            $distance = levenshtein($inputKey, $candidate);

            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestKey = $candidate;
            }
        }

        if ($bestDistance === null || $bestDistance > 4) {
            return null;
        }

        return $bestKey;
    }
}

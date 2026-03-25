<?php

namespace App\Http\Requests\Api\CircleJoinRequests;

use App\Models\CircleCategoryMapping;
use Illuminate\Foundation\Http\FormRequest;

class StoreCircleJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'circle_id' => ['required', 'uuid', 'exists:circles,id'],
            'reason_for_joining' => ['nullable', 'string', 'max:2000'],
            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $circleId = (string) $this->input('circle_id');

                    if ($value === null || $circleId === '') {
                        return;
                    }

                    $mapped = CircleCategoryMapping::query()
                        ->where('circle_id', $circleId)
                        ->where('category_id', (int) $value)
                        ->exists();

                    if (! $mapped) {
                        $fail('The selected category is not mapped to this circle.');
                    }
                },
            ],
        ];
    }
}

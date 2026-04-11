<?php

namespace App\Http\Requests\Admin\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_categories', 'name')->ignore($category?->id),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('circle_categories', 'slug')->ignore($category?->id),
            ],
            'circle_key' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.unique' => 'This category already exists.',
        ];
    }
}

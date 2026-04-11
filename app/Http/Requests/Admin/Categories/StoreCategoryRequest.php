<?php

namespace App\Http\Requests\Admin\Categories;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:circle_categories,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:circle_categories,slug'],
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

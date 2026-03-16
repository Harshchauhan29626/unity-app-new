<?php

namespace App\Http\Requests\Admin\Circulars;

use App\Models\Circular;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCircularRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(Circular::CATEGORY_OPTIONS)],
            'priority' => ['required', Rule::in(Circular::PRIORITY_OPTIONS)],
            'publish_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:publish_date'],
            'featured_image_file_id' => ['nullable', 'uuid'],
            'featured_image' => ['nullable', 'image', 'max:10240'],
            'content' => ['required', 'string'],
            'attachment_file_id' => ['nullable', 'uuid'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp'],
            'video_url' => ['nullable', 'url'],
            'audience_type' => ['required', Rule::in(Circular::AUDIENCE_OPTIONS)],
            'city_id' => ['nullable', 'uuid', 'exists:cities,id'],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'send_push_notification' => ['nullable', 'boolean'],
            'allow_comments' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}

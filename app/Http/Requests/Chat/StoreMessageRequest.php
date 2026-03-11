<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:5000'],
            'content_text' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['array'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => [
                'file',
                'max:51200',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,video/mp4,video/quicktime,video/webm,application/pdf,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $content = $this->normalizedNullableText($this->input('content'));
            $contentText = $this->normalizedNullableText($this->input('content_text'));

            $this->merge([
                'content' => $content,
                'content_text' => $contentText,
            ]);

            $hasContent = $content !== null || $contentText !== null;
            $hasAttachments = is_array($this->input('attachments')) && count($this->input('attachments')) > 0;
            $files = $this->file('files');
            $hasFiles = $files instanceof UploadedFile
                || (is_array($files) && count($files) > 0);

            if (! $hasContent && ! $hasAttachments && ! $hasFiles) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Either content or files is required.',
                ], 422));
            }
        });
    }

    private function normalizedNullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || in_array(strtolower($trimmed), ['none', 'null'], true)) {
            return null;
        }

        return $trimmed;
    }
}

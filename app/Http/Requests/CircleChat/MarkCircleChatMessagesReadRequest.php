<?php

namespace App\Http\Requests\CircleChat;

use Illuminate\Foundation\Http\FormRequest;

class MarkCircleChatMessagesReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_ids' => ['required', 'array', 'min:1'],
            'message_ids.*' => ['required', 'uuid', 'exists:circle_chat_messages,id'],
        ];
    }
}

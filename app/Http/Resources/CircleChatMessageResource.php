<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CircleChatMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        $authUserId = (string) optional($request->user())->id;
        $sender = $this->whenLoaded('sender');
        $replyTo = $this->whenLoaded('replyTo');

        return [
            'id' => (string) $this->id,
            'circle_id' => (string) $this->circle_id,
            'message_type' => (string) $this->message_type,
            'message_text' => $this->message_text,
            'attachment' => $this->formatAttachment(),
            'reply_to_message' => $replyTo ? new self($replyTo) : null,
            'sender' => [
                'id' => (string) optional($sender)->id,
                'name' => trim((string) (optional($sender)->display_name ?: ((optional($sender)->first_name ?? '') . ' ' . (optional($sender)->last_name ?? '')))),
                'company_name' => optional($sender)->company_name,
                'profile_photo_url' => optional($sender)->profile_photo_url,
            ],
            'is_mine' => (string) $this->sender_id === $authUserId,
            'read_count' => (int) ($this->read_count ?? ($this->relationLoaded('reads') ? $this->reads->count() : 0)),
            'is_read_by_me' => (bool) ($this->is_read_by_me ?? false),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function formatAttachment(): ?array
    {
        if (blank($this->file_path)) {
            return null;
        }

        return [
            'id' => null,
            'type' => (string) $this->message_type,
            'url' => $this->resolveStorageUrl((string) $this->file_path),
            'thumbnail_url' => $this->thumbnail_path ? $this->resolveStorageUrl((string) $this->thumbnail_path) : null,
            'mime' => $this->file_mime,
            'size' => $this->file_size,
            'name' => $this->file_name,
            'path' => $this->file_path,
        ];
    }

    private function resolveStorageUrl(string $path): string
    {
        $disk = config('filesystems.default', 'public');

        return Storage::disk($disk)->url($path);
    }
}

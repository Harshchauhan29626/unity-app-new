<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CircularDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'category' => $this->category,
            'priority' => $this->priority,
            'featured_image_url' => $this->featured_image_resolved_url,
            'content' => $this->content,
            'attachment_url' => $this->attachment_resolved_url,
            'video_url' => $this->video_url,
            'publish_date' => optional($this->publish_date)->toIso8601String(),
            'expiry_date' => optional($this->expiry_date)->toIso8601String(),
            'allow_comments' => (bool) $this->allow_comments,
            'action_label' => null,
        ];
    }
}

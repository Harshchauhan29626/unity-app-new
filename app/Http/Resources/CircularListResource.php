<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CircularListResource extends JsonResource
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
            'publish_date' => optional($this->publish_date)->toIso8601String(),
            'read_more_available' => true,
            'video_url' => $this->video_url,
            'attachment_url' => $this->attachment_resolved_url,
            'is_pinned' => (bool) $this->is_pinned,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EventGalleryResource extends JsonResource
{
    public function toArray($request): array
    {
        $imagesCount = $this->images_count ?? null;
        $videosCount = $this->videos_count ?? null;

        if ($this->relationLoaded('media')) {
            $imagesCount = $imagesCount ?? $this->media->where('media_type', 'image')->count();
            $videosCount = $videosCount ?? $this->media->where('media_type', 'video')->count();
        }

        return [
            'id' => $this->id,
            'event_name' => $this->event_name,
            'event_date' => $this->event_date,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category_name' => $this->categoryRef?->category_name,
            'cover_url' => $this->cover_url,
            'counts' => [
                'images' => (int) ($imagesCount ?? 0),
                'videos' => (int) ($videosCount ?? 0),
            ],
            'created_at' => $this->created_at,
            'media' => EventGalleryMediaResource::collection($this->whenLoaded('media')),
        ];
    }
}

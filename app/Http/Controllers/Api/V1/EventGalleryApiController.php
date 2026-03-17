<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\EventGalleryResource;
use App\Models\EventGallery;
use Illuminate\Http\Request;

class EventGalleryApiController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = EventGallery::query()
            ->with('categoryRef')
            ->withCount([
                'media as images_count' => function ($query) {
                    $query->where('media_type', 'image');
                },
                'media as videos_count' => function ($query) {
                    $query->where('media_type', 'video');
                },
            ]);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('event_name', 'ILIKE', '%' . $search . '%');
        }

        $categoryId = trim((string) $request->query('category_id', ''));
        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success([
            'items' => EventGalleryResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $event = EventGallery::query()
            ->with(['categoryRef', 'media' => function ($query) {
                $query->orderBy('sort_order')
                    ->orderBy('created_at');
            }])
            ->find($id);

        if (! $event) {
            return $this->error('Event gallery not found', 404);
        }

        return $this->success(new EventGalleryResource($event));
    }
}

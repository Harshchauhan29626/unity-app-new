<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\FileController as ApiFileController;
use App\Http\Controllers\Controller;
use App\Models\EventGallery;
use App\Models\EventGalleryMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventGalleryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $selectedEventId = $request->query('event_id');

        $events = EventGallery::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('event_name', 'ILIKE', '%' . $search . '%');
            })
            ->withCount([
                'media',
                'media as images_count' => function ($query) {
                    $query->where('media_type', 'image');
                },
                'media as videos_count' => function ($query) {
                    $query->where('media_type', 'video');
                },
            ])
            ->orderByDesc('created_at')
            ->get();

        $selectedEvent = null;

        if ($selectedEventId) {
            $selectedEvent = EventGallery::query()
                ->with(['media' => function ($query) {
                    $query->orderBy('sort_order')
                        ->orderBy('created_at');
                }])
                ->find($selectedEventId);
        }

        return view('admin.event_gallery.index', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
            'search' => $search,
        ]);
    }

    public function storeEvent(Request $request)
    {
        $data = $request->validate([
            'event_name' => ['required', 'string', 'max:180'],
            'event_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $normalizedName = mb_strtolower(trim($data['event_name']));

        $existing = EventGallery::query()
            ->whereRaw('LOWER(event_name) = ?', [$normalizedName])
            ->first();

        if ($existing) {
            return redirect()
                ->route('admin.event-gallery.index', ['event_id' => $existing->id])
                ->with('success', 'Event already exists.');
        }

        $event = EventGallery::create([
            'event_name' => $data['event_name'],
            'event_date' => $data['event_date'] ?? null,
            'description' => $data['description'] ?? null,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        return redirect()
            ->route('admin.event-gallery.index', ['event_id' => $event->id])
            ->with('success', 'Event created successfully.');
    }

    public function storeMedia(Request $request, ApiFileController $fileController)
    {
        $data = $request->validate([
            'event_gallery_id' => ['nullable', 'uuid'],
            'event_name' => ['nullable', 'string', 'max:180'],
            'media_type' => ['required', 'in:image,video'],
            'file' => ['required'],
            'file.*' => ['file', 'max:51200'],
            'caption' => ['nullable', 'string', 'max:255'],
            'thumbnail_file' => ['nullable', 'file', 'max:51200'],
        ]);

        if (empty($data['event_gallery_id']) && empty($data['event_name'])) {
            return back()
                ->withErrors(['event_name' => 'Please select an event or enter a new event name.'])
                ->withInput();
        }

        $event = null;

        if (! empty($data['event_gallery_id'])) {
            $event = EventGallery::find($data['event_gallery_id']);
        }

        if (! $event && ! empty($data['event_name'])) {
            $normalizedName = mb_strtolower(trim($data['event_name']));

            $event = EventGallery::query()
                ->whereRaw('LOWER(event_name) = ?', [$normalizedName])
                ->first();

            if (! $event) {
                $event = EventGallery::create([
                    'event_name' => $data['event_name'],
                    'created_by_admin_id' => Auth::guard('admin')->id(),
                ]);
            }
        }

        if (! $event) {
            return back()->withErrors(['event_gallery_id' => 'Event not found.'])->withInput();
        }

        try {
            $uploadResponse = $fileController->upload($request);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        if (! $uploadResponse instanceof JsonResponse) {
            return back()->withErrors(['file' => 'File upload failed.'])->withInput();
        }

        $payload = $uploadResponse->getData(true);

        if (! ($payload['success'] ?? false)) {
            return back()->withErrors(['file' => $payload['message'] ?? 'File upload failed.'])->withInput();
        }

        $uploadedItems = $payload['data'] ?? [];
        if (! is_array($uploadedItems)) {
            $uploadedItems = [];
        }

        $uploadedItems = array_is_list($uploadedItems) ? $uploadedItems : [$uploadedItems];

        $thumbnailFileId = null;

        if ($request->hasFile('thumbnail_file')) {
            $thumbRequest = new Request();
            $thumbRequest->files->set('file', $request->file('thumbnail_file'));
            $thumbRequest->setUserResolver($request->getUserResolver());

            try {
                $thumbResponse = $fileController->upload($thumbRequest);
            } catch (ValidationException $exception) {
                return back()->withErrors($exception->errors())->withInput();
            }

            if (! $thumbResponse instanceof JsonResponse) {
                return back()->withErrors(['thumbnail_file' => 'Thumbnail upload failed.'])->withInput();
            }

            $thumbPayload = $thumbResponse->getData(true);

            if (! ($thumbPayload['success'] ?? false)) {
                return back()->withErrors(['thumbnail_file' => $thumbPayload['message'] ?? 'Thumbnail upload failed.'])
                    ->withInput();
            }

            $thumbnailFileId = $thumbPayload['data']['id'] ?? null;
        }

        $nextSortOrder = (int) EventGalleryMedia::query()
            ->where('event_gallery_id', $event->id)
            ->max('sort_order');

        foreach ($uploadedItems as $uploadedItem) {
            if (! isset($uploadedItem['id'])) {
                continue;
            }

            $nextSortOrder++;

            EventGalleryMedia::create([
                'event_gallery_id' => $event->id,
                'media_type' => $data['media_type'],
                'file_id' => $uploadedItem['id'],
                'thumbnail_file_id' => $thumbnailFileId,
                'caption' => $data['caption'] ?? null,
                'sort_order' => $nextSortOrder,
                'created_by_admin_id' => Auth::guard('admin')->id(),
            ]);
        }

        return redirect()
            ->route('admin.event-gallery.index', ['event_id' => $event->id])
            ->with('success', 'Media added successfully.');
    }

    public function destroyMedia(string $id)
    {
        $media = EventGalleryMedia::findOrFail($id);
        $eventId = $media->event_gallery_id;

        $media->delete();

        return redirect()
            ->route('admin.event-gallery.index', ['event_id' => $eventId])
            ->with('success', 'Media deleted successfully.');
    }
}

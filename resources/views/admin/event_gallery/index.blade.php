@extends('admin.layouts.app')

@section('title', 'Event Gallery')

@push('styles')
    <style>
        .event-gallery-grid {
            max-height: 60vh;
            overflow: auto;
        }
        .event-gallery-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow: hidden;
            background: #fff;
        }
        .event-gallery-media {
            position: relative;
            padding-top: 70%;
            background: #f8f9fa;
        }
        .event-gallery-media img,
        .event-gallery-media video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .event-gallery-media .play-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.35);
            color: #fff;
            font-size: 2rem;
        }
    </style>
@endpush

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Event Gallery</h1>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">Add Event</button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMediaModal">Add Media</button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search events" value="{{ $search }}">
                        </div>
                        <div class="col-md-4">
                            <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">All Category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>{{ $category->category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-outline-secondary">Search</button>
                        </div>
                    </form>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($events as $event)
                        @php
                            $isActive = $selectedEvent && $selectedEvent->id === $event->id;
                            $eventDate = $event->event_date ? $event->event_date->format('M d, Y') : 'Date TBD';
                            $query = array_filter(['event_id' => $event->id, 'q' => $search, 'category_id' => $selectedCategoryId]);
                        @endphp
                        <a
                            href="{{ route('admin.event-gallery.index', $query) }}"
                            class="list-group-item list-group-item-action {{ $isActive ? 'active' : '' }}"
                        >
                            <div class="fw-semibold">{{ $event->event_name }}</div>
                            <div class="small {{ $isActive ? 'text-white-50' : 'text-muted' }}">{{ $eventDate }}</div>
                            <div class="small {{ $isActive ? 'text-white-50' : 'text-muted' }}">
                                {{ $event->categoryRef?->category_name ?? '—' }}
                            </div>
                            <div class="small {{ $isActive ? 'text-white-50' : 'text-muted' }}">
                                {{ $event->images_count ?? 0 }} images • {{ $event->videos_count ?? 0 }} videos
                            </div>
                        </a>
                    @empty
                        <div class="p-3 text-muted">No events found.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    @if ($selectedEvent)
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h2 class="h5 mb-1">{{ $selectedEvent->event_name }}</h2>
                                <div class="text-muted">
                                    {{ $selectedEvent->event_date ? $selectedEvent->event_date->format('M d, Y') : 'Date TBD' }}
                                </div>
                                <div class="small text-muted mt-1">Category: {{ $selectedEvent->categoryRef?->category_name ?? '—' }}</div>
                                @if ($selectedEvent->description)
                                    <p class="mt-2 mb-0">{{ $selectedEvent->description }}</p>
                                @endif
                            </div>
                            <div class="text-muted small">
                                {{ $selectedEvent->media->count() }} media items
                            </div>
                        </div>
                        <div class="event-gallery-grid">
                            <div class="row g-3">
                                @forelse ($selectedEvent->media as $media)
                                    <div class="col-md-6 col-xl-4">
                                        <div class="event-gallery-card h-100">
                                            <div class="event-gallery-media">
                                                @if ($media->media_type === 'video' && $media->thumbnail_url)
                                                    <a href="{{ $media->url }}" target="_blank" rel="noopener" class="text-decoration-none">
                                                        <img src="{{ $media->thumbnail_url }}" alt="Video thumbnail" loading="lazy">
                                                        <div class="play-overlay">
                                                            <i class="bi bi-play-circle"></i>
                                                        </div>
                                                    </a>
                                                @elseif ($media->media_type === 'video')
                                                    <video controls preload="metadata">
                                                        <source src="{{ $media->url }}" type="video/mp4">
                                                    </video>
                                                @else
                                                    <img src="{{ $media->url }}" alt="Event media" loading="lazy">
                                                @endif
                                            </div>
                                            <div class="p-2">
                                                @if ($media->caption)
                                                    <div class="small text-muted">{{ $media->caption }}</div>
                                                @endif
                                                <form method="POST" action="{{ route('admin.event-gallery.media.destroy', $media->id) }}" class="mt-2">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-muted">No media added yet.</div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="text-muted">Select an event to view its media.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.event-gallery.events.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEventModalLabel">Add Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Event Name</label>
                            <input type="text" name="event_name" class="form-control" required maxlength="180">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">All Category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Event Date</label>
                            <input type="date" name="event_date" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addMediaModal" tabindex="-1" aria-labelledby="addMediaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.event-gallery.media.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="addMediaModalLabel">Add Media</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Select Existing Event</label>
                                <select name="event_gallery_id" class="form-select">
                                    <option value="">-- Select Event --</option>
                                    @foreach ($events as $event)
                                        <option value="{{ $event->id }}" @selected($selectedEvent && $selectedEvent->id === $event->id)>{{ $event->event_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Or Add New Event Name</label>
                                <input type="text" name="event_name" class="form-control" maxlength="180">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">All Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Media Type</label>
                                <select name="media_type" class="form-select" required>
                                    <option value="image">Image</option>
                                    <option value="video">Video</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Caption (optional)</label>
                                <input type="text" name="caption" class="form-control" maxlength="255">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Upload Files</label>
                                <input type="file" name="file[]" class="form-control" multiple required>
                                <div class="form-text">You can upload multiple images/videos at once.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Video Thumbnail (optional)</label>
                                <input type="file" name="thumbnail_file" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Media</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

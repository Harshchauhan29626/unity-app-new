@extends('admin.layouts.app')

@section('title', 'Post Details')

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @php
        $owner = $post->user;
        $ownerName = $owner?->display_name ?: trim(($owner?->first_name ?? '') . ' ' . ($owner?->last_name ?? ''));
        $isActive = $post->deleted_at === null;
        $mediaItems = is_array($post->media) ? $post->media : [];
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h2 class="h4 mb-1">Post Details</h2>
            <div class="text-muted small">Post ID: {{ $post->id }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.posts.index') }}" class="btn btn-sm btn-outline-secondary">Back to All Posts</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Post Info</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Created At</dt>
                        <dd class="col-sm-8">{{ $post->created_at?->format('Y-m-d H:i') }}</dd>
                        <dt class="col-sm-4">Owner</dt>
                        <dd class="col-sm-8">{{ $ownerName !== '' ? $ownerName : 'Unknown' }}</dd>
                        <dt class="col-sm-4">Circle</dt>
                        <dd class="col-sm-8">{{ $post->circle?->name ?? '—' }}</dd>
                        <dt class="col-sm-4">Visibility</dt>
                        <dd class="col-sm-8">{{ ucfirst($post->visibility) }}</dd>
                        <dt class="col-sm-4">Moderation Status</dt>
                        <dd class="col-sm-8">{{ $post->moderation_status ? ucfirst($post->moderation_status) : '—' }}</dd>
                        <dt class="col-sm-4">Active?</dt>
                        <dd class="col-sm-8">{{ $isActive ? 'Yes' : 'No' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Content Preview</div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Content</div>
                        <div class="border rounded p-2 bg-light">{{ $post->content_text ?: '—' }}</div>
                    </div>
                    @if ($mediaItems)
                        <div>
                            <div class="text-muted small">Media</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($mediaItems as $media)
                                    @php
                                        $mediaUrl = data_get($media, 'url');
                                    @endphp
                                    @if ($mediaUrl)
                                        <img src="{{ $mediaUrl }}" alt="Post media" class="img-thumbnail" style="width: 120px; height: auto;">
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold">Actions</div>
        <div class="card-body d-flex flex-wrap gap-3">
            @if ($isActive)
                <form method="POST" action="{{ route('admin.posts.deactivate', $post) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to remove this post?')">Deactivate</button>
                </form>
            @else
                <span class="text-muted">This post has been removed.</span>
            @endif
        </div>
    </div>
@endsection

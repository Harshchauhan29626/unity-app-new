@extends('admin.layouts.app')

@section('title', 'All Posts')

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="p-3 border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted">Active</label>
                    <select name="active" class="form-select form-select-sm">
                        <option value="all" @selected($filters['active'] === 'all')>All</option>
                        <option value="active" @selected($filters['active'] === 'active')>Active</option>
                        <option value="deactivated" @selected($filters['active'] === 'deactivated')>Deactivated</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted">Visibility</label>
                    <select name="visibility" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($visibilities as $visibility)
                            <option value="{{ $visibility }}" @selected($filters['visibility'] === $visibility)>{{ ucfirst($visibility) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted">Moderation Status</label>
                    <select name="moderation_status" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($moderationStatuses as $status)
                            <option value="{{ $status }}" @selected($filters['moderation_status'] === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted">Circle</label>
                    <select name="circle_id" class="form-select form-select-sm">
                        <option value="all">All Circles</option>
                        @foreach ($circles as $circle)
                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Content or owner" value="{{ $filters['search'] }}">
                </div>
                <div class="col-12 col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ route('admin.posts.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle text-nowrap">
                <thead class="table-light">
                    <tr>
                        <th>Created At</th>
                        <th>Post ID</th>
                        <th>Peer Name</th>
                                                <th>Visibility</th>
                        <th>Moderation Status</th>
                        <th>Active?</th>
                        <th>Content</th>
                        <th>Media</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($posts as $post)
                        @php
                            $owner = $post->user;
                            $isActive = ! $post->is_deleted && ! $post->deleted_at;
                            $circleName = $post->circle?->name;
                            $mediaUrl = (function ($media) {
                                if (empty($media)) {
                                    return null;
                                }

                                $items = [];

                                if (is_array($media)) {
                                    $items = $media;
                                } elseif (is_object($media)) {
                                    $items = data_get($media, 'items', []);
                                }

                                if (! is_array($items)) {
                                    return null;
                                }

                                $imageItem = collect($items)->first(function ($item) {
                                    return data_get($item, 'type') === 'image';
                                });

                                $candidate = $imageItem ?? (collect($items)->first() ?? []);
                                $url = data_get($candidate, 'url');

                                if ($url) {
                                    return $url;
                                }

                                $id = data_get($candidate, 'id') ?? data_get($candidate, 'file_id');

                                if ($id) {
                                    return url('/api/v1/files/' . $id);
                                }

                                return data_get($candidate, 'path');
                            })($post->media ?? null);
                        @endphp
                        <tr>
                            <td>{{ $post->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $post->id }}</td>
                            <td>
                                @include('admin.partials.peer_identity', ['user' => $owner, 'circleName' => $circleName])
                            </td>
                            <td>{{ ucfirst($post->visibility) }}</td>
                            <td>{{ $post->moderation_status ? ucfirst($post->moderation_status) : '—' }}</td>
                            <td>{{ $isActive ? 'Yes' : 'No' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($post->content_text, 60) }}</td>
                            <td>
                                @if ($mediaUrl)
                                    <a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ $mediaUrl }}">View</a>
                                @else
                                    None
                                @endif
                            </td>
                            <td class="d-flex gap-2">
                                <a href="{{ route('admin.posts.show', $post) }}" class="btn btn-sm btn-outline-primary">View</a>
                                @if ($isActive)
                                    <form method="POST" action="{{ route('admin.posts.deactivate', $post) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.posts.restore', $post) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">No posts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $posts->links() }}
    </div>
@endsection

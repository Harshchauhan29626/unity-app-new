@extends('admin.layouts.app')

@section('title', 'Post Reports')

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
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted">Reason</label>
                    <select name="reason" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($reasons as $reason)
                            <option value="{{ $reason }}" @selected($filters['reason'] === $reason)>{{ ucfirst($reason) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted">Reported From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted">Reported To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] }}">
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
                <div class="col-12 col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    <a href="{{ route('admin.post-reports.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle text-nowrap">
                <thead class="table-light">
                    <tr>
                        <th>Reported At</th>
                        <th>Post ID</th>
                        <th>Peer Name</th>
                        <th>Reporter Name</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Total Reports on Post</th>
                        <th>Post Active?</th>
                        <th>Media</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        @php
                            $postOwner = $report->post?->user;
                            $circleName = $report->post?->circle?->name;
                            $reporterName = $report->reporter?->display_name ?: trim(($report->reporter?->first_name ?? '') . ' ' . ($report->reporter?->last_name ?? ''));
                            $isPostActive = $report->post ? ! $report->post->is_deleted && ! $report->post->deleted_at : false;
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
                            })($report->post?->media ?? null);
                        @endphp
                        <tr>
                            <td>{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $report->post_id }}</td>
                            <td>
                                @include('admin.partials.peer_identity', ['user' => $postOwner, 'circleName' => $circleName])
                            </td>
                            <td>{{ $reporterName !== '' ? $reporterName : 'Unknown' }}</td>
                            <td>{{ $report->reasonOption?->title ?? $report->reason ?? '—' }}</td>
                            <td>{{ ucfirst($report->status) }}</td>
                            <td>{{ $report->total_reports ?? 0 }}</td>
                            <td>{{ $isPostActive ? 'Yes' : 'No' }}</td>
                            <td>
                                @if ($mediaUrl)
                                    <a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ $mediaUrl }}">View</a>
                                @else
                                    None
                                @endif
                            </td>
                            <td class="d-flex gap-2">
                                <a href="{{ route('admin.post-reports.show', $report) }}" class="btn btn-sm btn-outline-primary">View</a>
                                @if ($report->post)
                                    @if ($isPostActive)
                                        <form method="POST" action="{{ route('admin.posts.deactivate', $report->post) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.posts.restore', $report->post) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">No reports found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $reports->links() }}
    </div>
@endsection

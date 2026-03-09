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
        <form method="GET" action="{{ route('admin.post-reports.index') }}">
            <div class="p-3 border-bottom">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Any</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted">Reason</label>
                        <select name="reason" class="form-select form-select-sm">
                            <option value="">Any</option>
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason }}" @selected(($filters['reason'] ?? '') === $reason)>{{ ucfirst($reason) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted">Reported From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted">Reported To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted">Circle</label>
                        <select name="circle_id" class="form-select form-select-sm">
                            <option value="all">All Circles</option>
                            @foreach ($circles as $circle)
                                <option value="{{ $circle->id }}" @selected(($circleId ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <div class="d-flex align-items-end justify-content-end gap-2" style="flex-wrap:nowrap; white-space:nowrap;">
                            <button type="submit" class="btn btn-sm btn-primary">
                                Apply Filters
                            </button>
                            <a href="{{ route('admin.post-reports.index') }}" class="btn btn-sm btn-outline-secondary">
                                Reset
                            </a>
                        </div>
                    </div>
                </div>
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
                        <tr class="bg-light">
                            <th class="py-2"></th>
                            <th class="py-2"><input type="text" name="post_id" class="form-control form-control-sm" value="{{ $postId ?? '' }}" placeholder="Post ID"></th>
                            <th class="py-2"><input type="text" name="peer" class="form-control form-control-sm" value="{{ $peer ?? '' }}" placeholder="Peer Name"></th>
                            <th class="py-2"><input type="text" name="reporter" class="form-control form-control-sm" value="{{ $reporter ?? '' }}" placeholder="Reporter Name"></th>
                            <th class="py-2"><input type="text" name="reason_text" class="form-control form-control-sm" value="{{ $reasonText ?? '' }}" placeholder="Reason"></th>
                            <th class="py-2">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="any">Any</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected(($filters['status'] ?? 'any') === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="py-2">
                                <select name="total_reports" class="form-select form-select-sm">
                                    <option value="any" @selected(($totalReports ?? 'any') === 'any')>Any</option>
                                    <option value="1" @selected(($totalReports ?? '') === '1')>1</option>
                                    <option value="2-5" @selected(($totalReports ?? '') === '2-5')>2-5</option>
                                    <option value="6+" @selected(($totalReports ?? '') === '6+')>6+</option>
                                </select>
                            </th>
                            <th class="py-2">
                                <select name="post_active" class="form-select form-select-sm">
                                    <option value="any" @selected(($postActive ?? 'any') === 'any')>Any</option>
                                    <option value="yes" @selected(($postActive ?? '') === 'yes')>Yes</option>
                                    <option value="no" @selected(($postActive ?? '') === 'no')>No</option>
                                </select>
                            </th>
                            <th class="py-2">
                                <select name="media" class="form-select form-select-sm">
                                    <option value="any" @selected(($media ?? 'any') === 'any')>Any</option>
                                    <option value="has" @selected(($media ?? '') === 'has')>Has Media</option>
                                    <option value="none" @selected(($media ?? '') === 'none')>No Media</option>
                                </select>
                            </th>
                            <th class="text-end py-2" style="white-space:nowrap;">
                                <div class="d-inline-flex align-items-center gap-2" style="flex-wrap:nowrap;">
                                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                    <a href="{{ route('admin.post-reports.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                                </div>
                            </th>
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
                                <td>@include('admin.partials.peer_identity', ['user' => $postOwner, 'circleName' => $circleName])</td>
                                <td>{{ $reporterName !== '' ? $reporterName : 'Unknown' }}</td>
                                <td>{{ $report->reasonOption?->title ?? $report->reason ?? '—' }}</td>
                                <td>{{ ucfirst($report->status) }}</td>
                                <td>{{ $report->total_reports ?? 0 }}</td>
                                <td>{{ $isPostActive ? 'Yes' : 'No' }}</td>
                                <td style="white-space:nowrap;">
                                    @if ($mediaUrl)
                                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ $mediaUrl }}">View</a>
                                    @else
                                        <span class="text-muted">None</span>
                                    @endif
                                </td>
                                <td class="text-end" style="white-space:nowrap;">
                                    <div class="d-inline-flex align-items-center gap-2" style="flex-wrap:nowrap;">
                                        <a href="{{ route('admin.post-reports.show', $report) }}" class="btn btn-sm btn-outline-primary">View</a>
                                        @if ($report->post)
                                            @if ($isPostActive)
                                                <form method="POST" action="{{ route('admin.posts.deactivate', $report->post) }}" class="m-0 p-0 d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.posts.restore', $report->post) }}" class="m-0 p-0 d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Restore this post report?')">Restore</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted">No reports found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <div class="mt-3">{{ $reports->links() }}</div>
@endsection

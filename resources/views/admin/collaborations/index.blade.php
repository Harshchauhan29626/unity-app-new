@extends('admin.layouts.app')

@section('title', 'Find & Build Collaborations')

@section('content')
    <style>
        .peer-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; display: block; }
    </style>
@php use App\Support\CollaborationFormatter; @endphp
    <form id="collaborationsFiltersForm" method="GET" action="{{ route('admin.collaborations.index') }}">
    @include('admin.components.activity-filter-bar-v2', [
        'actionUrl' => route('admin.collaborations.index'),
        'resetUrl' => route('admin.collaborations.index'),
        'filters' => $filters,
        'circles' => $circles ?? collect(),
        'showExport' => true,
        'exportUrl' => route('admin.collaborations.export', request()->query()),
        'renderFormTag' => false,
        'formId' => 'collaborationsFiltersForm',
    ])

<div class="card p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="d-flex align-items-center gap-2">
            <h2 class="h5 mb-0">Find &amp; Build Collaborations</h2>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto flex-wrap justify-content-end">
            <div class="d-flex align-items-center gap-2 ms-2">
                <label for="perPage" class="form-label mb-0 small text-muted">Rows per page:</label>
                <select id="perPage" name="per_page" class="form-select form-select-sm" style="width: 90px;">
                    @foreach ([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($rowsPerPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="small text-muted">
                @if($total > 0)
                    Records {{ $from }} to {{ $to }} of {{ $total }}
                @else
                    No records found
                @endif
            </div>
        </div>
    </div>


    <div class="table-responsive">
        <table class="table align-middle">
            <thead class="table-light">
                <tr>
                                        <th>Peer Name</th>
                    <th>Collaboration Type</th>
                    <th>Title</th>
                    <th>Scope</th>
                    <th>Preferred Mode</th>
                    <th>Business Stage</th>
                    <th>Year in Operation</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                <tr>
                    <th><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="form-control form-control-sm"></th>
                    <th><input type="text" name="collaboration_type" value="{{ $filters['collaboration_type'] ?? '' }}" placeholder="Collaboration Type" class="form-control form-control-sm"></th>
                    <th><input type="text" name="title" value="{{ $filters['title'] ?? '' }}" placeholder="Title" class="form-control form-control-sm"></th>
                    <th><input type="text" name="scope" value="{{ $filters['scope'] ?? '' }}" placeholder="Scope" class="form-control form-control-sm"></th>
                    <th><input type="text" name="preferred_mode" value="{{ $filters['preferred_mode'] ?? '' }}" placeholder="Preferred Mode" class="form-control form-control-sm"></th>
                    <th><input type="text" name="business_stage" value="{{ $filters['business_stage'] ?? '' }}" placeholder="Business Stage" class="form-control form-control-sm"></th>
                    <th><input type="text" name="year_in_operation" value="{{ $filters['year_in_operation'] ?? '' }}" placeholder="Year in Operation" class="form-control form-control-sm"></th>
                    <th>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Any</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                        </select>
                    </th>
                    <th>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            <a href="{{ route('admin.collaborations.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    @php
                        $peerName = $post->peer_name
                            ?? $post->person_name
                            ?? $post->name
                            ?? '—';

                        $company = ($post->peer_company ?? null)
                            ?? $post->company
                            ?? $post->company_name
                            ?? $post->business_name
                            ?? '—';

                        $city = ($post->peer_city ?? null)
                            ?? $post->city
                            ?? $post->user_city
                            ?? '—';

                        $initial = mb_strtoupper(mb_substr($peerName !== '—' ? $peerName : 'U', 0, 1));
                        $typeName = $post->collaborationType?->name ?? CollaborationFormatter::humanize($post->collaboration_type);
                        $title = $post->title ?? $post->collaboration_title ?? $post->subject ?? '—';
                        $scope = CollaborationFormatter::humanize($post->scope ?? $post->collaboration_scope ?? $post->scope_text);
                        $preferredMode = CollaborationFormatter::humanize($post->preferred_mode ?? $post->preferred_model ?? $post->meeting_mode ?? $post->mode);
                        $businessStage = CollaborationFormatter::humanize($post->business_stage ?? $post->stage ?? $post->business_stage_text);
                        $yearInOperation = CollaborationFormatter::humanize($post->year_in_operation ?? $post->years_in_operation ?? $post->operating_years ?? $post->years);
                        $status = $post->status ?? '—';
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar avatar-sm rounded-circle border d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                    <span class="fw-semibold">{{ $initial }}</span>
                                </div>

                                <div class="min-w-0">
                                    @include('admin.components.peer-card', [
                                        'name' => $peerName,
                                        'company' => $company,
                                        'city' => $city,
                                    ])
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-primary-subtle text-primary">{{ $typeName }}</span></td>
                        <td>{{ $title }}</td>
                        <td>{{ $scope }}</td>
                        <td>{{ $preferredMode }}</td>
                        <td>{{ $businessStage }}</td>
                        <td>{{ $yearInOperation }}</td>
                        <td>
                            <span class="badge {{ strtolower((string) $status) === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ CollaborationFormatter::humanize((string) $status) }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.collaborations.show', ['id' => $post->id] + request()->query()) }}">Details</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No collaboration posts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
        <div>
            {{ $posts->appends(request()->query())->links() }}
        </div>
        <div class="small text-muted">
            @if($total > 0)
                Showing {{ $from }}-{{ $to }} of {{ $total }} records
            @else
                No records
            @endif
        </div>
    </div>
</div>

</form>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const perPage = document.getElementById('perPage');

        if (perPage) {
            perPage.addEventListener('change', () => {
                const params = new URLSearchParams(window.location.search);
                params.set('per_page', perPage.value);
                params.delete('page');
                window.location = `${window.location.pathname}?${params.toString()}`;
            });
        }
    });
</script>
@endpush
@endsection

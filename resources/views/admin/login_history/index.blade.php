@extends('admin.layouts.app')

@section('title', 'Login History')

@section('content')
<div class="card p-3">
    <form id="loginHistoryFiltersForm" method="GET" action="{{ route('admin.login-history.index') }}"></form>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="d-flex align-items-center gap-2">
            <label for="perPage" class="form-label mb-0 small text-muted">Rows per page:</label>
            <select id="perPage" name="per_page" form="loginHistoryFiltersForm" class="form-select form-select-sm" style="width: 90px;">
                @foreach ([10, 20, 50, 100] as $size)
                    <option value="{{ $size }}" @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <input
                type="datetime-local"
                name="from"
                form="loginHistoryFiltersForm"
                value="{{ $filters['from'] ?? '' }}"
                class="form-control form-control-sm"
                style="min-width: 180px;"
                title="From Time"
            >
            <input
                type="datetime-local"
                name="to"
                form="loginHistoryFiltersForm"
                value="{{ $filters['to'] ?? '' }}"
                class="form-control form-control-sm"
                style="min-width: 180px;"
                title="To Time"
            >
        </div>

        <div class="small text-muted">
            @if($records->total() > 0)
                Records {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }}
            @else
                No records found
            @endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead class="table-light">
                <tr>
                    <th>Peer Name</th>
                    <th>Circles</th>
                    <th>Last Login</th>
                </tr>
                <tr class="bg-light align-middle">
                    <th>
                        <input
                            type="text"
                            name="q"
                            form="loginHistoryFiltersForm"
                            class="form-control form-control-sm"
                            placeholder="Peer/Company/City/Circle"
                            value="{{ $filters['q'] ?? '' }}"
                        >
                    </th>
                    <th>
                        <select name="circle_id" form="loginHistoryFiltersForm" class="form-select form-select-sm">
                            <option value="">All Circles</option>
                            @foreach ($circleOptions as $id => $name)
                                <option value="{{ $id }}" @selected(($filters['circle_id'] ?? '') == (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <select name="join_status" form="loginHistoryFiltersForm" class="form-select form-select-sm mt-2">
                            <option value="all" @selected(($filters['join_status'] ?? 'all') === 'all')>All</option>
                            <option value="joined" @selected(($filters['join_status'] ?? 'all') === 'joined')>Joined</option>
                            <option value="not_joined" @selected(($filters['join_status'] ?? 'all') === 'not_joined')>Not Joined</option>
                        </select>
                    </th>
                    <th>
                        <input
                            type="date"
                            name="last_login_date"
                            form="loginHistoryFiltersForm"
                            class="form-control form-control-sm mb-2"
                            value="{{ $filters['last_login_date'] ?? '' }}"
                        >
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="submit" form="loginHistoryFiltersForm" class="btn btn-primary btn-sm">Apply</button>
                            <a href="{{ route('admin.login-history.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $peerName = $record->peer_name ?: '—';
                        $peerCompany = $record->company ?: 'No Company';
                        $peerCity = $record->city ?: 'No City';
                        $peerCircle = ! empty($record->circles_names) ? explode(', ', $record->circles_names)[0] : 'No Circle';
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex flex-column">
                                <div class="fw-semibold text-dark">{{ $peerName }}</div>
                                <div class="text-muted small">{{ $peerCompany }}</div>
                                <div class="text-muted small">{{ $peerCity }}</div>
                                <div class="text-muted small">{{ $peerCircle }}</div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">{{ (int) $record->circles_count }}</span>
                            @if ((int) $record->circles_count === 0)
                                <span class="ms-1 text-muted">Not Joined</span>
                            @elseif (! empty($record->circles_names))
                                <span class="ms-1">{{ $record->circles_names }}</span>
                            @endif
                        </td>
                        <td>{{ $record->last_login_at ? \Illuminate\Support\Carbon::parse($record->last_login_at)->format('d-m-Y h:i A') : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">No records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-2">
        {{ $records->links() }}
    </div>
</div>
@endsection

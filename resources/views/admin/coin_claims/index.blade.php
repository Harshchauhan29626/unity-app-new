@extends('admin.layouts.app')

@section('title', 'Coin Claims')

@section('content')
    @php
        $displayName = function ($user): string {
            if (! $user) {
                return '—';
            }

            if (! empty($user->name)) {
                return (string) $user->name;
            }

            if (! empty($user->display_name)) {
                return (string) $user->display_name;
            }

            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            return $name !== '' ? $name : '—';
        };
    @endphp

    <form id="coinClaimsFiltersForm" method="GET" action="{{ route('admin.coin-claims.index') }}"></form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Coin Claims</h1>
        <span class="badge bg-light text-dark border">Total: {{ number_format($claims->total()) }}</span>
    </div>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="q" form="coinClaimsFiltersForm" value="{{ $filters['q'] }}" class="form-control" placeholder="Search peer/activity/key fields">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" form="coinClaimsFiltersForm" class="form-select">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                        <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Circle</label>
                    <select name="circle_id" form="coinClaimsFiltersForm" class="form-select">
                        <option value="all">All Circles</option>
                        @foreach($circles as $circle)
                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" form="coinClaimsFiltersForm" class="btn btn-primary">Apply</button>
                    <a href="{{ route('admin.coin-claims.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Peer Name</th>
                    <th>Peer Phone</th>
                    <th>Activity</th>
                    <th>Key Fields</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                <tr>
                    <th>
                        <input type="text" name="peer_q" form="coinClaimsFiltersForm" class="form-control form-control-sm" placeholder="Peer/Company/City" value="{{ $filters['peer_q'] }}">
                    </th>
                    <th>
                        <input type="text" name="peer_phone" form="coinClaimsFiltersForm" class="form-control form-control-sm" placeholder="Peer Phone" value="{{ $filters['peer_phone'] }}">
                    </th>
                    <th>
                        <input type="text" name="activity" form="coinClaimsFiltersForm" class="form-control form-control-sm" placeholder="Activity" value="{{ $filters['activity'] }}">
                    </th>
                    <th>
                        <input type="text" name="key_fields" form="coinClaimsFiltersForm" class="form-control form-control-sm" placeholder="Search key fields" value="{{ $filters['key_fields'] }}">
                    </th>
                    <th>
                        <select name="status" form="coinClaimsFiltersForm" class="form-select form-select-sm">
                            <option value="all" @selected($filters['status'] === 'all')>All</option>
                            <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                            <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                            <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                        </select>
                    </th>
                    <th class="text-end">
                        <div class="d-inline-flex align-items-center gap-2" style="white-space:nowrap;">
                            <button type="submit" form="coinClaimsFiltersForm" class="btn btn-sm btn-primary">Apply</button>
                            <a href="{{ route('admin.coin-claims.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </div>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse ($claims as $claim)
                    @php
                        $user = $claim->user;
                        $company = $user->company_name ?? $user->company ?? $user->business_name ?? 'No Company';
                        $city = $user->city ?? 'No City';
                        $circleName = optional($user?->circleMembers?->first()?->circle)->name ?? 'No Circle';
                        $fields = (array) data_get($claim->payload, 'fields', []);
                        $keyFields = collect($fields)
                            ->except(collect($fields)->keys()->filter(fn($k) => str_ends_with($k, '_normalized'))->all())
                            ->map(fn($v,$k)=>$k.': '.$v)
                            ->take(4)
                            ->implode(', ');
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex flex-column">
                                <div class="fw-semibold">{{ $displayName($user) }}</div>
                                <div class="text-muted small">{{ $company }}</div>
                                <div class="text-muted small">{{ $city }}</div>
                                <div class="text-muted small">{{ $circleName }}</div>
                            </div>
                        </td>
                        <td>{{ $user->phone ?? '—' }}</td>
                        <td>{{ data_get($registry->get($claim->activity_code), 'label', $claim->activity_code) }}</td>
                        <td>{{ $keyFields ?: '—' }}</td>
                        <td>{{ ucfirst($claim->status) }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.coin-claims.show', $claim->id) }}" class="btn btn-sm btn-outline-primary">Details</a>
                            @if ($claim->status === 'pending')
                                <form method="POST" action="{{ route('admin.coin-claims.approve', $claim->id) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-success" onclick="return confirm('Approve this claim?')">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.coin-claims.reject', $claim->id) }}" class="d-inline">@csrf
                                    <input type="hidden" name="admin_notes" value="Rejected by admin">
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this claim?')">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No coin claims found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $claims->links() }}</div>
@endsection

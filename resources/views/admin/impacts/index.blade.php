@extends('admin.layouts.app')

@section('title', 'Impact')

@section('content')
    @php
        $displayUser = function ($user): string {
            if (! $user) {
                return '—';
            }

            if (! empty($user->display_name)) {
                return (string) $user->display_name;
            }

            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            return $name !== '' ? $name : ((string) ($user->email ?? '—'));
        };

        $statusBadge = function (?string $status): string {
            return match ($status) {
                'approved' => 'bg-success-subtle text-success border border-success-subtle',
                'rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
                default => 'bg-warning-subtle text-warning border border-warning-subtle',
            };
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Impact</h1>
        <span class="badge bg-light text-dark border">Total: {{ number_format($impacts->total()) }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Manage Impact Actions</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.impacts.actions.store') }}" class="row g-2 align-items-end mb-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Action Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255" placeholder="Enter action name">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Add Action</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Action Name</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($impactActionItems as $actionItem)
                        <tr class="impact-action-row" data-action-index="{{ $loop->index }}">
                            <td>{{ $actionItem->name }}</td>
                            <td>
                                <span class="badge {{ $actionItem->is_active ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $actionItem->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted py-2">No actions found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($impactActionItems->count() > 6)
                <div class="mt-3">
                    <button type="button" id="impactActionsViewMoreBtn" class="btn btn-outline-secondary btn-sm">
                        View More
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Create Impact</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.impacts.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Action <span class="text-danger">*</span></label>
                        <select name="action" class="form-select" required>
                            <option value="">Select action</option>
                            @foreach($impactActions as $action)
                                <option value="{{ $action }}" @selected(old('action') === $action)>{{ $action }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Impacted Peer <span class="text-danger">*</span></label>
                        <select name="impacted_peer_id" class="form-select" required>
                            <option value="">Select peer</option>
                            @foreach($peers as $peer)
                                <option value="{{ $peer->id }}" @selected(old('impacted_peer_id') === (string) $peer->id)>
                                    {{ $peer->adminFounderDropdownLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Story to Share <span class="text-danger">*</span></label>
                        <textarea name="story_to_share" class="form-control" rows="3" required>{{ old('story_to_share') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Additional Remarks</label>
                        <textarea name="additional_remarks" class="form-control" rows="3">{{ old('additional_remarks') }}</textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Create Impact</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <form id="impactTableFiltersForm" method="GET" action="{{ route('admin.impacts.index') }}"></form>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Add Impact Details</th>
                        <th>Impacted Peer</th>
                        <th>Submitted By / User</th>
                        <th>Life Impacted</th>
                        <th>Status</th>
                        <th>Review Remarks</th>
                        <th>Approved By</th>
                        <th>Approved At</th>
                        <th>Created At</th>
                        <th class="text-end">
                            <div class="d-inline-flex align-items-center gap-2">
                                <span>Actions</span>
                                <a href="{{ route('admin.impacts.export.csv', request()->query()) }}" class="btn btn-sm btn-outline-secondary">Export CSV</a>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th>
                            <input type="date" name="filter_date" form="impactTableFiltersForm" class="form-control form-control-sm" value="{{ $filters['filter_date'] ?? '' }}">
                        </th>
                        <th>
                            <select name="filter_action" form="impactTableFiltersForm" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach($impactActions as $action)
                                    <option value="{{ $action }}" @selected(($filters['filter_action'] ?? '') === $action)>{{ $action }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <input type="text" name="filter_impacted_peer" form="impactTableFiltersForm" class="form-control form-control-sm" placeholder="Peer/Company/City" value="{{ $filters['filter_impacted_peer'] ?? '' }}">
                        </th>
                        <th>
                            <input type="text" name="filter_submitted_by" form="impactTableFiltersForm" class="form-control form-control-sm" placeholder="User/Company/City" value="{{ $filters['filter_submitted_by'] ?? '' }}">
                        </th>
                        <th></th>
                        <th>
                            <select name="filter_status" form="impactTableFiltersForm" class="form-select form-select-sm">
                                <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All</option>
                                <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                                <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Approved</option>
                                <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
                            </select>
                        </th>
                        <th></th>
                        <th>
                            <input type="text" name="filter_approved_by" form="impactTableFiltersForm" class="form-control form-control-sm" placeholder="Approved by" value="{{ $filters['filter_approved_by'] ?? '' }}">
                        </th>
                        <th></th>
                        <th></th>
                        <th class="text-end">
                            <div class="d-inline-flex align-items-center gap-2">
                                <button type="submit" form="impactTableFiltersForm" class="btn btn-sm btn-primary">Apply</button>
                                <a href="{{ route('admin.impacts.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($impacts as $impact)
                        <tr>
                            <td>{{ optional($impact->impact_date)->toDateString() }}</td>
                            <td>{{ $impact->action }}</td>
                            <td>{{ $displayUser($impact->impactedPeer) }}</td>
                            <td>{{ $displayUser($impact->user) }}</td>
                            <td>{{ (int) ($impact->life_impacted ?? 1) }}</td>
                            <td><span class="badge {{ $statusBadge($impact->status) }}">{{ ucfirst($impact->status ?? 'pending') }}</span></td>
                            <td>{{ $impact->review_remarks ?: '—' }}</td>
                            <td>{{ $impact->approvedBy?->name ?: '—' }}</td>
                            <td>{{ optional($impact->approved_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td>{{ optional($impact->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.impacts.show', $impact->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">No impacts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $impacts->links() }}</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rows = Array.from(document.querySelectorAll('.impact-action-row'));
            const button = document.getElementById('impactActionsViewMoreBtn');

            if (!button || rows.length <= 6) {
                return;
            }

            let visibleLimit = 6;

            const render = () => {
                rows.forEach((row, index) => {
                    row.style.display = index < visibleLimit ? '' : 'none';
                });

                if (visibleLimit >= rows.length) {
                    button.style.display = 'none';
                    return;
                }

                button.textContent = visibleLimit >= 12 ? 'View All' : 'View More';
            };

            button.addEventListener('click', () => {
                if (visibleLimit < 12) {
                    visibleLimit = 12;
                } else {
                    visibleLimit = rows.length;
                }

                render();
            });

            render();
        });
    </script>
@endpush

@extends('admin.layouts.app')

@section('title', 'Register A Visitor')

@section('content')
    <style>
        .peer-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; display: block; }
    </style>
    @php
        $displayName = function (?string $display, ?string $first, ?string $last): string {
            if ($display) {
                return $display;
            }
            $name = trim(($first ?? '') . ' ' . ($last ?? ''));
            return $name !== '' ? $name : '—';
        };

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '—';
        };

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '—';
        };
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Register A Visitor</h1>
        <span class="badge bg-light text-dark border">Total: {{ number_format($items->total()) }}</span>
    </div>

    <form id="adminactivitiesregister-visitorindexFiltersForm" method="GET" action="{{ route('admin.activities.register-visitor.index') }}">
    @include('admin.components.activity-filter-bar-v2', [
        'actionUrl' => route('admin.activities.register-visitor.index'),
        'resetUrl' => route('admin.activities.register-visitor.index'),
        'filters' => $filters,
        'circles' => $circles ?? collect(),
        'showExport' => false,
        'renderFormTag' => false,
        'formId' => 'adminactivitiesregister-visitorindexFiltersForm',
    ])

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Submitted At</th>
                        <th>Peer Name</th>
                        <th>Peer Phone</th>
                        <th>Event Type</th>
                        <th>Event Name</th>
                        <th>Event Date</th>
                        <th>Visitor Name</th>
                        <th>Visitor Mobile</th>
                        <th>Visitor City</th>
                        <th>Visitor Business</th>
                        <th>Status</th>
                        <th>Coins Awarded</th>
                        <th class="text-end">Actions</th>
                        <th>Created At</th>
                    </tr>
                    <tr>
                        <th class="text-muted">—</th>
                        <th><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="form-control form-control-sm"></th>
                        <th><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Peer Phone" class="form-control form-control-sm"></th>
                        <th><input type="text" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="Event Type" class="form-control form-control-sm"></th>
                        <th><input type="text" name="event_name" value="{{ $filters['event_name'] ?? '' }}" placeholder="Event Name" class="form-control form-control-sm"></th>
                        <th><input type="date" name="event_date" value="{{ $filters['event_date'] ?? '' }}" class="form-control form-control-sm"></th>
                        <th><input type="text" name="visitor_name" value="{{ $filters['visitor_name'] ?? '' }}" placeholder="Visitor Name" class="form-control form-control-sm"></th>
                        <th><input type="text" name="visitor_mobile" value="{{ $filters['visitor_mobile'] ?? '' }}" placeholder="Visitor Mobile" class="form-control form-control-sm"></th>
                        <th><input type="text" name="visitor_city" value="{{ $filters['visitor_city'] ?? '' }}" placeholder="Visitor City" class="form-control form-control-sm"></th>
                        <th><input type="text" name="visitor_business" value="{{ $filters['visitor_business'] ?? '' }}" placeholder="Visitor Business" class="form-control form-control-sm"></th>
                        <th><input type="text" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="Status" class="form-control form-control-sm"></th>
                        <th><input type="number" name="coins_awarded" value="{{ $filters['coins_awarded'] ?? '' }}" placeholder="Coins" class="form-control form-control-sm"></th>
                        <th class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                <a href="{{ route('admin.activities.register-visitor.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </th>
                        <th class="text-muted">—</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $peerName = $item->peer_name ?? '—';
                            $visitorSearch = $item->visitor_mobile ? ['search' => $item->visitor_mobile] : [];
                        @endphp
                        <tr>
                            <td>{{ $formatDateTime($item->created_at ?? null) }}</td>
                            <td>
                                @include('admin.components.peer-card', [
                                    'name' => $peerName,
                                    'company' => $item->peer_company ?? '',
                                    'city' => $item->peer_city ?? '',
                                ])
                            </td>
                            <td>{{ $item->peer_phone ?? '—' }}</td>
                            <td>{{ ucfirst($item->event_type ?? '—') }}</td>
                            <td>{{ $item->event_name ?? '—' }}</td>
                            <td>{{ $formatDate($item->event_date ?? null) }}</td>
                            <td>{{ $item->visitor_full_name ?? '—' }}</td>
                            <td>{{ $item->visitor_mobile ?? '—' }}</td>
                            <td>{{ $item->visitor_city ?? '—' }}</td>
                            <td>{{ $item->visitor_business ?? '—' }}</td>
                            <td>{{ ucfirst($item->status ?? '—') }}</td>
                            <td>{{ $item->coins_awarded ? 'Yes' : 'No' }}</td>
                            <td class="text-end">
                                @if ($item->visitor_mobile)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.visitor-registrations.index', $visitorSearch) }}">
                                        Open Approval Page
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $formatDateTime($item->created_at ?? null) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted">No visitor registrations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    </form>

    <div class="mt-3">
        {{ $items->links() }}
    </div>
@endsection

@extends('admin.layouts.app')

@section('title', 'Recommend A Peer')

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
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Recommend A Peer</h1>
        <span class="badge bg-light text-dark border">Total: {{ number_format($items->total()) }}</span>
    </div>

    <form id="adminactivitiesrecommend-peerindexFiltersForm" method="GET" action="{{ route('admin.activities.recommend-peer.index') }}">
    @include('admin.components.activity-filter-bar-v2', [
        'actionUrl' => route('admin.activities.recommend-peer.index'),
        'resetUrl' => route('admin.activities.recommend-peer.index'),
        'filters' => $filters,
        'circles' => $circles ?? collect(),
        'showExport' => false,
        'renderFormTag' => false,
        'formId' => 'adminactivitiesrecommend-peerindexFiltersForm',
    ])

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Submitted At</th>
                        <th>Peer Name</th>
                        <th>Peer Phone</th>
                        <th>Recommended Peer Name</th>
                        <th>Recommended Peer Mobile</th>
                        <th>How Well Known</th>
                        <th>Is Aware</th>
                        <th>Coins Awarded</th>
                        <th>Created At</th>
                    </tr>
                    <tr>
                        <th class="text-muted">—</th>
                        <th><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="form-control form-control-sm"></th>
                        <th><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Peer Phone" class="form-control form-control-sm"></th>
                        <th><input type="text" name="recommended_peer_name" value="{{ $filters['recommended_peer_name'] ?? '' }}" placeholder="Recommended Peer Name" class="form-control form-control-sm"></th>
                        <th><input type="text" name="recommended_peer_mobile" value="{{ $filters['recommended_peer_mobile'] ?? '' }}" placeholder="Recommended Peer Mobile" class="form-control form-control-sm"></th>
                        <th><input type="text" name="how_well_known" value="{{ $filters['how_well_known'] ?? '' }}" placeholder="How Well Known" class="form-control form-control-sm"></th>
                        <th>
                            <select name="is_aware" class="form-select form-select-sm">
                                <option value="">Any</option>
                                <option value="yes" @selected(($filters['is_aware'] ?? '')==='yes')>Yes</option>
                                <option value="no" @selected(($filters['is_aware'] ?? '')==='no')>No</option>
                            </select>
                        </th>
                        <th><input type="number" name="coins_awarded" value="{{ $filters['coins_awarded'] ?? '' }}" placeholder="Coins" class="form-control form-control-sm"></th>
                        <th>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                <a href="{{ route('admin.activities.recommend-peer.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $peerName = $item->from_user_name ?? '—';
                        @endphp
                        <tr>
                            <td>{{ $formatDateTime($item->created_at ?? null) }}</td>
                            <td>
                                @include('admin.components.peer-card', [
                                    'name' => $peerName,
                                    'company' => $item->from_company ?? '',
                                    'city' => $item->from_city ?? '',
                                ])
                            </td>
                            <td>{{ $item->from_phone ?? '—' }}</td>
                            <td>{{ $item->peer_name ?? '—' }}</td>
                            <td>{{ $item->peer_mobile ?? '—' }}</td>
                            <td>{{ $item->how_well_known ?? '—' }}</td>
                            <td>{{ $item->is_aware ? 'Yes' : 'No' }}</td>
                            <td>{{ $item->coins_awarded ? 'Yes' : 'No' }}</td>
                            <td>{{ $formatDateTime($item->created_at ?? null) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">No recommendations found.</td>
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

@extends('admin.layouts.app')

@section('title', 'Become A Leader')

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

        $formatRoles = function ($roles): string {
            if (! $roles) {
                return '—';
            }
            $list = is_array($roles) ? $roles : (array) $roles;
            $list = array_filter($list);
            return $list ? implode(', ', $list) : '—';
        };

        $truncate = function ($value, int $limit = 80): string {
            return $value ? \Illuminate\Support\Str::limit($value, $limit) : '—';
        };
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Become A Leader</h1>
        <span class="badge bg-light text-dark border">Total: {{ number_format($items->total()) }}</span>
    </div>

    <form id="adminactivitiesbecome-a-leaderindexFiltersForm" method="GET" action="{{ route('admin.activities.become-a-leader.index') }}">
    @include('admin.components.activity-filter-bar-v2', [
        'actionUrl' => route('admin.activities.become-a-leader.index'),
        'resetUrl' => route('admin.activities.become-a-leader.index'),
        'filters' => $filters,
        'circles' => $circles ?? collect(),
        'showExport' => false,
        'renderFormTag' => false,
        'formId' => 'adminactivitiesbecome-a-leaderindexFiltersForm',
    ])

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Submitted At</th>
                        <th>Peer Name</th>
                        <th>Peer Phone</th>
                        <th>Applying For</th>
                        <th>Referred Name</th>
                        <th>Referred Mobile</th>
                        <th>Leadership Roles</th>
                        <th>City / Region</th>
                        <th>Primary Domain</th>
                        <th>Why Interested</th>
                        <th>Created At</th>
                    </tr>
                    <tr>
                        <th class="text-muted">—</th>
                        <th><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="form-control form-control-sm"></th>
                        <th><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Peer Phone" class="form-control form-control-sm"></th>
                        <th><input type="text" name="applying_for" value="{{ $filters['applying_for'] ?? '' }}" placeholder="Applying For" class="form-control form-control-sm"></th>
                        <th><input type="text" name="referred_name" value="{{ $filters['referred_name'] ?? '' }}" placeholder="Referred Name" class="form-control form-control-sm"></th>
                        <th><input type="text" name="referred_mobile" value="{{ $filters['referred_mobile'] ?? '' }}" placeholder="Referred Mobile" class="form-control form-control-sm"></th>
                        <th><input type="text" name="leadership_roles" value="{{ $filters['leadership_roles'] ?? '' }}" placeholder="Leadership Roles" class="form-control form-control-sm"></th>
                        <th><input type="text" name="city_region" value="{{ $filters['city_region'] ?? '' }}" placeholder="City / Region" class="form-control form-control-sm"></th>
                        <th><input type="text" name="primary_domain" value="{{ $filters['primary_domain'] ?? '' }}" placeholder="Primary Domain" class="form-control form-control-sm"></th>
                        <th class="text-muted">—</th>
                        <th>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                <a href="{{ route('admin.activities.become-a-leader.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $peerName = $item->peer_name ?? '—';
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
                            <td>{{ $item->applying_for ?? '—' }}</td>
                            <td>{{ $item->referred_name ?? '—' }}</td>
                            <td>{{ $item->referred_mobile ?? '—' }}</td>
                            <td>{{ $formatRoles($item->leadership_roles ?? null) }}</td>
                            <td>{{ $item->contribute_city ?? '—' }}</td>
                            <td>{{ $item->primary_domain ?? '—' }}</td>
                            <td>{{ $truncate($item->why_interested ?? null) }}</td>
                            <td>{{ $formatDateTime($item->created_at ?? null) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted">No submissions found.</td>
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

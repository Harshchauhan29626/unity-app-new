@extends('admin.layouts.app')

@section('title', ($circle->name ?? 'Circle') . ' Circle')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0">{{ $circle->name ?? 'Circle' }}</h5>
        <small class="text-muted">Circle details and peers</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.circles.edit', $circle) }}" class="btn btn-outline-primary btn-sm">Edit Circle</a>

        <form action="{{ route('admin.circles.destroy', $circle) }}" method="POST" class="d-inline"
              onsubmit="return confirm('Delete this circle? This is a soft delete and can be restored by admin.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
        </form>

        <a href="{{ route('admin.circles.index') }}" class="btn btn-outline-secondary btn-sm">Back to Circles</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>There were some problems with your input.</strong>
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    use Illuminate\Pagination\LengthAwarePaginator;
    use Illuminate\Pagination\Paginator;
    use Illuminate\Support\Collection;

    $circleCategories = collect();

    if (($categoryFeatureEnabled ?? false) && method_exists($circle, 'categories')) {
        try {
            $circleCategories = $circle->categories ?? collect();
        } catch (\Throwable $e) {
            $circleCategories = collect();
        }
    }

    $circleStatus = data_get($circle, 'status') ?: 'pending';
    $circleType = data_get($circle, 'type') ?: '—';
    $circleSlug = data_get($circle, 'slug') ?: '—';
    $circleCity = data_get($circle, 'city.name') ?: '—';
    $circleCountry = data_get($circle, 'city.country') ?: (data_get($circle, 'country') ?: '—');

    $circleFounder = data_get($circle, 'founder.display_name')
        ?: data_get($circle, 'founder.name')
        ?: trim((string) data_get($circle, 'founder.first_name', '') . ' ' . (string) data_get($circle, 'founder.last_name', ''));
    $circleFounder = trim((string) $circleFounder) !== '' ? trim((string) $circleFounder) : '—';

    $circleDescription = data_get($circle, 'description') ?: '—';
    $circlePurpose = data_get($circle, 'purpose') ?: '—';
    $circleAnnouncement = data_get($circle, 'announcement') ?: '—';

    $industryTags = data_get($circle, 'industry_tags');
    if (is_array($industryTags)) {
        $industryTagsText = implode(', ', array_filter($industryTags));
    } elseif (is_string($industryTags) && trim($industryTags) !== '') {
        $industryTagsText = $industryTags;
    } else {
        $industryTagsText = '—';
    }

    $displayValue = static function ($value) {
        if (is_string($value)) {
            $value = trim($value);
        }

        return filled($value)
            ? '<span class="fw-semibold text-dark">' . e($value) . '</span>'
            : '<span class="text-muted">—</span>';
    };

    $formatUser = static function ($user) {
        if (! $user) {
            return null;
        }

        $name = data_get($user, 'name')
            ?: data_get($user, 'display_name')
            ?: trim((string) data_get($user, 'first_name', '') . ' ' . (string) data_get($user, 'last_name', ''));

        $name = trim((string) $name);
        $email = trim((string) data_get($user, 'email', ''));

        if ($name !== '' && $email !== '') {
            return $name . ' (' . $email . ')';
        }

        return $name !== '' ? $name : ($email !== '' ? $email : null);
    };

    $calendar = is_array($circle->calendar ?? null) ? $circle->calendar : [];

    $meetingMode = data_get($circle, 'meeting_mode');
    if (! $meetingMode) {
        $meetingMode = data_get($calendar, 'settings.meeting_mode');
    }
    $meetingMode = $meetingMode ? ucfirst(strtolower((string) $meetingMode)) : null;

    $meetingFrequency = data_get($circle, 'meeting_frequency');
    if (! $meetingFrequency) {
        $meetingFrequency = data_get($calendar, 'settings.meeting_frequency');
    }
    $meetingFrequency = $meetingFrequency ? ucfirst(strtolower((string) $meetingFrequency)) : null;

    $launchDateRaw = data_get($circle, 'launch_date') ?: data_get($calendar, 'settings.launch_date');
    $launchDate = '—';
    if (! empty($launchDateRaw)) {
        try {
            $launchDate = \Illuminate\Support\Carbon::parse($launchDateRaw)->format('d M Y');
        } catch (\Throwable $e) {
            $launchDate = (string) $launchDateRaw;
        }
    }

    $meetingRepeat = data_get($circle, 'meeting_repeat');
    if (! is_array($meetingRepeat)) {
        $meetingRepeat = data_get($calendar, 'settings.meeting_repeat');
    }
    $meetingRepeat = is_array($meetingRepeat) ? $meetingRepeat : null;

    $coverFileId = data_get($circle, 'cover_file_id');
    if (! $coverFileId) {
        $coverFileId = data_get($calendar, 'cover.file_id');
    }

    $peerFilters = is_array($peerFilters ?? null) ? $peerFilters : [
        'peer_name' => request('peer_name', ''),
        'peer_email' => request('peer_email', ''),
    ];

    $peerNameFilter = trim((string) ($peerFilters['peer_name'] ?? ''));
    $peerEmailFilter = trim((string) ($peerFilters['peer_email'] ?? ''));

    $membersSource = $peerMembers ?? ($circle->members ?? collect());

    $isPaginator = $membersSource instanceof LengthAwarePaginator
        || $membersSource instanceof \Illuminate\Contracts\Pagination\Paginator
        || $membersSource instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    if ($isPaginator) {
        $peerMembers = $membersSource;
        $peerItems = collect($peerMembers->items());
        $peerCurrentPage = method_exists($peerMembers, 'currentPage') ? $peerMembers->currentPage() : 1;
        $peerHasPagination = true;
    } else {
        $peerItems = $membersSource instanceof Collection ? $membersSource : collect($membersSource);

        if ($peerNameFilter !== '' || $peerEmailFilter !== '') {
            $peerItems = $peerItems->filter(function ($membership) use ($peerNameFilter, $peerEmailFilter) {
                $member = $membership->user ?? null;

                $memberName = trim(
                    (string) data_get($member, 'name',
                        trim((string) data_get($member, 'first_name', '') . ' ' . (string) data_get($member, 'last_name', ''))
                    )
                );

                if ($memberName === '') {
                    $memberName = trim((string) data_get($member, 'display_name', ''));
                }

                $memberEmail = trim((string) data_get($member, 'email', ''));

                $nameMatch = $peerNameFilter === '' || str_contains(mb_strtolower($memberName), mb_strtolower($peerNameFilter));
                $emailMatch = $peerEmailFilter === '' || str_contains(mb_strtolower($memberEmail), mb_strtolower($peerEmailFilter));

                return $nameMatch && $emailMatch;
            })->values();
        }

        $page = max((int) request('page', 1), 1);
        $perPage = 10;
        $total = $peerItems->count();
        $itemsForPage = $peerItems->slice(($page - 1) * $perPage, $perPage)->values();

        $peerMembers = new LengthAwarePaginator(
            $itemsForPage,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        $peerCurrentPage = $page;
        $peerHasPagination = $total > $perPage;
        $peerItems = collect($peerMembers->items());
    }

    if ($isPaginator) {
        $peerItems = collect($peerMembers->items());
    }

    $circleStage = $circleStage ?? data_get($circle, 'stage.name') ?? data_get($circle, 'circleStage.name') ?? data_get($circle, 'circle_stage') ?? null;
@endphp

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Circle Overview</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge badge-soft-secondary text-uppercase">{{ $circleStatus }}</span>
                    <span class="badge bg-light text-dark text-uppercase">{{ $circleType }}</span>
                </div>

                <dl class="row mb-0">
                    <dt class="col-sm-4">Slug</dt>
                    <dd class="col-sm-8">{{ $circleSlug }}</dd>

                    <dt class="col-sm-4">City</dt>
                    <dd class="col-sm-8">{{ $circleCity }}</dd>

                    <dt class="col-sm-4">Country</dt>
                    <dd class="col-sm-8">{{ $circleCountry }}</dd>

                    <dt class="col-sm-4">Founder</dt>
                    <dd class="col-sm-8">{{ $circleFounder }}</dd>

                    <dt class="col-sm-4">Created</dt>
                    <dd class="col-sm-8">{{ optional($circle->created_at)->format('Y-m-d H:i') ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Narrative & Tags</div>
            <div class="card-body">
                <p class="text-muted mb-2"><strong>Description:</strong> {{ $circleDescription }}</p>
                <p class="text-muted mb-2"><strong>Purpose:</strong> {{ $circlePurpose }}</p>
                <p class="text-muted mb-2"><strong>Announcement:</strong> {{ $circleAnnouncement }}</p>
                <p class="text-muted mb-2"><strong>Industry Tags:</strong> {{ $industryTagsText }}</p>

                @if($categoryFeatureEnabled ?? false)
                    <div>
                        <strong class="text-muted">Categories:</strong>
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            @forelse($circleCategories as $category)
                                <span class="badge bg-light text-dark border">
                                    {{ data_get($category, 'category_name', '—') }}
                                </span>
                            @empty
                                <span class="text-muted">No categories assigned</span>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold">Circle Settings</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="small text-muted">Meeting Mode</div>
                {!! $displayValue($meetingMode) !!}
            </div>

            <div class="col-md-4">
                <div class="small text-muted">Meeting Frequency</div>
                {!! $displayValue($meetingFrequency) !!}
            </div>

            <div class="col-md-4">
                <div class="small text-muted">Launch Date</div>
                {!! $displayValue($launchDate !== '—' ? $launchDate : null) !!}
            </div>

            <div class="col-md-4">
                <div class="small text-muted">Circle Stage</div>
                {!! $displayValue($circleStage) !!}
            </div>

            <div class="col-md-4">
                <div class="small text-muted">Director</div>
                {!! $displayValue($formatUser($circle->director ?? null)) !!}
            </div>

            <div class="col-md-4">
                <div class="small text-muted">Industry Director</div>
                {!! $displayValue($formatUser($circle->industryDirector ?? null)) !!}
            </div>

            <div class="col-md-4">
                <div class="small text-muted">DED</div>
                {!! $displayValue($formatUser($circle->ded ?? null)) !!}
            </div>

            <div class="col-md-8">
                <div class="small text-muted">Meeting Repeat</div>
                @if ($meetingRepeat)
                    <div class="small bg-light border rounded p-2">
                        @foreach ($meetingRepeat as $key => $value)
                            <div>
                                <span class="text-muted">{{ ucwords(str_replace('_', ' ', (string) $key)) }}:</span>
                                <span class="fw-semibold">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-muted">—</div>
                @endif
            </div>

            <div class="col-md-4">
                <div class="small text-muted">Cover</div>
                @if ($coverFileId)
                    <div class="d-flex flex-column gap-2">
                        <img src="{{ url('/api/v1/files/' . $coverFileId) }}" alt="Circle Cover"
                             class="rounded border"
                             style="max-height: 120px; width: auto; object-fit: cover;">
                        <div>
                            <a href="{{ url('/api/v1/files/' . $coverFileId) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                        </div>
                    </div>
                @else
                    <div class="text-muted">—</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold">Circle Ranking</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="small text-muted">Total Members</div>
                <div class="fw-semibold text-dark">{{ data_get($rankingData ?? [], 'total_members', 0) }}</div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Rank</div>
                <div class="fw-semibold text-dark">{{ data_get($rankingData ?? [], 'rank', '—') }}</div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Circle Title</div>
                <div class="fw-semibold text-dark">{{ data_get($rankingData ?? [], 'title', '—') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold">Meeting Schedule</div>
    <div class="card-body">
        @if (empty($meetingRows ?? []))
            <div class="text-muted">—</div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($meetingRows as $meetingRow)
                    <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                        <span>
                            <strong>{{ data_get($meetingRow, 'label', 'Meeting') }}:</strong>
                            {{ data_get($meetingRow, 'value', '—') }}
                        </span>
                    </li>
                @endforeach
            </ul>
            <div class="small text-muted mt-2">Timezone: {{ $timezone ?? 'Asia/Kolkata' }}</div>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold">Peers</div>
    <div class="card-body">
        <form action="{{ route('admin.circles.members.store', $circle) }}" method="POST" class="row g-2 align-items-end mb-4">
            @csrf
            <input type="hidden" name="peer_name" value="{{ $peerNameFilter }}">
            <input type="hidden" name="peer_email" value="{{ $peerEmailFilter }}">
            <input type="hidden" name="page" value="{{ $peerCurrentPage }}">

            <div class="col-md-6">
                <label class="form-label">Select Peer</label>
                <select id="peer_select" name="user_id" class="form-select" required></select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    @foreach (($roles ?? []) as $role)
                        <option value="{{ $role }}">{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <button class="btn btn-primary w-100">Add Peer</button>
            </div>
        </form>

        <form id="peerFilterForm" method="GET" action="{{ route('admin.circles.show', $circle) }}" class="d-none"></form>

        <div class="table-responsive" style="overflow-x: auto;">
            <table class="table align-middle" style="white-space: nowrap;">
                <thead class="table-light">
                    <tr>
                        <th>Peers</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    <tr>
                        <th>
                            <input
                                id="peer_name"
                                type="text"
                                name="peer_name"
                                form="peerFilterForm"
                                value="{{ $peerNameFilter }}"
                                class="form-control form-control-sm"
                                placeholder="Search peer name"
                            >
                        </th>
                        <th>
                            <input
                                id="peer_email"
                                type="text"
                                name="peer_email"
                                form="peerFilterForm"
                                value="{{ $peerEmailFilter }}"
                                class="form-control form-control-sm"
                                placeholder="Search email"
                            >
                        </th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end">
                                <button type="submit" form="peerFilterForm" class="btn btn-sm btn-outline-primary">Filter</button>
                                <a href="{{ route('admin.circles.show', $circle) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($peerItems as $membership)
                        @php
                            $member = $membership->user ?? null;

                            $memberName = trim((string) (($member->first_name ?? '') . ' ' . ($member->last_name ?? '')));
                            if ($memberName === '') {
                                $memberName = trim((string) ($member->display_name ?? ($member->name ?? '')));
                            }

                            $memberCompany = trim((string) ($member->company_name ?? ''));
                            if ($memberCompany === '') {
                                $memberCompany = trim((string) ($member->business_name ?? ''));
                            }

                            $memberCity = trim((string) ($member->city ?? ''));
                        @endphp

                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $memberName !== '' ? $memberName : '—' }}</div>
                                <div class="text-muted small">{{ $memberCompany !== '' ? $memberCompany : 'No Company' }}</div>
                                <div class="text-muted small">{{ $memberCity !== '' ? $memberCity : 'No City' }}</div>
                            </td>

                            <td>{{ $member->email ?? '—' }}</td>

                            <td>
                                <form method="POST" action="{{ route('admin.circles.members.update', [$circle, $membership]) }}"
                                      class="d-flex gap-2 align-items-center">
                                    @csrf
                                    @method('PUT')

                                    <input type="hidden" name="peer_name" value="{{ $peerNameFilter }}">
                                    <input type="hidden" name="peer_email" value="{{ $peerEmailFilter }}">
                                    <input type="hidden" name="page" value="{{ $peerCurrentPage }}">

                                    <select name="role" class="form-select form-select-sm">
                                        @foreach (($roles ?? []) as $role)
                                            <option value="{{ $role }}" @selected(($membership->role ?? null) === $role)>
                                                {{ ucwords(str_replace('_', ' ', $role)) }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <button class="btn btn-sm btn-outline-primary">Update</button>
                                </form>

                                @if (!empty($membership->roleRef))
                                    <div class="small text-muted mt-1">
                                        {{ $membership->roleRef->name ?? '—' }} ({{ $membership->roleRef->key ?? '—' }})
                                    </div>
                                @endif

                                @if (!empty($membership->role_id))
                                    <div class="small text-muted">Role ID: {{ $membership->role_id }}</div>
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-light text-dark text-uppercase">{{ $membership->status ?? 'pending' }}</span>
                            </td>

                            <td>{{ optional($membership->joined_at ?? $membership->created_at ?? null)->format('Y-m-d') ?? '—' }}</td>

                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.circles.members.destroy', [$circle, $membership]) }}"
                                      onsubmit="return confirm('Remove this peer from the circle?');">
                                    @csrf
                                    @method('DELETE')

                                    <input type="hidden" name="peer_name" value="{{ $peerNameFilter }}">
                                    <input type="hidden" name="peer_email" value="{{ $peerEmailFilter }}">
                                    <input type="hidden" name="page" value="{{ $peerCurrentPage }}">

                                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No peers assigned yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($peerMembers instanceof \Illuminate\Contracts\Pagination\Paginator || $peerMembers instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <div class="mt-3">
                {{ $peerMembers->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const CIRCLE_ID = @json($circle->id ?? null);

        if (window.$ && $('#peer_select').length && $.fn.select2) {
            $('#peer_select').select2({
                width: '100%',
                placeholder: 'Select peer',
                allowClear: true,
                ajax: {
                    url: `/admin/circles/${CIRCLE_ID}/peer-options`,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results || []
                        };
                    },
                    cache: true
                }
            });
        }
    });
</script>
@endpush
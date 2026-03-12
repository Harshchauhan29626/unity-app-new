@extends('admin.layouts.app')

@section('title', $circle->name . ' Circle')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0">{{ $circle->name }}</h5>
        <small class="text-muted">Circle details and peers</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.circles.edit', $circle) }}" class="btn btn-outline-primary btn-sm">Edit Circle</a>
        <form action="{{ route('admin.circles.destroy', $circle) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this circle? This is a soft delete and can be restored by admin.');">
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

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Circle Overview</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge badge-soft-secondary text-uppercase">{{ $circle->status ?? 'pending' }}</span>
                    <span class="badge bg-light text-dark text-uppercase">{{ $circle->type ?? '—' }}</span>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Slug</dt>
                    <dd class="col-sm-8">{{ $circle->slug ?? '—' }}</dd>

                    <dt class="col-sm-4">City</dt>
                    <dd class="col-sm-8">{{ $circle->city?->name ?? '—' }}</dd>

                    <dt class="col-sm-4">Country</dt>
                    <dd class="col-sm-8">{{ $circle->city?->country ?? '—' }}</dd>

                    <dt class="col-sm-4">Founder</dt>
                    <dd class="col-sm-8">{{ $circle->founder?->display_name ?? '—' }}</dd>

                    <dt class="col-sm-4">Created</dt>
                    <dd class="col-sm-8">{{ optional($circle->created_at)->format('Y-m-d H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">Narrative & Tags</div>
            <div class="card-body">
                <p class="text-muted mb-2"><strong>Description:</strong> {{ $circle->description ?: '—' }}</p>
                <p class="text-muted mb-2"><strong>Purpose:</strong> {{ $circle->purpose ?: '—' }}</p>
                <p class="text-muted mb-2"><strong>Announcement:</strong> {{ $circle->announcement ?: '—' }}</p>
                <p class="text-muted mb-0"><strong>Industry Tags:</strong> {{ $circle->industry_tags ? implode(', ', $circle->industry_tags) : '—' }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold">Circle Settings</div>
    <div class="card-body">
        @php
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

                $name = $user->name
                    ?? $user->display_name
                    ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

                $name = trim((string) $name);
                $email = trim((string) ($user->email ?? ''));

                if ($name !== '' && $email !== '') {
                    return $name . ' (' . $email . ')';
                }

                return $name !== '' ? $name : ($email !== '' ? $email : null);
            };

            $meetingMode = $circle->meeting_mode ? ucfirst(strtolower($circle->meeting_mode)) : null;
            $meetingFrequency = $circle->meeting_frequency ? ucfirst(strtolower($circle->meeting_frequency)) : null;
            $launchDate = optional($circle->launch_date)->format('d M Y');
            $meetingRepeat = is_array($circle->meeting_repeat) ? $circle->meeting_repeat : null;
        @endphp

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
                {!! $displayValue($launchDate) !!}
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Circle Stage</div>
                {!! $displayValue($circleStage) !!}
            </div>

            <div class="col-md-4">
                <div class="small text-muted">Director</div>
                {!! $displayValue($formatUser($circle->director)) !!}
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Industry Director</div>
                {!! $displayValue($formatUser($circle->industryDirector)) !!}
            </div>
            <div class="col-md-4">
                <div class="small text-muted">DED</div>
                {!! $displayValue($formatUser($circle->ded)) !!}
            </div>

            <div class="col-md-8">
                <div class="small text-muted">Meeting Repeat</div>
                @if ($meetingRepeat)
                    <div class="small bg-light border rounded p-2">
                        @foreach ($meetingRepeat as $key => $value)
                            <div><span class="text-muted">{{ ucwords(str_replace('_', ' ', (string) $key)) }}:</span> <span class="fw-semibold">{{ is_scalar($value) ? $value : json_encode($value) }}</span></div>
                        @endforeach
                    </div>
                @else
                    <div class="text-muted">—</div>
                @endif
            </div>

            <div class="col-md-4">
                <div class="small text-muted">Cover</div>
                @if ($circle->cover_file_id)
                    <div class="d-flex flex-column gap-2">
                        <img src="{{ url('/api/v1/files/' . $circle->cover_file_id) }}" alt="Circle Cover" class="rounded border" style="max-height: 120px; width: auto; object-fit: cover;">
                        <div>
                            <a href="{{ url('/api/v1/files/' . $circle->cover_file_id) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
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
                <div class="fw-semibold text-dark">{{ $rankingData['total_members'] }}</div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Rank</div>
                <div class="fw-semibold text-dark">{{ $rankingData['rank'] }}</div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Circle Title</div>
                <div class="fw-semibold text-dark">{{ $rankingData['title'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold">Meeting Schedule</div>
    <div class="card-body">
        @if (empty($meetingRows))
            <div class="text-muted">—</div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($meetingRows as $meetingRow)
                    <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                        <span><strong>{{ $meetingRow['label'] }}:</strong> {{ $meetingRow['value'] }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="small text-muted mt-2">Timezone: {{ $timezone ?: 'Asia/Kolkata' }}</div>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold">Peers</div>
    <div class="card-body">
        <form action="{{ route('admin.circles.members.store', $circle) }}" method="POST" class="row g-2 align-items-end mb-4">
            @csrf
            <div class="col-md-6">
                <label class="form-label">Select Peer</label>
                <select id="peer_select" name="user_id" class="form-select" required></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}">{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100">Add Peer</button>
            </div>
        </form>

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
                </thead>
                <tbody>
                    @forelse ($circle->members as $membership)
                        @php
                            $member = $membership->user;
                            $memberName = trim((string) ($member?->first_name ?? '').' '.(string) ($member?->last_name ?? ''));
                            if ($memberName === '') {
                                $memberName = trim((string) ($member?->display_name ?? ''));
                            }
                            $memberCompany = trim((string) ($member?->company_name ?? ''));
                            if ($memberCompany === '') {
                                $memberCompany = trim((string) ($member?->business_name ?? ''));
                            }
                            $memberCity = trim((string) ($member?->city ?? ''));
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $memberName !== '' ? $memberName : '—' }}</div>
                                <div class="text-muted small">{{ $memberCompany !== '' ? $memberCompany : 'No Company' }}</div>
                                <div class="text-muted small">{{ $memberCity !== '' ? $memberCity : 'No City' }}</div>
                            </td>
                            <td>{{ $member?->email ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.circles.members.update', [$circle, $membership]) }}" class="d-flex gap-2 align-items-center">
                                    @csrf
                                    @method('PUT')
                                    <select name="role" class="form-select form-select-sm">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role }}" @selected($membership->role === $role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary">Update</button>
                                </form>
                                @if ($membership->roleRef)
                                    <div class="small text-muted mt-1">
                                        {{ $membership->roleRef->name }} ({{ $membership->roleRef->key }})
                                    </div>
                                @endif
                                @if ($membership->role_id)
                                    <div class="small text-muted">Role ID: {{ $membership->role_id }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark text-uppercase">{{ $membership->status ?? 'pending' }}</span>
                            </td>
                            <td>{{ optional($membership->joined_at ?? $membership->created_at)->format('Y-m-d') ?? '—' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.circles.members.destroy', [$circle, $membership]) }}" onsubmit="return confirm('Remove this peer from the circle?');">
                                    @csrf
                                    @method('DELETE')
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
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const CIRCLE_ID = @json($circle->id);

        if (window.$ && $('#peer_select').length) {
            $('#peer_select').select2({
                width: '100%',
                placeholder: 'Select peer',
                allowClear: true,
                ajax: {
                    url: `/admin/circles/${CIRCLE_ID}/peer-options`,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: data => ({ results: data.results ?? [] }),
                    cache: true,
                },
            });
        }
    });
</script>
@endpush

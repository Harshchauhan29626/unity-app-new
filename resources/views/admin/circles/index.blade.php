@extends('admin.layouts.app')

@section('title', 'Circles')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0">Circles</h5>
        <small class="text-muted">Community circles overview</small>
    </div>
    <a href="{{ route('admin.circles.create') }}" class="btn btn-primary btn-sm">Create Circle</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="GET" action="{{ route('admin.circles.index') }}">
    <div class="card p-3">
        <div class="table-responsive" style="overflow-x: auto;">
            <table class="table align-middle" style="white-space: nowrap;">
                <thead>
                    <tr>
                        <th>Circle</th>
                        <th>Founder</th>
                        <th>City</th>
                        <th>Country</th>
                        <th>Type</th>
                        <th>Industry Tags</th>
                        <th>Meeting Mode</th>
                        <th>Meeting Frequency</th>
                        <th>Launch Date</th>
                        <th>Circle Stage</th>
                        <th>Cover Image</th>
                        <th>Director</th>
                        <th>Industry Director</th>
                        <th>DED</th>
                        <th>Peers</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                    <tr class="bg-light align-middle">
                        <th>
                            <select name="circle_name" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($circleNames as $circleName)
                                    <option value="{{ $circleName }}" @selected($filters['circle_name'] === $circleName)>{{ $circleName }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <input type="text" name="founder" class="form-control form-control-sm" value="{{ $filters['founder'] }}" placeholder="Founder">
                        </th>
                        <th>
                            <select name="city_id" class="form-select form-select-sm">
                                <option value="any" @selected(($filters['city_id'] ?? 'any') === 'any')>Any</option>
                                @foreach ($cities as $c)
                                    <option value="{{ $c->id }}" @selected(($filters['city_id'] ?? '') === (string) $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <select name="country" class="form-select form-select-sm">
                                <option value="">Any</option>
                                @foreach ($countryOptions as $country)
                                    <option value="{{ $country }}" @selected($filters['country'] === $country)>{{ $country }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <select name="type" class="form-select form-select-sm">
                                <option value="">Any</option>
                                @foreach ($typeOptions as $type)
                                    <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <input type="text" name="industry_tags" class="form-control form-control-sm" value="{{ $filters['industry_tags'] }}" placeholder="Industry Tags">
                        </th>
                        <th>
                            <select name="meeting_mode" class="form-select form-select-sm">
                                <option value="">Any</option>
                                @foreach ($meetingModeOptions as $meetingMode)
                                    <option value="{{ $meetingMode }}" @selected($filters['meeting_mode'] === $meetingMode)>{{ ucfirst($meetingMode) }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <select name="meeting_frequency" class="form-select form-select-sm">
                                <option value="">Any</option>
                                @foreach ($meetingFrequencyOptions as $meetingFrequency)
                                    <option value="{{ $meetingFrequency }}" @selected($filters['meeting_frequency'] === $meetingFrequency)>{{ ucfirst($meetingFrequency) }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <input type="date" name="launch_date" class="form-control form-control-sm" value="{{ $filters['launch_date'] }}">
                        </th>
                        <th>
                            <select name="circle_stage" class="form-select form-select-sm">
                                <option value="">Any</option>
                                @foreach ($circleStageOptions as $circleStage)
                                    <option value="{{ $circleStage }}" @selected($filters['circle_stage'] === $circleStage)>{{ $circleStage }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th class="text-muted small">—</th>
                        <th>
                            <input type="text" name="director" class="form-control form-control-sm" value="{{ $filters['director'] }}" placeholder="Director">
                        </th>
                        <th>
                            <input type="text" name="industry_director" class="form-control form-control-sm" value="{{ $filters['industry_director'] }}" placeholder="Industry Director">
                        </th>
                        <th>
                            <input type="text" name="ded" class="form-control form-control-sm" value="{{ $filters['ded'] }}" placeholder="DED">
                        </th>
                        <th>
                            <input type="text" class="form-control form-control-sm" placeholder="—" disabled>
                        </th>
                        <th>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Any</option>
                                @foreach ($statusOptions as $status)
                                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <input type="text" class="form-control form-control-sm" placeholder="—" disabled>
                        </th>
                        <th class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.circles.index') }}">Reset</a>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($circles as $circle)
                        <tr>
                            <td class="fw-semibold">{{ $circle->name ?? '—' }}</td>
                            <td>{{ $circle->founder?->display_name ?? '—' }}</td>
                            <td>{{ $circle->city_name ?? '—' }}</td>
                            <td>{{ $circle->country ?? ($circle->city?->country ?? '—') }}</td>
                            <td>
                                <span class="badge bg-light text-dark text-uppercase">
                                    {{ !empty($circle->type) ? ucfirst(strtolower($circle->type)) : '—' }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $industryTags = $circle->industry_tags;
                                    if (is_array($industryTags)) {
                                        $industryTagsText = implode(', ', array_filter($industryTags));
                                    } else {
                                        $industryTagsText = trim((string) $industryTags);
                                    }
                                @endphp
                                {{ $industryTagsText !== '' ? $industryTagsText : '—' }}
                            </td>
                            <td>{{ !empty($circle->meeting_mode) ? ucfirst(strtolower($circle->meeting_mode)) : '—' }}</td>
                            <td>{{ !empty($circle->meeting_frequency) ? ucfirst(strtolower($circle->meeting_frequency)) : '—' }}</td>
                            <td>
                                @if (!empty($circle->launch_date))
                                    {{ \Carbon\Carbon::parse($circle->launch_date)->format('d-m-Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $circle->circle_stage ?: '—' }}</td>
                            <td>
                                @if ($circle->cover_image_url)
                                    <a href="{{ $circle->cover_image_url }}" target="_blank">
                                        <img
                                            src="{{ $circle->cover_image_url }}"
                                            alt="Cover"
                                            style="width:48px;height:48px;object-fit:cover;border-radius:8px;"
                                        />
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $circle->director?->display_name ?? '—' }}</td>
                            <td>{{ $circle->industryDirector?->display_name ?? '—' }}</td>
                            <td>{{ $circle->ded?->display_name ?? '—' }}</td>
                            <td>{{ $circle->members_count ?? 0 }}</td>
                            <td>
                                <span class="badge badge-soft-secondary text-uppercase">
                                    {{ !empty($circle->status) ? ucfirst(strtolower($circle->status)) : '—' }}
                                </span>
                            </td>
                            <td>{{ optional($circle->created_at)->format('d-m-Y') ?? '—' }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-sm btn-light" href="{{ route('admin.circles.show', $circle) }}">View</a>
                                    <form action="{{ route('admin.circles.destroy', $circle) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this circle? This is a soft delete and can be restored by admin.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="19" class="text-center text-muted py-4">No circles found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-2">
            {{ $circles->links() }}
        </div>
    </div>
</form>
@endsection

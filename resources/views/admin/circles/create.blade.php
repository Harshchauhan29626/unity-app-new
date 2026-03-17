@extends('admin.layouts.app')

@section('title', 'Create Circle')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0">Create Circle</h5>
        <small class="text-muted">Add a new community circle</small>
    </div>
    <a href="{{ route('admin.circles.index') }}" class="btn btn-outline-secondary btn-sm">Back to Circles</a>
</div>

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
    $defaultFounder = $defaultFounder ?? null;
    $allUsers = $allUsers ?? collect();
    $types = $types ?? [];
    $statuses = $statuses ?? [];
    $meetingModes = $meetingModes ?? [];
    $meetingFrequencies = $meetingFrequencies ?? [];
    $circleStages = $circleStages ?? [];
    $countries = $countries ?? ['India'];
    $selectedCountry = $selectedCountry ?? 'India';
    $cities = $cities ?? collect();

    $industryTagsValue = old('industry_tags');
    if (is_array($industryTagsValue)) {
        $industryTagsValue = implode(', ', $industryTagsValue);
    }

    $founderId = old('founder_user_id', $defaultFounder?->id);
@endphp

<form action="{{ route('admin.circles.store') }}" method="POST">
    @csrf

    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Circle Details</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Circle Founder</label>
                        <select name="founder_user_id" class="form-select" required>
                            <option value="">Select a member</option>
                            @foreach ($allUsers as $user)
                                @php
                                    $label = trim((string) ($user->display_name ?? ''));
                                    if ($label === '') {
                                        $label = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));
                                    }
                                    if ($label === '') {
                                        $label = (string) ($user->email ?? 'User');
                                    }
                                @endphp
                                <option value="{{ $user->id }}" @selected((string) $founderId === (string) $user->id)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="" disabled @selected(old('type') === null)>Select type</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Pending (default)</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Purpose</label>
                        <textarea name="purpose" class="form-control" rows="2">{{ old('purpose') }}</textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Announcement</label>
                        <textarea name="announcement" class="form-control" rows="2">{{ old('announcement') }}</textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Industry Tags</label>
                        <input type="text" name="industry_tags" class="form-control" value="{{ $industryTagsValue }}" placeholder="e.g. Finance, SaaS, Retail">
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block">Categories</label>
                        <div class="form-text mb-2">Select one or more categories</div>
                        <div class="row g-2">
                            @forelse(($categories ?? collect()) as $category)
                                <div class="col-sm-6 col-lg-4">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="categories[]"
                                            value="{{ $category->id }}"
                                            id="category_{{ $category->id }}"
                                            @checked(in_array($category->id, $selectedCategoryIds ?? []))
                                        >
                                        <label class="form-check-label" for="category_{{ $category->id }}">
                                            {{ $category->category_name }}
                                        </label>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="text-muted small">No categories available.</div>
                                </div>
                            @endforelse
                        </div>
                        @error('categories')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                        @error('categories.*')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Circle Settings</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Meeting Mode</label>
                        <select name="meeting_mode" class="form-select">
                            <option value="">Select mode</option>
                            @foreach ($meetingModes as $mode)
                                <option value="{{ $mode }}" @selected(old('meeting_mode') === $mode)>{{ ucfirst($mode) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Meeting Frequency</label>
                        <select name="meeting_frequency" class="form-select">
                            <option value="">Select frequency</option>
                            @foreach ($meetingFrequencies as $frequency)
                                <option value="{{ $frequency }}" @selected(old('meeting_frequency') === $frequency)>{{ ucfirst($frequency) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Launch Date</label>
                        <input type="date" name="launch_date" class="form-control" value="{{ old('launch_date') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Circle Stage</label>
                        <select name="circle_stage" class="form-select">
                            <option value="">Select stage</option>
                            @foreach ($circleStages as $stage)
                                <option value="{{ $stage }}" @selected(old('circle_stage') === $stage)>{{ $stage }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Director</label>
                        <select name="director_user_id" class="form-select">
                            <option value="">Select director</option>
                            @foreach ($allUsers as $user)
                                @php
                                    $label = trim((string) ($user->display_name ?? ''));
                                    if ($label === '') {
                                        $label = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));
                                    }
                                    if ($label === '') {
                                        $label = (string) ($user->email ?? 'User');
                                    }
                                @endphp
                                <option value="{{ $user->id }}" @selected((string) old('director_user_id') === (string) $user->id)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Industry Director</label>
                        <select name="industry_director_user_id" class="form-select">
                            <option value="">Select industry director</option>
                            @foreach ($allUsers as $user)
                                @php
                                    $label = trim((string) ($user->display_name ?? ''));
                                    if ($label === '') {
                                        $label = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));
                                    }
                                    if ($label === '') {
                                        $label = (string) ($user->email ?? 'User');
                                    }
                                @endphp
                                <option value="{{ $user->id }}" @selected((string) old('industry_director_user_id') === (string) $user->id)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">DED</label>
                        <select name="ded_user_id" class="form-select">
                            <option value="">Select DED</option>
                            @foreach ($allUsers as $user)
                                @php
                                    $label = trim((string) ($user->display_name ?? ''));
                                    if ($label === '') {
                                        $label = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));
                                    }
                                    if ($label === '') {
                                        $label = (string) ($user->email ?? 'User');
                                    }
                                @endphp
                                <option value="{{ $user->id }}" @selected((string) old('ded_user_id') === (string) $user->id)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Location</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Country</label>
                        <select name="country" id="countrySelect" class="form-select" required>
                            @foreach ($countries as $country)
                                <option value="{{ $country }}" @selected(old('country', $selectedCountry) === $country)>{{ $country }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">City</label>
                        <select name="city_id" class="form-select" required>
                            <option value="" disabled @selected(old('city_id') === null)>Select city</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->id }}" @selected((string) old('city_id') === (string) $city->id)>
                                    {{ $city->name }}{{ !empty($city->state) ? ', ' . $city->state : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Derived Country</label>
                        <input type="text" class="form-control" value="{{ old('country', $selectedCountry) }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('admin.circles.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Circle</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.getElementById('countrySelect')?.addEventListener('change', (event) => {
        const url = new URL(window.location.href);
        url.searchParams.set('country', event.target.value);
        window.location.href = url.toString();
    });
</script>
@endpush
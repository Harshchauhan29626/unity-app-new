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
    $industryTagsValue = old('industry_tags');
    if (is_array($industryTagsValue)) {
        $industryTagsValue = implode(', ', $industryTagsValue);
    }
    $founderId = old('founder_user_id', $defaultFounder?->id);
    $oldMeetings = old('calendar_meetings', []);
    $calendarMeetings = is_array($oldMeetings) && $oldMeetings !== []
        ? array_values($oldMeetings)
        : [[
            'frequency' => '',
            'default_meet_day' => '',
            'default_meet_time' => '',
            'monthly_rule' => '',
        ]];
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
                                <option value="{{ $user->id }}" @selected((string) $founderId === (string) $user->id)>{{ \App\Support\UserOptionLabel::make($user) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Defaults to the logged-in admin user.</div>
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
                        <label class="form-label">Circle Package</label>
                        <select name="circle_package" class="form-select">
                            <option value="">Select package</option>
                            @foreach ($circlePackages as $package)
                                @php($packageValue = $package['addon_code'] ?: $package['addon_id'])
                                <option value="{{ $packageValue }}" @selected(old('circle_package') === $packageValue)>
                                    {{ $package['name'] }} ({{ $package['addon_code'] }}) - {{ $package['amount'] }} {{ $package['currency_code'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Only active Zoho addons with code starting with <code>Package-</code> are listed.</div>
                    </div>
                    <div class="col-md-12">
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
                    <div class="col-md-12">
                        <label class="form-label">Industry Tags</label>
                        <input type="text" name="industry_tags" class="form-control" value="{{ $industryTagsValue }}" placeholder="e.g. Finance, SaaS, Retail">
                        <div class="form-text">Separate tags with commas.</div>
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
                        <label class="form-label">Circle Director</label>
                        <select name="director_user_id" class="form-select">
                            <option value="">Select director</option>
                            @foreach ($allUsers as $user)
                                <option value="{{ $user->id }}" @selected(old('director_user_id') === $user->id)>{{ trim((string) ($user->display_name ?? '')) !== '' ? trim((string) $user->display_name) : (trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? ''))) !== '' ? trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? ''))) : '—') }} — {{ (isset($user->company_name) && trim((string) $user->company_name) !== '') ? trim((string) $user->company_name) : ((isset($user->business_name) && trim((string) $user->business_name) !== '') ? trim((string) $user->business_name) : 'No Company') }} — {{ (isset($user->city) && trim((string) $user->city) !== '') ? trim((string) $user->city) : 'No City' }} — {{ (trim((string) optional($user->circleMembers->first()?->circle)->name) !== '') ? trim((string) optional($user->circleMembers->first()?->circle)->name) : 'No Circle' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Industry Director</label>
                        <select name="industry_director_user_id" class="form-select">
                            <option value="">Select industry director</option>
                            @foreach ($allUsers as $user)
                                <option value="{{ $user->id }}" @selected(old('industry_director_user_id') === $user->id)>{{ trim((string) ($user->display_name ?? '')) !== '' ? trim((string) $user->display_name) : (trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? ''))) !== '' ? trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? ''))) : '—') }} — {{ (isset($user->company_name) && trim((string) $user->company_name) !== '') ? trim((string) $user->company_name) : ((isset($user->business_name) && trim((string) $user->business_name) !== '') ? trim((string) $user->business_name) : 'No Company') }} — {{ (isset($user->city) && trim((string) $user->city) !== '') ? trim((string) $user->city) : 'No City' }} — {{ (trim((string) optional($user->circleMembers->first()?->circle)->name) !== '') ? trim((string) optional($user->circleMembers->first()?->circle)->name) : 'No Circle' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">DED</label>
                        <select name="ded_user_id" class="form-select">
                            <option value="">Select DED</option>
                            @foreach ($allUsers as $user)
                                <option value="{{ $user->id }}" @selected(old('ded_user_id') === $user->id)>{{ trim((string) ($user->display_name ?? '')) !== '' ? trim((string) $user->display_name) : (trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? ''))) !== '' ? trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? ''))) : '—') }} — {{ (isset($user->company_name) && trim((string) $user->company_name) !== '') ? trim((string) $user->company_name) : ((isset($user->business_name) && trim((string) $user->business_name) !== '') ? trim((string) $user->business_name) : 'No Company') }} — {{ (isset($user->city) && trim((string) $user->city) !== '') ? trim((string) $user->city) : 'No City' }} — {{ (trim((string) optional($user->circleMembers->first()?->circle)->name) !== '') ? trim((string) optional($user->circleMembers->first()?->circle)->name) : 'No Circle' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Cover Image</label>
                        <input type="hidden" name="cover_file_id" id="coverFileId" value="{{ old('cover_file_id') }}">
                        <input type="file" class="form-control" id="coverFileInput" accept="image/*">
                        <div class="form-text" id="coverUploadStatus">Upload up to 10MB.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Meeting Schedule</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addMeetingBtn">+ Add Meeting</button>
                </div>
                <div class="card-body">
                    <div id="meetingRows" class="d-flex flex-column gap-3">
                        @foreach ($calendarMeetings as $index => $meeting)
                            @php
                                $rowFrequency = strtolower((string) data_get($meeting, 'frequency', ''));
                                $rowDay = strtolower((string) data_get($meeting, 'default_meet_day', ''));
                                $rowTime = (string) data_get($meeting, 'default_meet_time', '');
                                $rowRule = strtolower((string) data_get($meeting, 'monthly_rule', ''));
                            @endphp
                            <div class="border rounded p-3 meeting-row" data-index="{{ $index }}">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Frequency</label>
                                        <select class="form-select js-meeting-frequency" name="calendar_meetings[{{ $index }}][frequency]">
                                            <option value="">Select frequency</option>
                                            <option value="weekly" @selected($rowFrequency === 'weekly')>Weekly</option>
                                            <option value="monthly" @selected($rowFrequency === 'monthly')>Monthly</option>
                                            <option value="quarterly" @selected($rowFrequency === 'quarterly')>Quarterly</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 js-meeting-time-wrap">
                                        <label class="form-label">Default Meet Time</label>
                                        <input type="time" class="form-control js-meeting-time" name="calendar_meetings[{{ $index }}][default_meet_time]" value="{{ $rowTime }}">
                                    </div>
                                    <div class="col-md-3 js-meeting-day-wrap">
                                        <label class="form-label">Day of Week</label>
                                        <select class="form-select js-meeting-day" name="calendar_meetings[{{ $index }}][default_meet_day]">
                                            <option value="">Select day</option>
                                            @foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                                                <option value="{{ $day }}" @selected($rowDay === $day)>{{ ucfirst($day) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 js-meeting-rule-wrap">
                                        <label class="form-label">Week Rule</label>
                                        <select class="form-select js-meeting-rule" name="calendar_meetings[{{ $index }}][monthly_rule]">
                                            <option value="">Select</option>
                                            @foreach (['first','second','third','fourth','last'] as $rule)
                                                <option value="{{ $rule }}" @selected($rowRule === $rule)>{{ ucfirst($rule) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger js-remove-meeting" @if($index===0) disabled @endif>Remove</button>
                                    </div>
                                    <div class="col-12">
                                        <div class="small text-muted">Preview: <span class="js-meeting-preview">—</span></div>
                                        <div class="small text-muted">Weekly: day + time. Monthly/Quarterly: week rule + day + time.</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="calendar_timezone" value="Asia/Kolkata">
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
                                <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>
                                    {{ $city->name }}{{ $city->state ? ', ' . $city->state : '' }}
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

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const uploadUrl = '{{ route('admin.files.upload') }}';

    document.getElementById('coverFileInput')?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const statusEl = document.getElementById('coverUploadStatus');
        statusEl.textContent = 'Uploading...';

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
            });

            if (!response.ok) {
                statusEl.textContent = 'Upload failed. Please try again.';
                return;
            }

            const payload = await response.json();
            const fileId = payload?.data?.id ?? payload?.data?.[0]?.id;
            if (!fileId) {
                statusEl.textContent = 'Upload failed. Missing file id.';
                return;
            }

            document.getElementById('coverFileId').value = fileId;
            statusEl.textContent = 'Upload successful.';
        } catch (error) {
            statusEl.textContent = 'Upload failed. Please try again.';
        }
    });


    const meetingRows = document.getElementById('meetingRows');
    const addMeetingBtn = document.getElementById('addMeetingBtn');

    const title = (value) => value ? value.charAt(0).toUpperCase() + value.slice(1) : '';

    const updateMeetingRowState = (row) => {
        const frequency = row.querySelector('.js-meeting-frequency')?.value || '';
        const day = row.querySelector('.js-meeting-day')?.value || '';
        const time = row.querySelector('.js-meeting-time')?.value || '';
        const rule = row.querySelector('.js-meeting-rule')?.value || '';

        row.querySelector('.js-meeting-time-wrap')?.classList.toggle('d-none', !frequency);
        row.querySelector('.js-meeting-day-wrap')?.classList.toggle('d-none', !frequency);
        row.querySelector('.js-meeting-rule-wrap')?.classList.toggle('d-none', !['monthly', 'quarterly'].includes(frequency));

        let preview = '—';
        if (frequency === 'weekly' && day && time) {
            preview = `Every ${title(day)} at ${time}`;
        } else if ((frequency === 'monthly' || frequency === 'quarterly') && rule && day && time) {
            preview = `${title(rule)} ${title(day)} at ${time}${frequency === 'quarterly' ? ' (Quarterly)' : ''}`;
        }

        const previewEl = row.querySelector('.js-meeting-preview');
        if (previewEl) {
            previewEl.textContent = preview;
        }
    };

    const bindMeetingRow = (row) => {
        row.querySelectorAll('.js-meeting-frequency, .js-meeting-day, .js-meeting-time, .js-meeting-rule')
            .forEach((input) => input.addEventListener('change', () => updateMeetingRowState(row)));

        row.querySelector('.js-meeting-time')?.addEventListener('input', () => updateMeetingRowState(row));
        row.querySelector('.js-remove-meeting')?.addEventListener('click', () => {
            if (meetingRows.querySelectorAll('.meeting-row').length > 1) {
                row.remove();
                reindexMeetingRows();
            }
        });

        updateMeetingRowState(row);
    };

    const reindexMeetingRows = () => {
        const rows = meetingRows.querySelectorAll('.meeting-row');
        rows.forEach((row, index) => {
            row.dataset.index = String(index);
            row.querySelectorAll('select, input').forEach((el) => {
                const name = el.getAttribute('name');
                if (!name) return;
                el.setAttribute('name', name.replace(/calendar_meetings\[\d+\]/, `calendar_meetings[${index}]`));
            });

            const removeBtn = row.querySelector('.js-remove-meeting');
            if (removeBtn) {
                removeBtn.disabled = index === 0;
            }
        });
    };

    const createMeetingRow = () => {
        const index = meetingRows.querySelectorAll('.meeting-row').length;
        const template = meetingRows.querySelector('.meeting-row');
        if (!template) return;

        const clone = template.cloneNode(true);
        clone.dataset.index = String(index);
        clone.querySelectorAll('select, input').forEach((el) => {
            const name = el.getAttribute('name');
            if (!name) return;
            el.setAttribute('name', name.replace(/calendar_meetings\[\d+\]/, `calendar_meetings[${index}]`));
            if (el.tagName === 'SELECT') el.value = '';
            if (el.tagName === 'INPUT' && el.type !== 'hidden') el.value = '';
        });
        clone.querySelector('.js-meeting-preview').textContent = '—';
        meetingRows.appendChild(clone);
        bindMeetingRow(clone);
        reindexMeetingRows();
    };

    meetingRows?.querySelectorAll('.meeting-row').forEach((row) => bindMeetingRow(row));
    addMeetingBtn?.addEventListener('click', createMeetingRow);



</script>
@endpush

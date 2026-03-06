@extends('admin.layouts.app')

@section('title', 'Edit Circle')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0">Edit Circle</h5>
        <small class="text-muted">Update circle details</small>
    </div>
    <a href="{{ route('admin.circles.show', $circle) }}" class="btn btn-outline-secondary btn-sm">Back to Details</a>
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
    $industryTagsValue = old('industry_tags', $circle->industry_tags ? implode(', ', $circle->industry_tags) : '');
    if (is_array($industryTagsValue)) {
        $industryTagsValue = implode(', ', $industryTagsValue);
    }
    $founderId = old('founder_user_id', $defaultFounder?->id);
    $calendar = is_array($circle->calendar) ? $circle->calendar : [];
    $meetingScheduleFrequency = old('meeting_schedule_frequency');
    $meetingScheduleTimes = old('meeting_schedule_default_meet_time');
    $meetingScheduleDays = old('meeting_schedule_day_of_week');

    $calendarMeetings = [];

    if (is_array($meetingScheduleFrequency) || is_array($meetingScheduleTimes) || is_array($meetingScheduleDays)) {
        $max = max(count((array) $meetingScheduleFrequency), count((array) $meetingScheduleTimes), count((array) $meetingScheduleDays));
        for ($i = 0; $i < $max; $i++) {
            $calendarMeetings[] = [
                'frequency' => (string) (($meetingScheduleFrequency[$i] ?? '') ?: ''),
                'default_meet_time' => (string) (($meetingScheduleTimes[$i] ?? '') ?: ''),
                'day_of_week' => (string) (($meetingScheduleDays[$i] ?? '') ?: ''),
            ];
        }
    } else {
        $calendarMeetings = is_array(data_get($calendar, 'meeting_schedule')) && data_get($calendar, 'meeting_schedule') !== []
            ? array_values(data_get($calendar, 'meeting_schedule'))
            : [[
                'frequency' => '',
                'default_meet_time' => '',
                'day_of_week' => '',
            ]];
    }
@endphp

<form action="{{ route('admin.circles.update', $circle) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Circle Details</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $circle->name) }}" required>
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
                            <option value="" disabled>Select type</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('type', $circle->type) === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $circle->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $circle->description) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Purpose</label>
                        <textarea name="purpose" class="form-control" rows="2">{{ old('purpose', $circle->purpose) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Announcement</label>
                        <textarea name="announcement" class="form-control" rows="2">{{ old('announcement', $circle->announcement) }}</textarea>
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
                                <option value="{{ $mode }}" @selected(old('meeting_mode', $circle->meeting_mode) === $mode)>{{ ucfirst($mode) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Meeting Frequency</label>
                        <select name="meeting_frequency" class="form-select">
                            <option value="">Select frequency</option>
                            @foreach ($meetingFrequencies as $frequency)
                                <option value="{{ $frequency }}" @selected(old('meeting_frequency', $circle->meeting_frequency) === $frequency)>{{ ucfirst($frequency) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Launch Date</label>
                        <input type="date" name="launch_date" class="form-control" value="{{ old('launch_date', $circle->launch_date) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Circle Director</label>
                        <select name="director_user_id" class="form-select">
                            <option value="">Select director</option>
                            @foreach ($allUsers as $user)
                                <option value="{{ $user->id }}" @selected(old('director_user_id', $circle->director_user_id) === $user->id)>{{ $user->adminDisplayInlineLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Industry Director</label>
                        <select name="industry_director_user_id" class="form-select">
                            <option value="">Select industry director</option>
                            @foreach ($allUsers as $user)
                                <option value="{{ $user->id }}" @selected(old('industry_director_user_id', $circle->industry_director_user_id) === $user->id)>{{ $user->adminDisplayInlineLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">DED</label>
                        <select name="ded_user_id" class="form-select">
                            <option value="">Select DED</option>
                            @foreach ($allUsers as $user)
                                <option value="{{ $user->id }}" @selected(old('ded_user_id', $circle->ded_user_id) === $user->id)>{{ $user->adminDisplayInlineLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Cover Image</label>
                        <input type="hidden" name="cover_file_id" id="coverFileId" value="{{ old('cover_file_id', $circle->cover_file_id) }}">
                        @if ($circle->cover_file_id)
                            <div id="coverPreviewBlock" class="mb-2 d-flex align-items-center gap-2">
                                <a id="coverPreviewLink" href="{{ url('/api/v1/files/' . $circle->cover_file_id) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                <small class="text-muted">File ID: {{ $circle->cover_file_id }}</small>
                                <img id="coverPreviewImage" src="{{ url('/api/v1/files/' . $circle->cover_file_id) }}" alt="Cover preview" class="rounded border" style="max-height:80px;border-radius:8px;">
                            </div>
                        @else
                            <div id="coverPreviewBlock" class="mb-2 d-none align-items-center gap-2">
                                <a id="coverPreviewLink" href="#" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                <img id="coverPreviewImage" src="#" alt="Cover preview" class="rounded border" style="max-height:80px;border-radius:8px;">
                            </div>
                        @endif
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
                                $rowDay = (string) data_get($meeting, 'day_of_week', '');
                                $rowTime = (string) data_get($meeting, 'default_meet_time', '');
                                                            @endphp
                            <div class="border rounded p-3 meeting-row" data-index="{{ $index }}">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Frequency</label>
                                        <select class="form-select js-meeting-frequency" name="meeting_schedule_frequency[]">
                                            <option value="">Select frequency</option>
                                            <option value="weekly" @selected($rowFrequency === 'weekly')>Weekly</option>
                                            <option value="monthly" @selected($rowFrequency === 'monthly')>Monthly</option>
                                            <option value="quarterly" @selected($rowFrequency === 'quarterly')>Quarterly</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 js-meeting-time-wrap">
                                        <label class="form-label">Default Meet Time</label>
                                        <input type="time" class="form-control js-meeting-time" name="meeting_schedule_default_meet_time[]" value="{{ $rowTime }}">
                                    </div>
                                    <div class="col-md-3 js-meeting-day-wrap">
                                        <label class="form-label">Day of Week</label>
                                        <select class="form-select js-meeting-day" name="meeting_schedule_day_of_week[]">
                                            <option value="">Select day</option>
                                            @foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day)
                                                <option value="{{ $day }}" @selected($rowDay === $day)>{{ $day }}</option>
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
                <div class="card-header fw-semibold">Circle Payment Details</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="circle_payment_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="circlePaymentEnabled" name="circle_payment_enabled" value="1" @checked((bool) old('circle_payment_enabled', $circle->circle_payment_enabled))>
                            <label class="form-check-label" for="circlePaymentEnabled">Enable Circle Payment</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Monthly Price</label>
                        <input type="number" step="0.01" min="0" name="monthly_price" class="form-control js-circle-price" value="{{ old('monthly_price', $circle->monthly_price) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quarterly Price</label>
                        <input type="number" step="0.01" min="0" name="quarterly_price" class="form-control js-circle-price" value="{{ old('quarterly_price', $circle->quarterly_price) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">6 Months Price</label>
                        <input type="number" step="0.01" min="0" name="half_yearly_price" class="form-control js-circle-price" value="{{ old('half_yearly_price', $circle->half_yearly_price) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Yearly Price</label>
                        <input type="number" step="0.01" min="0" name="yearly_price" class="form-control js-circle-price" value="{{ old('yearly_price', $circle->yearly_price) }}">
                    </div>
                    <div class="col-12">
                        <div class="form-text">These prices are used for paid circle joining plans and Zoho addon sync.</div>
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
                            <option value="" disabled>Select city</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->id }}" @selected(old('city_id', $circle->city_id) == $city->id)>
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
            <a href="{{ route('admin.circles.show', $circle) }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
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
            const previewLink = document.getElementById('coverPreviewLink');
            const previewImage = document.getElementById('coverPreviewImage');
            const previewBlock = document.getElementById('coverPreviewBlock');
            if (previewLink) {
                previewLink.href = `/api/v1/files/${fileId}`;
            }
            if (previewImage) {
                previewImage.src = `/api/v1/files/${fileId}`;
            }
            if (previewBlock) {
                previewBlock.classList.remove('d-none');
            }
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
        row.querySelector('.js-meeting-time-wrap')?.classList.toggle('d-none', !frequency);
        row.querySelector('.js-meeting-day-wrap')?.classList.toggle('d-none', !frequency);

        let preview = '—';
        if (frequency && day && time) {
            preview = `${title(day)} at ${time} (${title(frequency)})`;
        }

        const previewEl = row.querySelector('.js-meeting-preview');
        if (previewEl) {
            previewEl.textContent = preview;
        }
    };

    const bindMeetingRow = (row) => {
        row.querySelectorAll('.js-meeting-frequency, .js-meeting-day, .js-meeting-time')
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
                el.setAttribute('name', name);
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
            el.setAttribute('name', name);
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


    const circlePaymentToggle = document.getElementById('circlePaymentEnabled');
    const circlePriceInputs = document.querySelectorAll('.js-circle-price');

    const toggleCirclePaymentPrices = () => {
        const enabled = !!circlePaymentToggle?.checked;
        circlePriceInputs.forEach((input) => {
            input.disabled = !enabled;
        });
    };

    circlePaymentToggle?.addEventListener('change', toggleCirclePaymentPrices);
    toggleCirclePaymentPrices();

</script>
@endpush

@extends('admin.layouts.app')

@section('title', 'Edit Peer')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0">Edit Peer</h5>
        <small class="text-muted">ID: {{ $user->id }}</small>
    </div>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Back to Peers</a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
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

@if ($hasAssignedAdminRole)
    <form id="removeAdminRoleForm" method="POST" action="{{ route('admin.users.roles.remove', $user->id) }}">
        @csrf
    </form>
@endif

<form id="userEditForm" action="{{ route('admin.users.update', $user->id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Basic Info</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Display Name</label>
                        <input type="text" name="display_name" class="form-control" value="{{ old('display_name', $user->display_name) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Designation</label>
                        <input type="text" name="designation" class="form-control" value="{{ old('designation', $user->designation) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Business & Profile</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $user->company_name) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Business Type</label>
                        <input type="text" name="business_type" class="form-control" value="{{ old('business_type', $user->business_type) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Turnover Range</label>
                        <input type="text" name="turnover_range" class="form-control" value="{{ old('turnover_range', $user->turnover_range) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender</label>
                        <input type="text" name="gender" class="form-control" value="{{ old('gender', $user->gender) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" class="form-control" value="{{ old('dob', optional($user->dob)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Experience Years</label>
                        <input type="number" name="experience_years" class="form-control" min="0" max="100" value="{{ old('experience_years', $user->experience_years) }}">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Experience Summary</label>
                        <textarea name="experience_summary" class="form-control" rows="2">{{ old('experience_summary', $user->experience_summary) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Short Bio</label>
                        <textarea name="short_bio" class="form-control" rows="2">{{ old('short_bio', $user->short_bio) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Profile Photo</label>
                        <input type="hidden" name="profile_photo_file_id" id="profilePhotoFileId" value="{{ old('profile_photo_file_id', $user->profile_photo_file_id) }}">
                        <div id="profilePhotoExisting" class="{{ $user->profile_photo_file_id ? '' : 'd-none' }}">
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ $user->profile_photo_file_id ? url('/api/v1/files/' . $user->profile_photo_file_id) : '#' }}" target="_blank" class="btn btn-outline-secondary btn-sm">View Image</a>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-change-target="profilePhoto">Change</button>
                            </div>
                        </div>
                        <div id="profilePhotoUpload" class="{{ $user->profile_photo_file_id ? 'd-none' : '' }}">
                            <input type="file" class="form-control" id="profilePhotoFile" accept="image/*">
                            <div class="form-text" id="profilePhotoStatus">Upload up to 10MB.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cover Photo</label>
                        <input type="hidden" name="cover_photo_file_id" id="coverPhotoFileId" value="{{ old('cover_photo_file_id', $user->cover_photo_file_id) }}">
                        <div id="coverPhotoExisting" class="{{ $user->cover_photo_file_id ? '' : 'd-none' }}">
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ $user->cover_photo_file_id ? url('/api/v1/files/' . $user->cover_photo_file_id) : '#' }}" target="_blank" class="btn btn-outline-secondary btn-sm">View Image</a>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-change-target="coverPhoto">Change</button>
                            </div>
                        </div>
                        <div id="coverPhotoUpload" class="{{ $user->cover_photo_file_id ? 'd-none' : '' }}">
                            <input type="file" class="form-control" id="coverPhotoFile" accept="image/*">
                            <div class="form-text" id="coverPhotoStatus">Upload up to 10MB.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Public Profile Slug</label>
                        <input type="text" name="public_profile_slug" class="form-control" value="{{ old('public_profile_slug', $user->public_profile_slug) }}">
                    </div>
                    <div class="col-12">
                        @php
                            $socialLinksValue = '';
                            if (is_array($user->social_links) && $user->social_links !== []) {
                                if (array_keys($user->social_links) !== range(0, count($user->social_links) - 1)) {
                                    $pairs = [];
                                    foreach ($user->social_links as $k => $v) {
                                        $pairs[] = $k . '=' . $v;
                                    }
                                    $socialLinksValue = implode(', ', $pairs);
                                } else {
                                    $socialLinksValue = implode(', ', $user->social_links);
                                }
                            }
                        @endphp
                        <label class="form-label">Social Links</label>
                        <textarea name="social_links" class="form-control" rows="3" placeholder="Enter social links">{{ old('social_links', $socialLinksValue) }}</textarea>
                        <small class="text-muted">
                            Enter comma separated links, optionally as key=value
                            (e.g. linkedin=https://linkedin.com/..., website=https://example.com)
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Membership & Coins</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Membership Status</label>
                        @php
                            $membershipStatusLabels = [
                                'free_trial_peer' => 'Free Trial Peer',
                                'free_peer' => 'Free Peer',
                                'Only Unity Peer' => 'Only Unity Peer',
                                'Circle Peer' => 'Circle Peer',
                                'Multi Circle Peer' => 'Multi Circle Peer',
                                'Charter Peer' => 'Charter Peer',
                                'Industry Advisor' => 'Industry Advisor',
                                'Charter Investor' => 'Charter Investor',
                                'Circle Founder' => 'Circle Founder',
                                'Circle Director' => 'Circle Director',
                                'Board Advisor' => 'Board Advisor',
                            ];
                        @endphp
                        <select name="membership_status" class="form-select" required>
                            @foreach ($membershipStatuses as $status)
                                <option value="{{ $status }}" @selected(old('membership_status', $user->membership_status) === $status)>
                                    {{ $membershipStatusLabels[$status] ?? $status }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            @php
                                $statusValue = old('status', $user->status ?? 'active');
                            @endphp
                            <option value="active" @selected($statusValue === 'active')>Active</option>
                            <option value="inactive" @selected($statusValue === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Membership Expiry</label>
                        <input type="datetime-local" name="membership_expiry" class="form-control" value="{{ old('membership_expiry', optional($user->membership_ends_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    @if(old('membership_status', $user->membership_status) === 'free_trial_peer')
                        <div class="col-md-4">
                            <label class="form-label">Trial Expiry Date</label>
                            <input type="text" class="form-control" value="{{ old('membership_expiry', optional($user->membership_ends_at)->format('Y-m-d H:i:s')) }}" readonly>
                        </div>
                    @endif
                    <div class="col-md-4">
                        <label class="form-label">Coins Balance</label>
                        <input type="number" name="coins_balance" class="form-control" min="0" value="{{ old('coins_balance', $user->coins_balance) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Influencer Stars</label>
                        <input type="number" name="influencer_stars" class="form-control" min="0" value="{{ old('influencer_stars', $user->influencer_stars) }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" value="1" id="isSponsoredMember" name="is_sponsored_member" @checked(old('is_sponsored_member', $user->is_sponsored_member))>
                            <label class="form-check-label" for="isSponsoredMember">
                                Is Sponsored Member
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Members Introduced Count</label>
                        <input type="number" name="members_introduced_count" class="form-control" min="0" value="{{ old('members_introduced_count', $user->members_introduced_count) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Introduced By (User ID)</label>
                        <input type="text" name="introduced_by" class="form-control" value="{{ old('introduced_by', $user->introduced_by) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Membership & Circle Details</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <small class="text-muted">Manual admin override only. Does not affect payment history. Expired membership will be treated as Free Peer.</small>
                    </div>
                    <div class="col-12"><h6 class="mb-0">Membership Details</h6></div>
                    <div class="col-md-4">
                        <label class="form-label">Membership Start Date</label>
                        <input type="date" name="membership_starts_at" class="form-control" value="{{ old('membership_starts_at', optional($user->membership_starts_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Membership Expiry Date</label>
                        <input type="date" name="membership_ends_at" class="form-control" value="{{ old('membership_ends_at', optional($user->membership_ends_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Membership Plan</label>
                        <select name="zoho_plan_code" class="form-select @error('zoho_plan_code') is-invalid @enderror">
                            <option value="">Select Membership Plan</option>
                            @foreach ($membershipPlanOptions as $plan)
                                <option value="{{ $plan['code'] }}" @selected(old('zoho_plan_code', $user->zoho_plan_code) === $plan['code'])>{{ $plan['label'] }}</option>
                            @endforeach
                        </select>
                        @error('zoho_plan_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Membership plan list is loaded from existing system plans.</div>
                    </div>
                    <div class="col-12"><h6 class="mb-0 mt-2">Circle Membership Details</h6></div>
                    @php
                        $selectedCircleValue = (string) old('active_circle_id', $user->active_circle_id ?? $effectiveCircleId ?? '');
                    @endphp
                    <input type="hidden" name="active_circle_id" value="{{ $selectedCircleValue }}">
                    <div class="col-md-4">
                        <label class="form-label" for="additional_circle_id">Add Another Circle Membership</label>
                        <select name="additional_circle_id" id="additional_circle_id" class="form-select @error('additional_circle_id') is-invalid @enderror">
                            <option value="">-- Optional --</option>
                            @foreach ($circles as $circle)
                                <option value="{{ $circle->id }}" @selected((string) old('additional_circle_id') === (string) $circle->id)>
                                    {{ $circle->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('additional_circle_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Adds or reactivates membership without removing existing circles.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Circle Joined Date</label>
                        <input type="date" name="circle_joined_at" class="form-control" value="{{ old('circle_joined_at', optional($user->circle_joined_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Circle Expiry Date</label>
                        <input type="date" name="circle_expires_at" class="form-control" value="{{ old('circle_expires_at', optional($user->circle_expires_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="add_circle_membership" value="1" class="btn btn-outline-primary w-100">
                            Add Circle
                        </button>
                    </div>

                    @if (! $isJoinedToEffectiveCircle)
                        <div class="col-12">
                            <div class="alert alert-warning mb-0">
                                Peer is not joined to the selected circle. Select a circle and click <strong>Save</strong> to join.
                            </div>
                        </div>
                    @endif

                    <div class="col-12 mt-2">
                        <h6 class="mb-2">Joined Circle Memberships (Multi-circle)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Circle</th>
                                        <th>Addon Code</th>
                                        <th>Addon Name</th>
                                        <th>Joined At</th>
                                        <th>Expires At</th>
                                        <th>Member Status</th>
                                        <th>Payment Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($circleMemberships as $membership)
                                        @php
                                            $latestSubscription = $latestCircleSubscriptions->get((string) $membership->circle_id);
                                        @endphp
                                        <tr>
                                            <td>
                                                @if ($membership->circle?->id)
                                                    <a href="{{ route('admin.circles.show', $membership->circle->id) }}">{{ $membership->circle?->name ?: '—' }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $membership->zoho_addon_code ?: ($latestSubscription->zoho_addon_code ?? '—') }}</td>
                                            <td>{{ $latestSubscription->zoho_addon_name ?? '—' }}</td>
                                            <td>{{ optional($membership->joined_at)->format('Y-m-d') ?: '—' }}</td>
                                            <td>{{ optional($membership->paid_ends_at)->format('Y-m-d') ?: optional($latestSubscription?->expires_at)->format('Y-m-d') ?: '—' }}</td>
                                            <td>{{ $membership->status ?: '—' }}</td>
                                            <td>{{ $membership->payment_status ?: ($latestSubscription->status ?? '—') }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('admin.users.circle-members.destroy', [$user->id, $membership->id]) }}" onsubmit="return confirm('Remove this circle membership for this peer?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-muted text-center">No joined circle memberships.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Location</div>
                <div class="card-body row g-3">
                    <div class="col-md-12">
                        <label class="form-label">City (string fallback)</label>
                        <input type="text" name="city" class="form-control" value="{{ old('city', $user->city) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Additional Details</div>
                <div class="card-body row g-3">
                    @php
                        $jsonFields = [
                            'industry_tags' => $user->industry_tags,
                            'target_regions' => $user->target_regions,
                            'target_business_categories' => $user->target_business_categories,
                            'hobbies_interests' => $user->hobbies_interests,
                            'leadership_roles' => $user->leadership_roles,
                            'special_recognitions' => $user->special_recognitions,
                            'skills' => $user->skills,
                            'interests' => $user->interests,
                        ];

                        $asCsv = function ($value): string {
                            if (is_array($value)) {
                                return implode(', ', $value);
                            }
                            return '';
                        };

                    @endphp
                    @foreach ($jsonFields as $field => $value)
                        <div class="col-md-6">
                            <label class="form-label text-capitalize">{{ str_replace('_', ' ', $field) }}</label>
                            <textarea name="{{ $field }}" class="form-control" rows="3" placeholder="Enter comma separated values (e.g. IT, Finance, Retail)">{{ old($field, $asCsv($value)) }}</textarea>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Read-only Metadata</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">ID</label>
                        <input type="text" class="form-control" value="{{ $user->id }}" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Created At</label>
                        <input type="text" class="form-control" value="{{ optional($user->created_at)->toDateTimeString() }}" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Updated At</label>
                        <input type="text" class="form-control" value="{{ optional($user->updated_at)->toDateTimeString() }}" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last Login</label>
                        <input type="text" class="form-control" value="{{ optional($user->last_login_at)->toDateTimeString() }}" disabled>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">Roles</div>
                <div class="card-body">
                    @php
                        $currentRoleIds = old('role_ids', $userRoleIds);
                    @endphp
                    @if ($hasAssignedAdminRole)
                        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <strong>Currently assigned:</strong>
                                <span>{{ $assignedAdminRoleNames }}</span>
                            </div>
                            <button type="submit"
                                    form="removeAdminRoleForm"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Remove the current admin role from this user?');">
                                Remove Role
                            </button>
                        </div>
                    @endif
                    <div class="row g-3 align-items-center">
                        @foreach ($roles as $role)
                            <div class="col-md-4 d-flex align-items-start">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="role_ids[]"
                                           value="{{ $role->id }}"
                                           id="role-{{ $role->id }}"
                                           @checked(in_array($role->id, $currentRoleIds))
                                           @disabled($hasAssignedAdminRole)>
                                    <label class="form-check-label" for="role-{{ $role->id }}">
                                        <strong>{{ $role->name }}</strong>
                                        <div class="small text-muted">{{ $role->description }}</div>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if ($hasAssignedAdminRole)
                        <div class="form-text text-muted mt-2">
                            Remove the existing admin role to assign a new one.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary" type="button">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
});
</script>
@endpush

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const uploadUrl = '{{ route('admin.files.upload') }}';

        const setupUploader = (prefix) => {
            const fileInput = document.getElementById(`${prefix}File`);
            const hiddenInput = document.getElementById(`${prefix}FileId`);
            const existing = document.getElementById(`${prefix}Existing`);
            const upload = document.getElementById(`${prefix}Upload`);
            const status = document.getElementById(`${prefix}Status`);
            const changeBtn = existing?.querySelector('[data-change-target]');
            const viewLink = existing?.querySelector('a');

            const setStatus = (text, isError = false) => {
                if (!status) return;
                status.textContent = text;
                status.classList.toggle('text-danger', isError);
            };

            changeBtn?.addEventListener('click', () => {
                if (existing) existing.classList.add('d-none');
                if (upload) upload.classList.remove('d-none');
                if (hiddenInput) hiddenInput.value = '';
                if (fileInput) fileInput.value = '';
                setStatus('Select a file to upload.');
            });

            fileInput?.addEventListener('change', async () => {
                const file = fileInput.files?.[0];
                if (!file) return;
                setStatus('Uploading...');

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
                        setStatus('Upload failed. Please try again.', true);
                        return;
                    }

                    const json = await response.json();
                    const fileId = json?.data?.id ?? json?.data?.[0]?.id;
                    if (!fileId) {
                        setStatus('Upload failed. Missing file id.', true);
                        return;
                    }

                    if (hiddenInput) hiddenInput.value = fileId;
                    if (viewLink) viewLink.href = `/api/v1/files/${fileId}`;

                    if (upload) upload.classList.add('d-none');
                    if (existing) existing.classList.remove('d-none');
                    setStatus('Upload successful.');
                } catch (e) {
                    setStatus('Upload failed. Please try again.', true);
                }
            });
        };

        setupUploader('profilePhoto');
        setupUploader('coverPhoto');
    });
</script>
@endpush

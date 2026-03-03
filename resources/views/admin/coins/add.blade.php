@extends('admin.layouts.app')

@section('title', 'Add Coins')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0">Add Coins</h5>
            <small class="text-muted">Create a manual coins adjustment for a member.</small>
        </div>
        <a href="{{ route('admin.coins.index') }}" class="btn btn-outline-secondary">Back to Coins</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.coins.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-select js-user-select @error('user_id') is-invalid @enderror" required>
                        <option value="">Select a member</option>
                        @foreach ($users as $user)
                            @php
                                $name = trim((string) ($user->display_name ?? ''));
                                if ($name === '') {
                                    $name = trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? '')));
                                }
                                if ($name === '') {
                                    $name = '—';
                                }

                                if (isset($user->company_name) && trim((string) $user->company_name) !== '') {
                                    $company = trim((string) $user->company_name);
                                } elseif (isset($user->business_name) && trim((string) $user->business_name) !== '') {
                                    $company = trim((string) $user->business_name);
                                } else {
                                    $company = 'No Company';
                                }

                                $city = (isset($user->city) && trim((string) $user->city) !== '')
                                    ? trim((string) $user->city)
                                    : 'No City';

                                $circle = (string) (optional($user->circleMembers->first()?->circle)->name ?? 'No Circle');
                                $circle = trim($circle) !== '' ? trim($circle) : 'No Circle';

                                $label = $name.' — '.$company.' — '.$city.' — '.$circle;
                            @endphp
                            <option
                                value="{{ $user->id }}"
                                data-name="{{ $name }}"
                                data-company="{{ $company }}"
                                data-city="{{ $city }}"
                                data-circle="{{ $circle }}"
                                @selected(old('user_id') === $user->id)
                            >{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Activity (optional)</label>
                    <select name="activity" class="form-select @error('activity') is-invalid @enderror">
                        <option value="">None</option>
                        @foreach ($activityTypes as $type)
                            <option value="{{ $type }}" @selected(old('activity') === $type)>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                    @error('activity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">If selected, the ledger reference will start with "Activity: &lt;type&gt;".</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Coins Amount</label>
                    <input
                        type="number"
                        min="1"
                        name="amount"
                        value="{{ old('amount') }}"
                        class="form-control @error('amount') is-invalid @enderror"
                        required
                    >
                    @error('amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Remarks (optional)</label>
                    <input
                        type="text"
                        name="remarks"
                        value="{{ old('remarks') }}"
                        class="form-control @error('remarks') is-invalid @enderror"
                        maxlength="255"
                    >
                    @error('remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ route('admin.coins.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.$ && $.fn.select2) {
            const escapeHtml = (value) => $('<div>').text(value ?? '').html();
            const renderUser = (item) => {
                if (!item.id || !item.element) {
                    return item.text;
                }

                const option = item.element;
                const name = option.dataset.name || item.text || '—';
                const company = option.dataset.company || 'No Company';
                const city = option.dataset.city || 'No City';
                const circle = option.dataset.circle || 'No Circle';

                return $(
                    '<div class="d-flex flex-column">'
                    + '<div class="fw-semibold">' + escapeHtml(name) + '</div>'
                    + '<div class="text-muted small">' + escapeHtml(company) + '</div>'
                    + '<div class="text-muted small">' + escapeHtml(city) + '</div>'
                    + '<div class="text-muted small">' + escapeHtml(circle) + '</div>'
                    + '</div>'
                );
            };

            $('.js-user-select').select2({
                placeholder: 'Select a member',
                allowClear: true,
                width: '100%',
                templateResult: renderUser,
                templateSelection: (item) => {
                    if (!item.id || !item.element) {
                        return item.text;
                    }

                    const option = item.element;
                    const name = option.dataset.name || item.text || '—';
                    const company = option.dataset.company || 'No Company';
                    const city = option.dataset.city || 'No City';
                    const circle = option.dataset.circle || 'No Circle';

                    return `${name} — ${company} — ${city} — ${circle}`;
                }
            });
        }
    });
</script>
@endpush

@php
    $user = $user ?? (object) [];

    $name = $user->name
        ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
        ?? '—';

    $name = $name !== '' ? $name : '—';

    $company = $user->company_name
        ?? $user->company
        ?? $user->business_name
        ?? $user->organization
        ?? 'No Company';

    $city = $user->city
        ?? $user->current_city
        ?? $user->location_city
        ?? 'No City';

    $circleLine = $circleName ?? 'No Circle';
@endphp

<div class="d-flex flex-column">
    <div class="fw-semibold text-dark">{{ $name }}</div>
    <div class="text-muted small">{{ $company }}</div>
    <div class="text-muted small">{{ $city }}</div>
    <div class="text-muted small">{{ $circleLine }}</div>
</div>

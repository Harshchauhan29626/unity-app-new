@php
    $user = $user ?? null;

    $name = $user?->name
        ?? trim((($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')))
        ?: '—';

    $company = $user?->company_name
        ?? $user?->company
        ?? optional($user?->profile)->company_name
        ?? 'No Company';

    $city = $user?->city
        ?? optional($user?->profile)->city
        ?? 'No City';

    $circle = $circleName ?? 'No Circle';
@endphp

<div class="d-flex flex-column">
    <div class="fw-semibold text-dark">{{ $name }}</div>
    <div class="text-muted small">{{ $company }}</div>
    <div class="text-muted small">{{ $city }}</div>
    <div class="text-muted small">{{ $circle }}</div>
</div>

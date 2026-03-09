@php
    /** @var \App\Models\User|null $user */
    $user = $user ?? null;

    $name = trim((string) ($user?->display_name ?? ''));
    if ($name === '') {
        $name = trim(trim((string) ($user?->first_name ?? '')) . ' ' . trim((string) ($user?->last_name ?? '')));
    }
    if ($name === '') {
        $name = trim((string) ($user?->email ?? ''));
    }
    if ($name === '') {
        $name = '—';
    }

    $company = trim((string) ($user?->company_name ?? $user?->business_name ?? $user?->company ?? ''));
    if ($company === '') {
        $company = 'No Company';
    }

    $city = trim((string) ($user?->city ?? ''));
    if ($city === '') {
        $city = 'No City';
    }

    $circleName = trim((string) optional($user?->circleMembers?->first()?->circle)->name);
    if ($circleName === '') {
        $circleName = trim((string) optional($user?->circles?->first())->name);
    }
    if ($circleName === '') {
        $circleName = 'No Circle';
    }
@endphp

<div class="d-flex flex-column">
    <div class="fw-semibold text-dark">{{ $name }}</div>
    <div class="text-muted small">{{ $company }}</div>
    <div class="text-muted small">{{ $city }}</div>
    <div class="text-muted small">{{ $circleName }}</div>
</div>

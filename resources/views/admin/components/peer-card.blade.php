<div class="peer-card">
    <div class="peer-name fw-semibold text-truncate" style="max-width: {{ $maxWidth ?? 220 }}px;">
        {{ ($name ?? '') !== '' ? $name : '—' }}
    </div>
    <div class="small text-muted">{{ ($company ?? '') !== '' ? $company : '—' }}</div>
    <div class="small text-muted">{{ ($city ?? '') !== '' ? $city : 'No City' }}</div>
</div>

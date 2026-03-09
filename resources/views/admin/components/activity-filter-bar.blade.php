@php
    $label = $label ?? 'Search created by';
    $filters = $filters ?? [];
    $q = $filters['q'] ?? '';
    $from = $filters['from'] ?? '';
    $to = $filters['to'] ?? '';
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">{{ $label }}</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Name, company, or city">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control" placeholder="dd-mm-yyyy">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control" placeholder="dd-mm-yyyy">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
                <a href="{{ $resetUrl }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
        @if (!empty($showExport) && !empty($exportUrl))
            <div class="mt-2 d-flex justify-content-end">
                <a href="{{ $exportUrl }}" class="btn btn-outline-primary">Export</a>
            </div>
        @endif
    </div>
</div>

@php
    $filters = $filters ?? [];
    $q = $filters['q'] ?? request('q', '');
    $from = $filters['from'] ?? request('from', '');
    $to = $filters['to'] ?? request('to', '');
@endphp
<tr class="bg-light align-middle">
    <th colspan="{{ $colspan ?? 1 }}">
        <form method="GET" action="{{ $actionUrl }}" class="d-flex flex-wrap gap-2 align-items-end justify-content-between">
            <div class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label small text-muted mb-1">{{ $label ?? 'Search created by' }}</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Name, company, or city" style="min-width: 280px;">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ $resetUrl }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </div>
            @if (!empty($showExport) && !empty($exportUrl))
                <a href="{{ $exportUrl }}" class="btn btn-sm btn-outline-primary">Export</a>
            @endif
        </form>
    </th>
</tr>

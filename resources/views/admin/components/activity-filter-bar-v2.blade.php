@php
    $label = $titleLabel ?? 'Search created by';
    $filters = $filters ?? [];
    $q = $filters['q'] ?? request('q', '');
    $from = $filters['from'] ?? request('from', '');
    $to = $filters['to'] ?? request('to', '');
    $circleId = (string) ($filters['circle_id'] ?? request('circle_id', ''));
    $formId = $formId ?? null;
    $renderFormTag = $renderFormTag ?? true;
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-body">
        @if ($renderFormTag)
            <form method="GET" action="{{ $actionUrl }}" class="row g-3 align-items-end" @if($formId) id="{{ $formId }}" @endif>
        @else
            <div class="row g-3 align-items-end">
        @endif
            <div class="col-md-4">
                <label for="activityFilterQuery" class="form-label small text-muted">{{ $label }}</label>
                <input id="activityFilterQuery" type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Name, company, or city" @if($formId) form="{{ $formId }}" @endif>
                <div class="mt-2">
                    <label for="activityFilterCircle" class="form-label small text-muted">Circle</label>
                    <select id="activityFilterCircle" name="circle_id" class="form-select" @if($formId) form="{{ $formId }}" @endif>
                        <option value="">All Circles</option>
                        @foreach (($circles ?? collect()) as $circle)
                            <option value="{{ $circle->id }}" @selected($circleId !== '' && $circleId === (string) $circle->id)>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <label for="activityFilterFrom" class="form-label small text-muted">From</label>
                <input id="activityFilterFrom" type="date" name="from" value="{{ $from }}" class="form-control" placeholder="dd-mm-yyyy" @if($formId) form="{{ $formId }}" @endif>
            </div>
            <div class="col-md-2">
                <label for="activityFilterTo" class="form-label small text-muted">To</label>
                <input id="activityFilterTo" type="date" name="to" value="{{ $to }}" class="form-control" placeholder="dd-mm-yyyy" @if($formId) form="{{ $formId }}" @endif>
            </div>
            <div class="col-md-4 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary" @if($formId) form="{{ $formId }}" @endif>Apply</button>
                <a href="{{ $resetUrl }}" class="btn btn-outline-secondary">Reset</a>
                @if (!empty($showExport) && !empty($exportUrl))
                    <a href="{{ $exportUrl }}" class="btn btn-outline-primary">Export</a>
                @endif
            </div>
        @if ($renderFormTag)
            </form>
        @else
            </div>
        @endif
    </div>
</div>

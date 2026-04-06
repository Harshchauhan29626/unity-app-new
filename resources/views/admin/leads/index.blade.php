@extends('admin.layouts.app')

@section('title', $resource['title'])

@push('styles')
    <style>
        .table-responsive-horizontal {
            width: 100%;
            overflow-x: auto;
        }

        .table-responsive-horizontal table {
            min-width: 1200px;
        }

        .scroll-cell {
            max-width: 150px;
            overflow-x: auto;
            white-space: nowrap;
        }
    </style>
@endpush

@section('content')
    @php
        $formatValue = function ($value, string $column, bool $isLongText = false): string {
            if ($value === null || $value === '') {
                return '—';
            }

            if (in_array($column, ['created_at', 'updated_at'], true)) {
                return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
            }

            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
            }

            $stringValue = (string) $value;

            if ($isLongText) {
                return \Illuminate\Support\Str::limit($stringValue, 70);
            }

            return $stringValue;
        };

        $statusBadgeClass = static function (string $status): string {
            return match (strtolower(trim($status))) {
                'approved', 'active', 'completed' => 'bg-success-subtle text-success border border-success-subtle',
                'rejected', 'failed', 'inactive' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'pending', 'in_review' => 'bg-warning-subtle text-warning border border-warning-subtle',
                'new' => 'bg-info-subtle text-info border border-info-subtle',
                default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            };
        };

        $scrollableColumns = [
            'full_name',
            'first_name',
            'last_name',
            'contact_no',
            'contact_number',
            'phone',
            'mobile_number',
            'city',
            'email',
            'email_id',
            'business_name',
            'brand_or_company_name',
            'company_name',
        ];

        $columnFilterMap = [
            'full_name' => 'name',
            'first_name' => 'name',
            'contact_no' => 'phone',
            'contact_number' => 'phone',
            'phone' => 'phone',
            'city' => 'city',
            'email' => 'email',
            'email_id' => 'email',
            'business_name' => 'company',
            'brand_or_company_name' => 'company',
            'company_name' => 'company',
        ];

        $columnFilterLabels = [
            'name' => 'Name',
            'phone' => 'Mobile / Phone',
            'city' => 'City',
            'email' => 'Email',
            'company' => 'Company Name',
        ];

        $renderedFilterKeys = [];
    @endphp

    <form id="leadFiltersForm" method="GET" action="{{ route($resource['index_route']) }}"></form>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">{{ $resource['menu_label'] }}</h1>
        <span class="badge bg-light text-dark border">Total: {{ number_format($items->total()) }}</span>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="search" form="leadFiltersForm" value="{{ $filters['search'] }}" class="form-control" placeholder="Search by keyword">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" form="leadFiltersForm" class="form-select">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst((string) $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">From Date</label>
                    <input type="date" name="from_date" form="leadFiltersForm" value="{{ $filters['from_date'] }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">To Date</label>
                    <input type="date" name="to_date" form="leadFiltersForm" value="{{ $filters['to_date'] }}" class="form-control">
                </div>
                <div class="col-md-1 d-flex flex-column gap-2">
                    <button type="submit" form="leadFiltersForm" class="btn btn-primary">Apply</button>
                    <a href="{{ route($resource['index_route']) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive-horizontal">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        @foreach ($resource['columns'] as $column)
                            <th @if (in_array($column, $scrollableColumns, true)) style="min-width:150px;" @endif>{{ str_replace('_', ' ', ucfirst($column)) }}</th>
                        @endforeach
                        <th class="text-end">Action</th>
                    </tr>
                    <tr>
                        @foreach ($resource['columns'] as $column)
                            @php
                                $filterKey = $columnFilterMap[$column] ?? null;
                                $canRenderFilter = $filterKey && array_key_exists($filterKey, $filters) && ! in_array($filterKey, $renderedFilterKeys, true);
                            @endphp
                            <th>
                                @if ($canRenderFilter)
                                    @php $renderedFilterKeys[] = $filterKey; @endphp
                                    <input
                                        type="text"
                                        name="{{ $filterKey }}"
                                        form="leadFiltersForm"
                                        class="form-control form-control-sm"
                                        placeholder="{{ $columnFilterLabels[$filterKey] ?? 'Filter' }}"
                                        value="{{ $filters[$filterKey] ?? '' }}"
                                    >
                                @endif
                            </th>
                        @endforeach
                        <th class="text-end">
                            <div class="d-inline-flex align-items-center gap-2" style="white-space:nowrap;">
                                <button type="submit" form="leadFiltersForm" class="btn btn-sm btn-primary">Apply</button>
                                <a href="{{ route($resource['index_route']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            @foreach ($resource['columns'] as $column)
                                @php
                                    $isLongText = in_array($column, $resource['long_text_columns'], true);
                                    $value = data_get($item, $column);
                                @endphp
                                <td @if (in_array($column, $scrollableColumns, true)) class="scroll-cell" @endif>
                                    @if ($column === 'status')
                                        <span class="badge {{ $statusBadgeClass((string) $value) }}">{{ $formatValue($value, $column) }}</span>
                                    @elseif ($column === 'id')
                                        <span class="text-monospace small">{{ $formatValue($value, $column) }}</span>
                                    @else
                                        <span @if($isLongText) title="{{ (string) $value }}" @endif>{{ $formatValue($value, $column, $isLongText) }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-end">
                                <a href="{{ route($resource['show_route'], $item->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($resource['columns']) + 1 }}" class="text-center text-muted">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $items->links() }}
    </div>
@endsection

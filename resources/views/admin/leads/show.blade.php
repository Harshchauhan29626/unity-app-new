@extends('admin.layouts.app')

@section('title', $resource['menu_label'].' Details')

@section('content')
    @php
        $statusBadgeClass = static function (string $status): string {
            return match (strtolower(trim($status))) {
                'approved', 'active', 'completed' => 'bg-success-subtle text-success border border-success-subtle',
                'rejected', 'failed', 'inactive' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'pending', 'in_review' => 'bg-warning-subtle text-warning border border-warning-subtle',
                'new' => 'bg-info-subtle text-info border border-info-subtle',
                default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            };
        };
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">{{ $resource['menu_label'] }} Details</h1>
        <a href="{{ route($resource['index_route'], request()->query()) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                @foreach ($resource['columns'] as $column)
                    <div class="col-md-6">
                        <div class="small text-muted mb-1">{{ str_replace('_', ' ', ucfirst($column)) }}</div>
                        @php $value = data_get($item, $column); @endphp
                        @if ($column === 'status')
                            <span class="badge {{ $statusBadgeClass((string) $value) }}">{{ $value ?: '—' }}</span>
                        @elseif (in_array($column, ['notes', 'brief_bio', 'about_your_business', 'partnership_goal', 'why_partner_with_peers_global', 'topics_to_speak_on'], true))
                            <div class="border rounded p-2 bg-light" style="white-space: pre-wrap;">{{ $value ?: '—' }}</div>
                        @else
                            <div>{{ $value ?: '—' }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

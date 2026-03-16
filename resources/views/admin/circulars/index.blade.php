@extends('admin.layouts.app')

@section('title', 'Circulars')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0">Circulars</h5>
        <small class="text-muted">Manage announcements and updates</small>
    </div>
    <a href="{{ route('admin.circulars.create') }}" class="btn btn-primary btn-sm">Create Circular</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form class="card mb-3">
    <div class="card-body row g-2">
        <div class="col-md-3"><input class="form-control" name="search" placeholder="Search title / summary" value="{{ $filters['search'] ?? '' }}"></div>
        <div class="col-md-2">
            <select name="category" class="form-select"><option value="">Category</option>@foreach($categories as $x)<option value="{{ $x }}" @selected(($filters['category'] ?? '')===$x)>{{ ucfirst($x) }}</option>@endforeach</select>
        </div>
        <div class="col-md-2">
            <select name="priority" class="form-select"><option value="">Priority</option>@foreach($priorities as $x)<option value="{{ $x }}" @selected(($filters['priority'] ?? '')===$x)>{{ ucfirst($x) }}</option>@endforeach</select>
        </div>
        <div class="col-md-2">
            <select name="audience_type" class="form-select"><option value="">Audience</option>@foreach($audiences as $x)<option value="{{ $x }}" @selected(($filters['audience_type'] ?? '')===$x)>{{ ucfirst(str_replace('_', ' ', $x)) }}</option>@endforeach</select>
        </div>
        <div class="col-md-1">
            <select name="status" class="form-select"><option value="">Status</option><option value="active" @selected(($filters['status'] ?? '')==='active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '')==='inactive')>Inactive</option></select>
        </div>
        <div class="col-md-2">
            <select name="city_id" class="form-select"><option value="">City</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected(($filters['city_id'] ?? '')==$city->id)>{{ $city->name }}</option>@endforeach</select>
        </div>
        <div class="col-md-2">
            <select name="circle_id" class="form-select"><option value="">Circle</option>@foreach($circles as $circle)<option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? '')==$circle->id)>{{ $circle->name }}</option>@endforeach</select>
        </div>
        <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ $filters['from_date'] ?? '' }}" title="From publish date"></div>
        <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ $filters['to_date'] ?? '' }}" title="To publish date"></div>
        <div class="col-md-1 text-end"><button class="btn btn-outline-secondary">Filter</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Image</th><th>Title</th><th>Category</th><th>Priority</th><th>Audience</th><th>City</th><th>Circle</th><th>Publish</th><th>Expiry</th><th>Status</th><th>Pinned</th><th>Created By</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($circulars as $circular)
                <tr>
                    <td>@if($circular->featured_image_resolved_url)<img src="{{ $circular->featured_image_resolved_url }}" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:6px;">@else <span class="text-muted">—</span>@endif</td>
                    <td>{{ $circular->title }}<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($circular->summary, 60) }}</small></td>
                    <td>{{ ucfirst($circular->category) }}</td>
                    <td><span class="badge bg-light text-dark">{{ ucfirst($circular->priority) }}</span></td>
                    <td>{{ ucfirst(str_replace('_', ' ', $circular->audience_type)) }}</td>
                    <td>{{ $circular->city?->name ?? 'All' }}</td>
                    <td>{{ $circular->circle?->name ?? 'All' }}</td>
                    <td>{{ optional($circular->publish_date)->format('d M Y H:i') }}</td>
                    <td>{{ optional($circular->expiry_date)->format('d M Y H:i') ?? '—' }}</td>
                    <td>{{ ucfirst($circular->status) }}</td>
                    <td>{{ $circular->is_pinned ? 'Yes' : 'No' }}</td>
                    <td>{{ $circular->creator?->name ?? $circular->creator?->email ?? '—' }}</td>
                    <td class="text-end d-flex justify-content-end gap-1">
                        <form method="POST" action="{{ route('admin.circulars.toggle-status', $circular) }}">@csrf<button class="btn btn-sm btn-outline-warning">Toggle</button></form>
                        <a href="{{ route('admin.circulars.edit', $circular) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('admin.circulars.destroy', $circular) }}" onsubmit="return confirm('Delete this circular?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="13" class="text-center text-muted py-4">No circulars found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $circulars->links() }}</div>
@endsection

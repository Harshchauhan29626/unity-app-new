@extends('admin.layouts.app')

@section('title', 'Circle Joining Requests')

@section('content')
<div class="container-fluid">
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Search peer/email/phone/company" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-md-2"><select name="circle_id" class="form-select"><option value="">All Circles</option>@foreach($circles as $circle)<option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? '')===$circle->id)>{{ $circle->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-select"><option value="">All Statuses</option>@foreach(\App\Models\CircleJoinRequest::ACTIVE_STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ $status }}</option>@endforeach<option value="rejected_by_cd">rejected_by_cd</option><option value="rejected_by_id">rejected_by_id</option><option value="cancelled">cancelled</option></select></div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Apply</button></div>
        </form>
    </div></div>

    <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Submitted At</th><th>Peer</th><th>Company</th><th>City</th><th>Circle</th><th>Reason for Joining</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($requests as $row)
                <tr>
                    <td>{{ optional($row->requested_at)->format('d M Y H:i') }}</td>
                    <td>{{ $row->user?->adminDisplayName() }}</td>
                    <td>{{ $row->user?->adminCompanyLabel() }}</td>
                    <td>{{ $row->user?->adminCityLabel() }}</td>
                    <td>{{ $row->circle?->name }}</td>
                    <td>{{ \Illuminate\Support\Str::limit((string)$row->reason_for_joining, 50) }}</td>
                    <td>
                        <span class="badge text-bg-secondary">{{ $row->status }}</span>
                        @if($row->status === 'rejected_by_cd' && $row->cd_rejection_reason)
                            <div class="small text-danger mt-1">Reason: {{ \Illuminate\Support\Str::limit((string) $row->cd_rejection_reason, 60) }}</div>
                        @elseif($row->status === 'rejected_by_id' && $row->id_rejection_reason)
                            <div class="small text-danger mt-1">Reason: {{ \Illuminate\Support\Str::limit((string) $row->id_rejection_reason, 60) }}</div>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.circle-joining-requests.show', $row->id) }}" class="btn btn-sm btn-outline-primary">Details</a>
                        @if($row->status === 'pending_cd_approval')
                            <form method="POST" action="{{ route('admin.circle-joining-requests.approve-cd', $row->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve CD</button></form>
                        @endif
                        @if($row->status === 'pending_id_approval')
                            <form method="POST" action="{{ route('admin.circle-joining-requests.approve-id', $row->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve ID</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted">No requests found.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $requests->links() }}
    </div></div>
</div>
@endsection

@extends('admin.layouts.app')

@section('title', 'Impact Detail')

@section('content')
<div class="container-fluid">
    <h1 class="mb-3">Impact Detail</h1>
    <div class="card p-3 mb-3">
        <p><strong>Date:</strong> {{ optional($impact->impact_date)->toDateString() }}</p>
        <p><strong>User:</strong> {{ $impact->user->display_name ?? $impact->user->first_name }}</p>
        <p><strong>Impacted Peer:</strong> {{ $impact->impactedPeer->display_name ?? $impact->impactedPeer->first_name }}</p>
        <p><strong>Action:</strong> {{ $impact->action }}</p>
        <p><strong>Story:</strong> {{ $impact->story_to_share }}</p>
        <p><strong>Additional Remarks:</strong> {{ $impact->additional_remarks ?: '-' }}</p>
        <p><strong>Status:</strong> {{ $impact->status }}</p>
        <p><strong>Total Life Impacted:</strong> {{ (int) ($impact->life_impacted ?? 1) }}</p>
    </div>

    @if($impact->status === 'pending')
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('admin.impacts.approve', $impact->id) }}">
            @csrf
            <input type="text" name="review_remarks" class="form-control mb-2" placeholder="Review remarks (optional)">
            <button class="btn btn-success">Approve</button>
        </form>

        <form method="POST" action="{{ route('admin.impacts.reject', $impact->id) }}">
            @csrf
            <input type="text" name="review_remarks" class="form-control mb-2" placeholder="Review remarks (optional)">
            <button class="btn btn-danger">Reject</button>
        </form>
    </div>
    @endif
</div>
@endsection

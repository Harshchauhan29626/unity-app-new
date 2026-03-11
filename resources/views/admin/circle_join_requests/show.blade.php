@extends('admin.layouts.app')
@section('title', 'Circle Joining Request Detail')
@section('content')
<div class="container-fluid">
    <div class="card"><div class="card-body">
        <h5>Peer: {{ $record->user?->adminDisplayName() }}</h5>
        <p>Email: {{ $record->user?->email }} | Phone: {{ $record->user?->phone }}</p>
        <p>Company: {{ $record->user?->adminCompanyLabel() }} | City: {{ $record->user?->adminCityLabel() }}</p>
        <p>Circle: {{ $record->circle?->name }}</p>
        <p>Reason: {{ $record->reason_for_joining }}</p>
        <p>Status: <span class="badge text-bg-secondary">{{ $record->status }}</span></p>
        <hr>
        <h6 class="mt-4">Approval Timeline</h6>
        <p><strong>Submitted At:</strong> {{ optional($record->requested_at)->format('d M Y H:i') }}</p>
        <p><strong>Circle Director Approved By:</strong> {{ $record->cdApprovedBy?->adminDisplayName() ?? '—' }}</p>
        <p><strong>Circle Director Approved At:</strong> {{ optional($record->cd_approved_at)->format('d M Y H:i') ?? '—' }}</p>
        <p><strong>Circle Director Rejected By:</strong> {{ $record->cdRejectedBy?->adminDisplayName() ?? '—' }}</p>
        <p><strong>Circle Director Rejected At:</strong> {{ optional($record->cd_rejected_at)->format('d M Y H:i') ?? '—' }}</p>
        <p><strong>Circle Director Rejection Reason:</strong> <span class="text-danger">{{ $record->cd_rejection_reason ?: '—' }}</span></p>

        <p><strong>Industry Director Approved By:</strong> {{ $record->idApprovedBy?->adminDisplayName() ?? '—' }}</p>
        <p><strong>Industry Director Approved At:</strong> {{ optional($record->id_approved_at)->format('d M Y H:i') ?? '—' }}</p>
        <p><strong>Industry Director Rejected By:</strong> {{ $record->idRejectedBy?->adminDisplayName() ?? '—' }}</p>
        <p><strong>Industry Director Rejected At:</strong> {{ optional($record->id_rejected_at)->format('d M Y H:i') ?? '—' }}</p>
        <p><strong>Industry Director Rejection Reason:</strong> <span class="text-danger">{{ $record->id_rejection_reason ?: '—' }}</span></p>

        <p><strong>Fee Paid At:</strong> {{ optional($record->fee_paid_at)->format('d M Y H:i') ?? '—' }}</p>
        <p><strong>Membership Activated At:</strong> {{ optional($record->fee_paid_at)->format('d M Y H:i') ?? '—' }}</p>
    </div></div>
</div>
@endsection

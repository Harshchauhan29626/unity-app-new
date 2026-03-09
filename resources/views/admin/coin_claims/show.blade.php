@extends('admin.layouts.app')

@section('title', 'Coin Claim Details')

@section('content')
    @php
        $user = $claim->user;
        $fields = (array) data_get($claim->payload, 'fields', []);
        $files = (array) data_get($claim->payload, 'files', []);
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Coin Claim Details</h1>
        <a href="{{ route('admin.coin-claims.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm"><div class="card-body">
        <p><strong>Peer:</strong> {{ $user->display_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) }}</p>
        <p><strong>Phone:</strong> {{ $user->phone ?? '—' }}</p>
        <p><strong>Activity:</strong> {{ data_get($activity, 'label', $claim->activity_code) }}</p>
        <p><strong>Status:</strong> {{ ucfirst($claim->status) }}</p>
        <hr>
        <h6>Fields</h6>
        <ul>
            @foreach ($fields as $key => $value)
                <li><strong>{{ $key }}</strong>: {{ is_scalar($value) ? $value : json_encode($value) }}</li>
            @endforeach
        </ul>

        <h6>Files</h6>
        <ul>
            @forelse ($files as $key => $fileId)
                <li><strong>{{ $key }}</strong>: <a href="{{ url('/api/v1/files/' . $fileId) }}" target="_blank">{{ $fileId }}</a></li>
            @empty
                <li>—</li>
            @endforelse
        </ul>

        @if ($claim->status === 'pending')
            <form method="POST" action="{{ route('admin.coin-claims.approve', $claim->id) }}" class="d-inline">@csrf
                <button class="btn btn-success">Approve</button>
            </form>
            <form method="POST" action="{{ route('admin.coin-claims.reject', $claim->id) }}" class="d-inline">@csrf
                <input type="text" name="admin_note" class="form-control my-2" placeholder="Reason (optional)">
                <button class="btn btn-outline-danger">Reject</button>
            </form>
        @endif
    </div></div>
@endsection

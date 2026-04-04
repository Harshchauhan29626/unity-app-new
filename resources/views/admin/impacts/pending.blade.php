@extends('admin.layouts.app')

@section('title', 'Pending Impacts')

@section('content')
    @php
        $displayUser = function ($user): string {
            if (! $user) {
                return '—';
            }

            if (! empty($user->display_name)) {
                return (string) $user->display_name;
            }

            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            return $name !== '' ? $name : ((string) ($user->email ?? '—'));
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Pending Impacts</h1>
        <span class="badge bg-light text-dark border">Total: {{ number_format($impacts->total()) }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Impacted Peer</th>
                        <th>Submitted By</th>
                        <th>Story to Share</th>
                        <th>Additional Remarks</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($impacts as $impact)
                        <tr>
                            <td>{{ optional($impact->impact_date)->toDateString() }}</td>
                            <td>{{ $impact->action }}</td>
                            <td>{{ $displayUser($impact->impactedPeer) }}</td>
                            <td>{{ $displayUser($impact->user) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit((string) $impact->story_to_share, 120) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit((string) ($impact->additional_remarks ?? '—'), 100) }}</td>
                            <td>{{ optional($impact->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.impacts.show', $impact->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                <form method="POST" action="{{ route('admin.impacts.approve', $impact->id) }}" class="d-inline-flex gap-1">
                                    @csrf
                                    <input type="text" name="review_remarks" class="form-control form-control-sm" placeholder="Review remarks">
                                    <button class="btn btn-sm btn-success" onclick="return confirm('Approve this impact?')">Approve</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No pending impact requests.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $impacts->links() }}</div>
@endsection

@extends('admin.layouts.app')

@section('title', 'Impact All Posts')

@section('content')
<div class="container-fluid">
    <h1 class="mb-3">Impact All Posts (Approved)</h1>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Posted At</th><th>User</th><th>Impacted Peer</th><th>Action</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($impacts as $impact)
                    <tr>
                        <td>{{ optional($impact->timeline_posted_at)->toDateTimeString() }}</td>
                        <td>{{ $impact->user->display_name ?? $impact->user->first_name }}</td>
                        <td>{{ $impact->impactedPeer->display_name ?? $impact->impactedPeer->first_name }}</td>
                        <td>{{ $impact->action }}</td>
                        <td><span class="badge bg-success">{{ $impact->status }}</span></td>
                        <td><a href="{{ route('admin.impacts.show', $impact->id) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4">No approved impacts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $impacts->links() }}</div>
</div>
@endsection

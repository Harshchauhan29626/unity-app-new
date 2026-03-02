<div class="card shadow-sm h-100">
    <div class="card-header bg-white">
        <strong>{{ $title }}</strong>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Rank</th>
                    <th>Peer Name</th>
                    <th>{{ $totalLabel }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($rows ?? collect()) as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <div class="fw-semibold">{{ $row->peer_name ?? '-' }}</div>
                            <div class="small text-muted">{{ $row->peer_company ?? '-' }}</div>
                            <div class="small text-muted">{{ $row->peer_city ?? 'No City' }}</div>
                        </td>
                        <td>{{ (int) ($row->total ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted">No data found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

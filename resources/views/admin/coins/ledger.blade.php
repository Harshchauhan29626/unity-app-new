@extends('admin.layouts.app')

@section('title', 'Coins Ledger')

@section('content')
    @php
        $memberName = $member->display_name ?? trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
        $heading = $memberName ? $memberName . ' Coins Ledger' : 'Coins Ledger';
        $labelForType = function (?string $type): ?string {
            return $type ? \App\Support\Coins\CoinLedgerFormatter::why($type) : null;
        };
        $resetUrl = $activeType ? route('admin.coins.ledger.type', [$member, $activeType]) : route('admin.coins.ledger', $member);
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0">{{ $heading }}</h5>
            <small class="text-muted">{{ $member->adminDisplayInlineLabel() }}</small>
        </div>
        <a href="{{ route('admin.coins.index') }}" class="btn btn-outline-secondary">Back to Coins</a>
    </div>

    <div class="card shadow-sm">
        <div class="border-bottom p-3">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end" id="ledgerFilterForm">
                @if ($activeType)
                    <input type="hidden" name="active_type" value="{{ $activeType }}">
                @endif
                <div>
                    <label class="form-label small text-muted mb-1">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ $resetUrl }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <a href="{{ route('admin.coins.ledger.export', array_merge(['member' => $member->id], request()->query(), ['type' => $activeType])) }}" class="btn btn-sm btn-outline-primary">Export</a>
                </div>
                @if ($activeType)
                    <span class="badge bg-light text-dark border ms-auto">Type: {{ $labelForType($activeType) }}</span>
                @endif
            </form>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Coins</th>
                        <th>Balance After</th>
                        <th>Why</th>
                        <th>Created By</th>
                    </tr>
                    <tr>
                        <th>
                            <input
                                type="date"
                                name="date"
                                form="ledgerFilterForm"
                                value="{{ $filters['date'] ?? '' }}"
                                class="form-control form-control-sm"
                                placeholder="Date"
                            >
                        </th>
                        <th>
                            <input
                                type="text"
                                name="coins"
                                form="ledgerFilterForm"
                                value="{{ $filters['coins'] ?? '' }}"
                                class="form-control form-control-sm"
                                placeholder="Coins"
                            >
                        </th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th>
                            <select name="why" form="ledgerFilterForm" class="form-select form-select-sm">
                                <option value="">All Reasons</option>
                                <option value="testimonial" @selected(($filters['why'] ?? '') === 'testimonial')>Testimonial</option>
                                <option value="referral" @selected(($filters['why'] ?? '') === 'referral')>Referral</option>
                                <option value="business_deal" @selected(($filters['why'] ?? '') === 'business_deal')>Business Deal</option>
                                <option value="p2p_meeting" @selected(($filters['why'] ?? '') === 'p2p_meeting')>P2P Meeting</option>
                                <option value="requirement" @selected(($filters['why'] ?? '') === 'requirement')>Requirement</option>
                            </select>
                        </th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $reasonType = trim((string) ($item->reason_type ?? ''));
                        @endphp
                        <tr>
                            <td>{{ optional($item->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>{{ $item->amount }}</td>
                            <td>{{ $item->balance_after }}</td>
                            <td class="text-muted">{{ \App\Support\Coins\CoinLedgerFormatter::why($reasonType) }}</td>
                            <td>
                                @if ($item->createdBy)
                                    @include('admin.shared.peer_card', ['user' => $item->createdBy])
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No ledger entries found.</td>
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


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('ledgerFilterForm');

            if (!form) {
                return;
            }

            const inputs = form.querySelectorAll('input, select');

            inputs.forEach(function (input) {
                input.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush

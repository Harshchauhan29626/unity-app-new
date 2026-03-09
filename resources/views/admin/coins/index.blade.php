@extends('admin.layouts.app')

@section('title', 'Coins')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="d-flex flex-wrap justify-content-between align-items-center p-3 gap-2">
            <div class="d-flex align-items-center gap-2">
                <label for="perPage" class="form-label mb-0 small text-muted">Rows per page:</label>
                <select id="perPage" name="per_page" form="coinsFiltersForm" class="form-select form-select-sm" style="width: 90px;">
                    @foreach ([10, 20, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('admin.coins.create') }}" class="btn btn-sm btn-primary">Add Coins</a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Peer Name</th>
                        <th>Total Coins</th>
                        <th>Testimonials Coins</th>
                        <th>Referrals Coins</th>
                        <th>Business Deals Coins</th>
                        <th>P2P Meetings Coins</th>
                        <th>Requirements Coins</th>
                    </tr>
                    <tr class="bg-light align-middle">
                        <th>
                            <div class="d-flex flex-column gap-2">
                                <input
                                    type="text"
                                    name="q"
                                    form="coinsFiltersForm"
                                    class="form-control form-control-sm"
                                    placeholder="Peer/Company/City"
                                    value="{{ $filters['q'] }}"
                                >
                                <select name="circle_id" form="coinsFiltersForm" class="form-select form-select-sm">
                                    <option value="all">All Circles</option>
                                    @foreach ($circles as $circle)
                                        <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                                    @endforeach
                                </select>
                                <select name="membership_status" form="coinsFiltersForm" class="form-select form-select-sm">
                                    <option value="">Any</option>
                                    @foreach ($membershipStatuses as $status)
                                        <option value="{{ $status }}" @selected($filters['membership_status'] === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="—" disabled></th>
                        <th class="text-end">
                            <form id="coinsFiltersForm" method="GET" class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                <a href="{{ route('admin.coins.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                            </form>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        @php
                            $memberName = $member->adminName();
                            $company = $member->adminCompany();
                            $city = $member->adminCity();
                            $circleName = $member->adminCircleName();

                            $coins = $coinsByUserId[$member->id] ?? null;
                            $totalCoins = $coins->total_coins ?? 0;
                            $testimonialCoins = $coins->testimonial_coins ?? 0;
                            $referralCoins = $coins->referral_coins ?? 0;
                            $businessDealCoins = $coins->business_deal_coins ?? 0;
                            $p2pMeetingCoins = $coins->p2p_meeting_coins ?? 0;
                            $requirementCoins = $coins->requirement_coins ?? 0;
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-start gap-2">
                                    <div class="rounded-circle border d-flex align-items-center justify-content-center bg-light text-muted fw-semibold" style="width:36px;height:36px;">
                                        {{ strtoupper(mb_substr($memberName, 0, 1)) }}
                                    </div>
                                    <div class="d-flex flex-column">
                                        <div class="fw-semibold">{{ $memberName }}</div>
                                        <div class="text-muted small">{{ $company }}</div>
                                        <div class="text-muted small">{{ $city }}</div>
                                        <div class="text-muted small">{{ $circleName }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger', $member) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $totalCoins }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'testimonial']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $testimonialCoins }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'referral']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $referralCoins }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'business_deal']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $businessDealCoins }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'p2p_meeting']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $p2pMeetingCoins }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'requirement']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $requirementCoins }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">
            {{ $members->links() }}
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const perPage = document.getElementById('perPage');
                const form = document.getElementById('coinsFiltersForm');

                if (perPage && form) {
                    perPage.addEventListener('change', function () {
                        form.submit();
                    });
                }
            });
        </script>
    @endpush
@endsection

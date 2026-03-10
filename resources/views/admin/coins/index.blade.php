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
                        <th>Testimonials</th>
                        <th>Referrals</th>
                        <th>Business Deals</th>
                        <th>P2P Meetings</th>
                        <th>Requirements</th>
                    </tr>
                    <tr class="bg-light align-middle">
                        <th>
                            <div class="d-flex flex-column gap-2">
                                <input
                                    id="coinsQ"
                                    type="text"
                                    name="q"
                                    form="coinsFiltersForm"
                                    class="form-control form-control-sm"
                                    placeholder="Peer/Company/City"
                                    value="{{ $filters['q'] }}"
                                >
                                <select id="coinsCircle" name="circle_id" form="coinsFiltersForm" class="form-select form-select-sm">
                                    <option value="all">All Circles</option>
                                    @foreach ($circles as $circle)
                                        <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                                    @endforeach
                                </select>
                                <select name="membership_status" form="coinsFiltersForm" class="form-select form-select-sm">
                                    <option value="">Any Membership</option>
                                    @foreach ($membershipStatuses as $status)
                                        <option value="{{ $status }}" @selected($filters['membership_status'] === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                                <select name="activity_type" form="coinsFiltersForm" class="form-select form-select-sm">
                                    <option value="">All Activities</option>
                                    @foreach ($activityFilterOptions as $activityType => $activityLabel)
                                        <option value="{{ $activityType }}" @selected(($filters['activity_type'] ?? '') === $activityType)>{{ $activityLabel }}</option>
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
                                <a href="{{ route('admin.coins.export', request()->query()) }}" class="btn btn-sm btn-outline-primary">Export</a>
                            </form>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        @php
                            $stats = $activityStats[$member->id] ?? null;
                            $totalCoins = (int) ($stats->total_coins ?? 0);
                            $testimonialCount = (int) ($stats->testimonial_count ?? 0);
                            $referralCount = (int) ($stats->referral_count ?? 0);
                            $businessDealCount = (int) ($stats->business_deal_count ?? 0);
                            $p2pMeetingCount = (int) ($stats->p2p_meeting_count ?? 0);
                            $requirementCount = (int) ($stats->requirement_count ?? 0);
                        @endphp
                        <tr>
                            <td>
                                @include('admin.shared.peer_card', ['user' => $member])
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger', $member) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $totalCoins }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'testimonial']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $testimonialCount }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'referral']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $referralCount }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'business_deal']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $businessDealCount }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'p2p_meeting']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $p2pMeetingCount }}</a>
                            </td>
                            <td>
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'requirement']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $requirementCount }}</a>
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
                const qInput = document.getElementById('coinsQ');
                const circleSelect = document.getElementById('coinsCircle');

                if (perPage && form) {
                    perPage.addEventListener('change', function () {
                        form.submit();
                    });
                }

                const submitOnEnter = function (event) {
                    if (event.key === 'Enter' && form) {
                        event.preventDefault();
                        form.submit();
                    }
                };

                if (qInput) {
                    qInput.addEventListener('keydown', submitOnEnter);
                }

                if (circleSelect) {
                    circleSelect.addEventListener('keydown', submitOnEnter);
                }
            });
        </script>
    @endpush
@endsection

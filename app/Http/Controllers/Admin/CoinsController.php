<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CoinLedger;
use App\Models\User;
use App\Support\AdminCircleScope;
use App\Support\Coins\CoinLedgerFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoinsController extends Controller
{
    private const ACTIVITY_REFERENCE_PATTERNS = [
        'testimonial' => 'Activity: testimonial%',
        'referral' => 'Activity: referral%',
        'business_deal' => 'Activity: business_deal%',
        'p2p_meeting' => 'Activity: p2p_meeting%',
        'requirement' => 'Activity: requirement%',
    ];

    public function index(Request $request): View
    {
        $filters = $this->indexFilters($request);
        $members = $this->coinsIndexMembersQuery($filters)
            ->orderByDesc('total_coins_sort')
            ->orderBy('users.display_name')
            ->paginate($filters['per_page'])
            ->appends($request->query());

        $activityStats = $this->coinStatsByUserId($members->pluck('id')->all());

        return view('admin.coins.index', [
            'members' => $members,
            'filters' => $filters,
            'membershipStatuses' => User::query()->whereNotNull('membership_status')->distinct()->pluck('membership_status')->sort()->values(),
            'circles' => Circle::query()->orderBy('name')->get(['id', 'name']),
            'activityStats' => $activityStats,
            'activityFilterOptions' => collect(self::ACTIVITY_REFERENCE_PATTERNS)
                ->keys()
                ->mapWithKeys(fn (string $type) => [$type => CoinLedgerFormatter::why($type)])
                ->all(),
        ]);
    }

    public function exportIndex(Request $request): StreamedResponse
    {
        $filters = $this->indexFilters($request);

        $query = $this->coinsIndexMembersQuery($filters)
            ->orderByDesc('total_coins_sort')
            ->orderBy('users.display_name');

        $filename = 'coins_index_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Peer Name', 'Company', 'City', 'Circle', 'Total Coins', 'Testimonials', 'Referrals', 'Business Deals', 'P2P Meetings', 'Requirements']);

            $query->chunkById(500, function ($chunk) use ($handle): void {
                $stats = $this->coinStatsByUserId($chunk->pluck('id')->all());

                foreach ($chunk as $member) {
                    $item = $stats[$member->id] ?? null;

                    fputcsv($handle, [
                        $member->adminName(),
                        $member->adminCompany(),
                        $member->adminCity(),
                        $member->adminCircleName(),
                        (int) ($item->total_coins ?? 0),
                        (int) ($item->testimonial_count ?? 0),
                        (int) ($item->referral_count ?? 0),
                        (int) ($item->business_deal_count ?? 0),
                        (int) ($item->p2p_meeting_count ?? 0),
                        (int) ($item->requirement_count ?? 0),
                    ]);
                }
            }, 'users.id');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        $columns = ['id', 'display_name', 'first_name', 'last_name', 'email', 'city'];

        foreach (['company_name', 'business_name'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $columns[] = $column;
            }
        }

        $usersQuery = User::query()
            ->select($columns)
            ->with(['circleMembers' => function ($query) {
                $query->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }])
            ->orderByRaw("COALESCE(NULLIF(display_name,''), NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)),''), email) ASC");

        $this->applyCircleScopeToUsersQuery($usersQuery, auth('admin')->user());

        return view('admin.coins.create', [
            'users' => $usersQuery->get(),
            'activityTypes' => array_keys(self::ACTIVITY_REFERENCE_PATTERNS),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'activity' => ['nullable', 'string', Rule::in(array_keys(self::ACTIVITY_REFERENCE_PATTERNS))],
            'amount' => ['required', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $validated['user_id'];
        $amount = (int) $validated['amount'];
        $this->ensureMemberInScope($userId, auth('admin')->user());

        $activity = $validated['activity'] ?? null;
        $remarks = trim((string) ($validated['remarks'] ?? ''));
        $reference = $this->buildReference($activity, $remarks);

        DB::transaction(function () use ($userId, $amount, $reference): void {
            $latest = DB::table('coins_ledger')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            $previousBalance = $latest?->balance_after ?? 0;

            DB::table('coins_ledger')->insert([
                'transaction_id' => (string) Str::uuid(),
                'user_id' => $userId,
                'amount' => $amount,
                'balance_after' => $previousBalance + $amount,
                'activity_id' => null,
                'reference' => $reference,
                'created_by' => null,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.coins.index')->with('success', 'Coins added successfully.');
    }

    public function ledger(User $member, Request $request): View
    {
        $this->ensureMemberInScope($member->id, auth('admin')->user());
        $filters = $this->ledgerFilters($request);
        $items = $this->ledgerQuery($member, $filters)->paginate(20)->withQueryString();

        return view('admin.coins.ledger', $this->ledgerViewData($member, $items, $filters));
    }

    public function ledgerByType(User $member, string $type, Request $request): View
    {
        if (! array_key_exists($type, self::ACTIVITY_REFERENCE_PATTERNS)) {
            abort(404);
        }

        $this->ensureMemberInScope($member->id, auth('admin')->user());

        $filters = $this->ledgerFilters($request);
        $filters['active_type'] = $type;

        $items = $this->ledgerQuery($member, $filters, $type)->paginate(20)->withQueryString();

        return view('admin.coins.ledger', $this->ledgerViewData($member, $items, $filters));
    }

    public function exportLedger(User $member, Request $request): StreamedResponse
    {
        $this->ensureMemberInScope($member->id, auth('admin')->user());

        $type = (string) $request->query('type', '');
        $activeType = $type !== '' && array_key_exists($type, self::ACTIVITY_REFERENCE_PATTERNS) ? $type : null;

        $filters = $this->ledgerFilters($request);
        $query = $this->ledgerQuery($member, $filters, $activeType)->orderByDesc('created_at');

        $filename = 'coins_ledger_' . ($activeType ?: 'all') . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Coins', 'Balance After', 'Why', 'Created By Name', 'Company', 'City', 'Circle']);

            $query->chunkById(500, function ($chunk) use ($handle): void {
                foreach ($chunk as $item) {
                    $createdBy = $item->createdBy;

                    fputcsv($handle, [
                        optional($item->created_at)->format('Y-m-d H:i') ?? '—',
                        (int) $item->amount,
                        (int) $item->balance_after,
                        CoinLedgerFormatter::why($item->reason_type),
                        $createdBy?->adminName() ?? '—',
                        $createdBy?->adminCompany() ?? 'No Company',
                        $createdBy?->adminCity() ?? 'No City',
                        $createdBy?->adminCircleName() ?? 'No Circle',
                    ]);
                }
            }, 'transaction_id');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function coinsIndexMembersQuery(array $filters): Builder
    {
        $hasUsersName = Schema::hasColumn('users', 'name');
        $hasUsersCompany = Schema::hasColumn('users', 'company');
        $hasUsersBusinessName = Schema::hasColumn('users', 'business_name');

        $totalCoinsSubQuery = DB::table('coins_ledger as cl')
            ->selectRaw('cl.user_id, COALESCE(SUM(cl.amount),0) as total_coins')
            ->groupBy('cl.user_id');

        $query = User::query()
            ->select([
                'users.id',
                'users.email',
                'users.first_name',
                'users.last_name',
                'users.display_name',
                'users.membership_status',
                'users.company_name',
                'users.city',
            ])
            ->leftJoinSub($totalCoinsSubQuery, 'coins_totals', fn ($join) => $join->on('coins_totals.user_id', '=', 'users.id'))
            ->addSelect(DB::raw('COALESCE(coins_totals.total_coins, 0) as total_coins_sort'))
            ->with(['circleMembers' => function ($circleMembersQuery) {
                $circleMembersQuery->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }]);

        $this->applyCircleScopeToUsersQuery($query, auth('admin')->user());

        if ($filters['q'] !== '') {
            $query->where(function ($searchQuery) use ($filters, $hasUsersName, $hasUsersCompany, $hasUsersBusinessName) {
                $like = "%{$filters['q']}%";

                $searchQuery->where('users.display_name', 'ILIKE', $like)
                    ->orWhere('users.first_name', 'ILIKE', $like)
                    ->orWhere('users.last_name', 'ILIKE', $like)
                    ->orWhere('users.company_name', 'ILIKE', $like)
                    ->orWhere('users.city', 'ILIKE', $like);

                if ($hasUsersName) {
                    $searchQuery->orWhere('users.name', 'ILIKE', $like);
                }

                if ($hasUsersCompany) {
                    $searchQuery->orWhere('users.company', 'ILIKE', $like);
                }

                if ($hasUsersBusinessName) {
                    $searchQuery->orWhere('users.business_name', 'ILIKE', $like);
                }
            });
        }

        if ($filters['circle_id'] !== '' && $filters['circle_id'] !== 'all') {
            $query->whereHas('circleMembers', function ($circleMembersQuery) use ($filters) {
                $circleMembersQuery->where('circle_id', $filters['circle_id'])
                    ->where('status', 'approved')
                    ->whereNull('deleted_at');
            });
        }

        if ($filters['membership_status'] && $filters['membership_status'] !== 'all') {
            $query->where('users.membership_status', $filters['membership_status']);
        }

        if ($filters['activity_type'] !== '' && isset(self::ACTIVITY_REFERENCE_PATTERNS[$filters['activity_type']])) {
            $pattern = self::ACTIVITY_REFERENCE_PATTERNS[$filters['activity_type']];
            $query->whereExists(function ($exists) use ($pattern) {
                $exists->selectRaw('1')
                    ->from('coins_ledger as cl')
                    ->whereColumn('cl.user_id', 'users.id')
                    ->where('cl.reference', 'ILIKE', $pattern);
            });
        }

        return $query;
    }

    private function coinStatsByUserId(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        return DB::table('coins_ledger as cl')
            ->whereIn('cl.user_id', $memberIds)
            ->select([
                'cl.user_id',
                DB::raw('sum(cl.amount) as total_coins'),
                DB::raw("count(case when cl.reference ilike 'Activity: testimonial%' then 1 end) as testimonial_count"),
                DB::raw("count(case when cl.reference ilike 'Activity: referral%' then 1 end) as referral_count"),
                DB::raw("count(case when cl.reference ilike 'Activity: business_deal%' then 1 end) as business_deal_count"),
                DB::raw("count(case when cl.reference ilike 'Activity: p2p_meeting%' then 1 end) as p2p_meeting_count"),
                DB::raw("count(case when cl.reference ilike 'Activity: requirement%' then 1 end) as requirement_count"),
            ])
            ->groupBy('cl.user_id')
            ->get()
            ->keyBy('user_id')
            ->all();
    }

    private function ledgerQuery(User $member, array $filters, ?string $type = null): Builder
    {
        $query = CoinLedger::query()
            ->where('user_id', $member->id)
            ->with(['createdBy' => function ($q) {
                $q->with(['circleMembers' => function ($circleMembersQuery) {
                    $circleMembersQuery->where('status', 'approved')
                        ->whereNull('deleted_at')
                        ->orderByDesc('joined_at')
                        ->with('circle:id,name');
                }]);
            }])
            ->select('coins_ledger.*')
            ->selectRaw("COALESCE(NULLIF(split_part(split_part(reference, '|', 1), ':', 2), ''), '') as reason_type")
            ->when($type && isset(self::ACTIVITY_REFERENCE_PATTERNS[$type]), function (Builder $q) use ($type) {
                $q->where('reference', 'ILIKE', self::ACTIVITY_REFERENCE_PATTERNS[$type]);
            })
            ->when($filters['from'], fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when($filters['coins'] !== '', function (Builder $q) use ($filters) {
                if (is_numeric($filters['coins'])) {
                    $q->where('amount', (int) $filters['coins']);
                } else {
                    $q->whereRaw('CAST(amount AS TEXT) ILIKE ?', ['%' . $filters['coins'] . '%']);
                }
            })
            ->when($filters['created_by'] !== '', function (Builder $q) use ($filters) {
                $like = '%' . $filters['created_by'] . '%';

                $q->whereHas('createdBy', function (Builder $createdByQuery) use ($like) {
                    $createdByQuery->where(function (Builder $nested) use ($like) {
                        $nested->where('display_name', 'ILIKE', $like)
                            ->orWhere('first_name', 'ILIKE', $like)
                            ->orWhere('last_name', 'ILIKE', $like)
                            ->orWhere('company_name', 'ILIKE', $like)
                            ->orWhere('business_name', 'ILIKE', $like)
                            ->orWhere('city', 'ILIKE', $like)
                            ->orWhereHas('circleMembers.circle', fn (Builder $circleQuery) => $circleQuery->where('name', 'ILIKE', $like));
                    });
                });
            })
            ->orderByDesc('created_at');

        if ($filters['why'] !== '') {
            $this->applyWhyFilter($query, $filters['why']);
        }

        return $query;
    }

    private function applyWhyFilter(Builder $query, string $whyFilter): void
    {
        $value = strtolower(trim($whyFilter));
        $query->where(function (Builder $nested) use ($value) {
            $nested->whereRaw('LOWER(reference) LIKE ?', ['%' . $value . '%']);

            foreach (self::ACTIVITY_REFERENCE_PATTERNS as $type => $pattern) {
                if (str_contains(strtolower(CoinLedgerFormatter::why($type)), $value) || str_contains($type, $value)) {
                    $nested->orWhere('reference', 'ILIKE', $pattern);
                }
            }
        });
    }

    private function ledgerViewData(User $member, LengthAwarePaginator $items, array $filters): array
    {
        return [
            'member' => $member,
            'items' => $items,
            'filters' => $filters,
            'activeType' => $filters['active_type'] ?? null,
        ];
    }

    private function indexFilters(Request $request): array
    {
        $perPage = $request->integer('per_page') ?: 20;
        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;

        return [
            'q' => trim((string) $request->query('q', $request->query('search', ''))),
            'search' => trim((string) $request->query('q', $request->query('search', ''))),
            'circle_id' => (string) $request->query('circle_id', 'all'),
            'membership_status' => $request->query('membership_status'),
            'activity_type' => trim((string) $request->query('activity_type', '')),
            'per_page' => $perPage,
        ];
    }

    private function ledgerFilters(Request $request): array
    {
        return [
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'coins' => trim((string) $request->query('coins', '')),
            'why' => trim((string) $request->query('why', '')),
            'created_by' => trim((string) $request->query('created_by', '')),
            'active_type' => $request->query('active_type'),
        ];
    }

    private function buildReference(?string $activity, string $remarks): string
    {
        if ($activity) {
            return $remarks !== ''
                ? "Activity: {$activity} | Admin: {$remarks}"
                : "Activity: {$activity} | Admin adjustment";
        }

        return $remarks !== '' ? "Admin adjustment | {$remarks}" : 'Admin adjustment';
    }

    private function applyCircleScopeToUsersQuery($query, $admin): void
    {
        AdminCircleScope::applyToUsersQuery($query, $admin);
    }

    private function ensureMemberInScope(string $userId, $admin): void
    {
        if (! AdminCircleScope::userInScope($admin, $userId)) {
            abort(403);
        }
    }
}

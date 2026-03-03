<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CoinLedger;
use App\Models\User;
use App\Support\AdminCircleScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CoinsController extends Controller
{
    private const ACTIVITY_TABLES = [
        'testimonials' => 'testimonials',
        'referrals' => 'referrals',
        'business_deals' => 'business_deals',
        'p2p_meetings' => 'p2p_meetings',
        'requirements' => 'requirements',
    ];

    private const ACTIVITY_REFERENCE_PATTERNS = [
        'testimonial' => 'Activity: testimonial%',
        'referral' => 'Activity: referral%',
        'business_deal' => 'Activity: business_deal%',
        'p2p_meeting' => 'Activity: p2p_meeting%',
        'requirement' => 'Activity: requirement%',
    ];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', $request->query('search', '')));
        $circleId = (string) $request->query('circle_id', 'all');
        $membership = $request->query('membership_status');
        $perPage = $request->integer('per_page') ?: 20;
        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;

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
                $circleMembersQuery
                    ->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }]);

        $this->applyCircleScopeToUsersQuery($query, auth('admin')->user());

        if ($q !== '') {
            $query->where(function ($searchQuery) use ($q, $hasUsersName, $hasUsersCompany, $hasUsersBusinessName) {
                $like = "%{$q}%";

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

        if ($circleId !== '' && $circleId !== 'all') {
            $query->whereHas('circleMembers', function ($circleMembersQuery) use ($circleId) {
                $circleMembersQuery
                    ->where('circle_id', $circleId)
                    ->where('status', 'approved')
                    ->whereNull('deleted_at');
            });
        }

        if ($membership && $membership !== 'all') {
            $query->where('users.membership_status', $membership);
        }

        $members = $query
            ->orderByDesc('total_coins_sort')
            ->orderBy('users.display_name')
            ->paginate($perPage)
            ->appends($request->query());

        $memberIds = $members->pluck('id')->all();

        $coinsByUserId = DB::table('coins_ledger as cl')
            ->whereIn('cl.user_id', $memberIds)
            ->select([
                'cl.user_id',
                DB::raw('sum(cl.amount) as total_coins'),
                DB::raw("sum(case when cl.reference ilike 'Activity: testimonial%' then cl.amount else 0 end) as testimonial_coins"),
                DB::raw("sum(case when cl.reference ilike 'Activity: referral%' then cl.amount else 0 end) as referral_coins"),
                DB::raw("sum(case when cl.reference ilike 'Activity: business_deal%' then cl.amount else 0 end) as business_deal_coins"),
                DB::raw("sum(case when cl.reference ilike 'Activity: requirement%' then cl.amount else 0 end) as requirement_coins"),
                DB::raw("sum(case when cl.reference ilike 'Activity: p2p_meeting%' then cl.amount else 0 end) as p2p_meeting_coins"),
            ])
            ->groupBy('cl.user_id')
            ->get()
            ->keyBy('user_id')
            ->all();

        $membershipStatuses = User::query()
            ->whereNotNull('membership_status')
            ->distinct()
            ->pluck('membership_status')
            ->sort()
            ->values();

        $circles = Circle::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.coins.index', [
            'members' => $members,
            'filters' => [
                'q' => $q,
                'search' => $q,
                'circle_id' => $circleId,
                'membership_status' => $membership,
                'per_page' => $perPage,
            ],
            'membershipStatuses' => $membershipStatuses,
            'coinsByUserId' => $coinsByUserId,
            'circles' => $circles,
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

        $users = $usersQuery->get();

        return view('admin.coins.create', [
            'users' => $users,
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

        return redirect()
            ->route('admin.coins.index')
            ->with('success', 'Coins added successfully.');
    }

    public function ledger(User $member, Request $request): View
    {
        $this->ensureMemberInScope($member->id, auth('admin')->user());
        $filters = $this->dateFilters($request);

        $query = CoinLedger::query()
            ->where('user_id', $member->id)
            ->when($filters['from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->orderByDesc('created_at');

        $items = $query->paginate(20)->withQueryString();

        return view('admin.coins.ledger', $this->ledgerViewData($member, $items, $filters));
    }

    public function ledgerByType(User $member, string $type, Request $request): View
    {
        if (! array_key_exists($type, self::ACTIVITY_REFERENCE_PATTERNS)) {
            abort(404);
        }

        $this->ensureMemberInScope($member->id, auth('admin')->user());
        $filters = $this->dateFilters($request);
        $referencePattern = self::ACTIVITY_REFERENCE_PATTERNS[$type];

        $query = CoinLedger::query()
            ->where('user_id', $member->id)
            ->where('reference', 'ILIKE', $referencePattern)
            ->when($filters['from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->orderByDesc('created_at');

        $items = $query->paginate(20)->withQueryString();

        return view('admin.coins.ledger', array_merge(
            $this->ledgerViewData($member, $items, $filters),
            ['activeType' => $type]
        ));
    }

    private function ledgerViewData(User $member, $items, array $filters): array
    {
        $activityTypes = $this->resolveActivityTypes($items->pluck('activity_id')->filter()->unique()->all());
        $createdBy = $this->resolveCreatedByUsers($items->pluck('created_by')->filter()->unique()->all());

        return [
            'member' => $member,
            'items' => $items,
            'filters' => $filters,
            'activityTypes' => $activityTypes,
            'createdByUsers' => $createdBy,
            'activeType' => null,
        ];
    }

    private function resolveActivityTypes(array $activityIds): array
    {
        if ($activityIds === []) {
            return [];
        }

        $types = [];

        foreach (self::ACTIVITY_TABLES as $type => $table) {
            $ids = DB::table($table)
                ->whereIn('id', $activityIds)
                ->pluck('id')
                ->all();

            foreach ($ids as $id) {
                $types[$id] = $type;
            }
        }

        return $types;
    }

    private function resolveCreatedByUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->select(['id', 'email', 'first_name', 'last_name', 'display_name'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id')
            ->all();
    }

    private function dateFilters(Request $request): array
    {
        return [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }

    private function buildReference(?string $activity, string $remarks): string
    {
        if ($activity) {
            return $remarks !== ''
                ? "Activity: {$activity} | Admin: {$remarks}"
                : "Activity: {$activity} | Admin adjustment";
        }

        return $remarks !== ''
            ? "Admin adjustment | {$remarks}"
            : 'Admin adjustment';
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

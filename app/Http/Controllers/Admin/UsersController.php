<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel2;
use App\Models\CircleCategoryLevel3;
use App\Models\CircleCategoryLevel4;
use App\Models\CircleMember;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\PublicProfileSlugService;
use App\Support\AdminAccess;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function __construct(
        private readonly ZohoBillingService $zohoBillingService,
        private readonly PublicProfileSlugService $publicProfileSlugService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->expireTrialUsersForAdminPanel();

        [$query, $filters, $perPage] = $this->buildUserQuery($request);

        $users = $query->paginate($perPage)->appends($request->query());
        $canEditUsers = AdminAccess::canEditUsers(Auth::guard('admin')->user());

        $membershipStatuses = User::query()
            ->whereNotNull('membership_status')
            ->distinct()
            ->pluck('membership_status')
            ->sort()
            ->values();

        $circles = Circle::query()->orderBy('name')->get(['id', 'name']);
        $q = $filters['search'] ?? '';
        $circleId = $filters['circle_id'] ?? 'all';

        return view('admin.users.index', [
            'users' => $users,
            'membershipStatuses' => $membershipStatuses,
            'circles' => $circles,
            'q' => $q,
            'circleId' => $circleId,
            'filters' => $filters,
            'canEditUsers' => $canEditUsers,
        ]);
    }

    public function create(): View
    {
        $user = new User();
        $cities = City::query()->orderBy('name')->get();
        $membershipStatuses = $this->membershipStatuses();
        $circles = Circle::query()->orderBy('name')->get(['id', 'name', 'zoho_addon_code', 'zoho_addon_name']);

        return view('admin.users.create', [
            'user' => $user,
            'cities' => $cities,
            'membershipStatuses' => $membershipStatuses,
            'circles' => $circles,
            'membershipPlanOptions' => $this->membershipPlanOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $membershipStatuses = $this->membershipStatuses();

        $request->merge([
            'public_profile_slug' => $this->publicProfileSlugService->normalize($request->input('public_profile_slug')),
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'turnover_range' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:100'],
            'experience_summary' => ['nullable', 'string'],
            'short_bio' => ['nullable', 'string'],
            'long_bio_html' => ['nullable', 'string'],
            'public_profile_slug' => ['nullable', 'string', 'max:255', 'unique:users,public_profile_slug'],
            'membership_status' => ['nullable', Rule::in($membershipStatuses)],
            'membership_expiry' => ['nullable', 'date'],
            'membership_starts_at' => ['nullable', 'date'],
            'membership_ends_at' => ['nullable', 'date', 'after_or_equal:membership_starts_at'],
            'zoho_plan_code' => ['nullable', 'string', 'max:100', Rule::in($this->membershipPlanCodes())],
            'active_circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'active_circle_addon_code' => ['nullable', 'string', 'max:100'],
            'active_circle_addon_name' => ['nullable', 'string', 'max:255'],
            'circle_joined_at' => ['nullable', 'date'],
            'circle_expires_at' => ['nullable', 'date', 'after_or_equal:circle_joined_at'],
            'coins_balance' => ['nullable', 'integer', 'min:0'],
            'is_sponsored_member' => ['boolean'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'city' => ['nullable', 'string', 'max:150'],
            'profile_photo_file_id' => ['nullable', 'uuid'],
            'cover_photo_file_id' => ['nullable', 'uuid'],
            'industry_tags' => ['nullable', 'string', 'max:10000'],
            'target_regions' => ['nullable', 'string', 'max:10000'],
            'target_business_categories' => ['nullable', 'string', 'max:10000'],
            'hobbies_interests' => ['nullable', 'string', 'max:10000'],
            'leadership_roles' => ['nullable', 'string', 'max:10000'],
            'special_recognitions' => ['nullable', 'string', 'max:10000'],
            'skills' => ['nullable', 'string', 'max:10000'],
            'interests' => ['nullable', 'string', 'max:10000'],
            'social_links' => ['nullable', 'string', 'max:10000'],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'circle_city' => ['nullable', 'string', 'max:150'],
            'circle_country' => ['nullable', 'string', 'max:150'],
            'circle_meeting_mode' => ['nullable', 'string', 'max:50'],
            'circle_meeting_frequency' => ['nullable', 'string', 'max:50'],
        ]);

        $csvFields = [
            'industry_tags',
            'target_regions',
            'target_business_categories',
            'hobbies_interests',
            'leadership_roles',
            'special_recognitions',
            'skills',
            'interests',
        ];

        foreach ($csvFields as $field) {
            $validated[$field] = $this->csvToArray($request->input($field, ''));
        }

        $validated['social_links'] = $this->parseSocialLinks($request->input('social_links'));
        $validated = $this->syncMembershipExpiryInput($validated, $request);
        $validated['is_sponsored_member'] = $request->boolean('is_sponsored_member');
        $validated['membership_status'] = $validated['membership_status'] ?: ($membershipStatuses[0] ?? null);
        $validated['coins_balance'] = $validated['coins_balance'] ?? 0;
        $validated['password_hash'] = Hash::make(Str::random(32));

        $circleId = $validated['active_circle_id'] ?? ($validated['circle_id'] ?? null);
        $validated['active_circle_id'] = $circleId;
        unset($validated['circle_id']);

        $this->applyCircleAddonFields($validated, $circleId);

        $user = null;

        DB::transaction(function () use (&$user, $validated, $circleId) {
            $user = User::create($validated);

            if (! $circleId) {
                return;
            }

            $membershipAttributes = [
                'role' => 'member',
                'status' => $this->activeCircleMemberStatus(),
            ];

            if (Schema::hasColumn('circle_members', 'joined_at')) {
                $membershipAttributes['joined_at'] = $validated['circle_joined_at'] ?? now();
            }

            CircleMember::query()->updateOrCreate(
                [
                    'circle_id' => $circleId,
                    'user_id' => $user->id,
                ],
                $membershipAttributes,
            );
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Member created successfully.');
    }

    public function edit(Request $request, string $userId): View
    {
        if (! AdminAccess::canEditUsers(Auth::guard('admin')->user())) {
            abort(403);
        }

        $user = User::query()->findOrFail($userId);
        $this->expireTrialUserForAdminPanel($user);
        $user->refresh()->load(['city', 'roles']);
        $cities = City::query()->orderBy('name')->get();
        $adminRoleKeys = ['global_admin', 'industry_director', 'ded', 'circle_leader'];
        $roles = Role::query()
            ->whereIn('key', $adminRoleKeys)
            ->orderBy('name')
            ->get();
        $membershipStatuses = $this->membershipStatuses();
        $adminRoleIds = $roles->pluck('id')->all();
        $assignedAdminRoles = $user->roles->whereIn('id', $adminRoleIds)->values();
        $circles = Circle::query()
            ->orderBy('name')
            ->get(['id', 'name', 'zoho_addon_code', 'zoho_addon_name']);

        $joinedStatus = $this->activeCircleMemberStatus();
        $joinedCircleId = $this->activeCircleMembershipQuery($user->id, $joinedStatus)
            ->latest('created_at')
            ->value('circle_id');

        $effectiveCircleId = old('active_circle_id')
            ?: old('circle_id')
            ?: ($user->active_circle_id ?: null)
            ?: $request->query('circle_id')
            ?: $joinedCircleId;

        $selectedCircle = $effectiveCircleId
            ? Circle::query()->with('cityRef:id,name')->find($effectiveCircleId)
            : null;

        $circleMemberships = $this->activeCircleMembershipQuery($user->id, $joinedStatus)
            ->with('circle:id,name,slug')
            ->orderByDesc('joined_at')
            ->get();

        $joinedCircleCategoryTrees = collect();
        $circleIds = $circleMemberships->pluck('circle_id')->filter()->unique()->values();

        if ($circleIds->isNotEmpty()) {
            $circlesWithCategories = Circle::query()
                ->whereIn('id', $circleIds)
                ->with(['categories' => function ($query) {
                    $query->orderBy('sort_order')->orderBy('id');
                }])
                ->get(['id', 'name'])
                ->keyBy('id');

            $mappedMainCategoryIds = $circlesWithCategories
                ->flatMap(fn (Circle $circle) => $circle->categories->pluck('id'))
                ->unique()
                ->values();

            $level2ByMain = collect();
            $level3ByMain = collect();
            $level4ByMain = collect();

            if ($mappedMainCategoryIds->isNotEmpty()) {
                $level2ByMain = CircleCategoryLevel2::query()
                    ->whereIn('circle_category_id', $mappedMainCategoryIds)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('circle_category_id');

                $level3ByMain = CircleCategoryLevel3::query()
                    ->whereIn('circle_category_id', $mappedMainCategoryIds)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('circle_category_id');

                $level4ByMain = CircleCategoryLevel4::query()
                    ->whereIn('circle_category_id', $mappedMainCategoryIds)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('circle_category_id');
            }

            $joinedCircleCategoryTrees = $circleMemberships->map(function ($membership) use ($circlesWithCategories, $level2ByMain, $level3ByMain, $level4ByMain) {
                $circle = $circlesWithCategories->get($membership->circle_id);
                $mainCategories = $circle?->categories ?? collect();
                $selectedCategoryPath = is_array($membership->meta) ? ($membership->meta['category_path'] ?? []) : [];
                $selectedLevel1Id = (int) ($selectedCategoryPath['level1_category_id'] ?? 0);
                $selectedLevel2Id = (int) ($selectedCategoryPath['level2_category_id'] ?? 0);
                $selectedLevel3Id = (int) ($selectedCategoryPath['level3_category_id'] ?? 0);
                $selectedLevel4Id = (int) ($selectedCategoryPath['level4_category_id'] ?? 0);

                $categoryTree = $mainCategories->map(function ($mainCategory) use ($level2ByMain, $level3ByMain, $level4ByMain) {
                    $level2Items = $level2ByMain->get($mainCategory->id, collect());
                    $level3Items = $level3ByMain->get($mainCategory->id, collect());
                    $level4Items = $level4ByMain->get($mainCategory->id, collect());

                    $level3ByLevel2 = [];
                    foreach ($level3Items as $level3) {
                        $level2Id = $level3->level2_id ?? null;
                        if ($level2Id === null) {
                            continue;
                        }
                        $level3ByLevel2[$level2Id][] = $level3;
                    }

                    $level4ByLevel3 = [];
                    foreach ($level4Items as $level4) {
                        $level3Id = $level4->level3_id ?? null;
                        if ($level3Id === null) {
                            continue;
                        }
                        $level4ByLevel3[$level3Id][] = $level4;
                    }

                    $level2Tree = collect($level2Items)->map(function ($level2) use ($level3ByLevel2, $level4ByLevel3) {
                        $level3Tree = collect($level3ByLevel2[$level2->id] ?? [])->map(function ($level3) use ($level4ByLevel3) {
                            return [
                                'node' => $level3,
                                'children' => collect($level4ByLevel3[$level3->id] ?? []),
                            ];
                        });

                        return [
                            'node' => $level2,
                            'children' => $level3Tree,
                        ];
                    });

                    return [
                        'node' => $mainCategory,
                        'children' => $level2Tree,
                    ];
                });

                return [
                    'membership' => $membership,
                    'circle' => $circle,
                    'categories' => $categoryTree,
                    'selected_category_path' => [
                        'level1' => $selectedLevel1Id > 0
                            ? $mainCategories->firstWhere('id', $selectedLevel1Id)
                            : null,
                        'level2' => $selectedLevel2Id > 0
                            ? $level2ByMain->flatten(1)->firstWhere('id', $selectedLevel2Id)
                            : null,
                        'level3' => $selectedLevel3Id > 0
                            ? $level3ByMain->flatten(1)->firstWhere('id', $selectedLevel3Id)
                            : null,
                        'level4' => $selectedLevel4Id > 0
                            ? $level4ByMain->flatten(1)->firstWhere('id', $selectedLevel4Id)
                            : null,
                    ],
                ];
            });
        }

        $circleCategoryOptionsByCircle = $this->buildCircleCategoryPickerData($circles);

        $latestCircleSubscriptions = $user->circleSubscriptions()
            ->whereIn('circle_id', $circleMemberships->pluck('circle_id')->filter()->values())
            ->latest('paid_at')
            ->latest('created_at')
            ->get()
            ->groupBy('circle_id')
            ->map(fn ($items) => $items->first());

        $isJoinedToEffectiveCircle = false;
        if ($effectiveCircleId) {
            $isJoinedToEffectiveCircle = $this->activeCircleMembershipQuery($user->id, $joinedStatus)
                ->where('user_id', $user->id)
                ->where('circle_id', $effectiveCircleId)
                ->exists();
        }

        $meetingModes = ['Online', 'Offline', 'Hybrid'];
        $meetingFrequencies = ['Weekly', 'Monthly', 'Quarterly', 'Half Yearly', 'Yearly'];

        $citySuggestions = collect();
        if (Schema::hasColumn('circles', 'city')) {
            $citySuggestions = Circle::query()
                ->select(['id', 'city'])
                ->get()
                ->map(fn (Circle $circle) => trim((string) ($circle->city_display ?? '')))
                ->filter(fn (string $city) => $city !== '')
                ->unique()
                ->sort()
                ->values();
        }

        $countries = collect();
        if (Schema::hasColumn('circles', 'country')) {
            $countries = Circle::query()
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->distinct()
                ->orderBy('country')
                ->pluck('country')
                ->values();
        }

        return view('admin.users.edit', [
            'user' => $user,
            'cities' => $cities,
            'roles' => $roles,
            'membershipStatuses' => $membershipStatuses,
            'circles' => $circles,
            'joinedCircleId' => $joinedCircleId,
            'effectiveCircleId' => $effectiveCircleId,
            'selectedCircle' => $selectedCircle,
            'isJoinedToEffectiveCircle' => $isJoinedToEffectiveCircle,
            'circleMemberships' => $circleMemberships,
            'joinedCircleCategoryTrees' => $joinedCircleCategoryTrees,
            'latestCircleSubscriptions' => $latestCircleSubscriptions,
            'meetingModes' => $meetingModes,
            'meetingFrequencies' => $meetingFrequencies,
            'citySuggestions' => $citySuggestions,
            'countries' => $countries,
            'userRoleIds' => $assignedAdminRoles->pluck('id')->all(),
            'assignedAdminRoleNames' => $assignedAdminRoles->pluck('name')->implode(', '),
            'hasAssignedAdminRole' => $assignedAdminRoles->isNotEmpty(),
            'membershipPlanOptions' => $this->membershipPlanOptions($user->zoho_plan_code),
            'circleCategoryOptionsByCircle' => $circleCategoryOptionsByCircle,
        ]);
    }

    public function update(Request $request, string $userId)
    {
        if (! AdminAccess::canEditUsers(Auth::guard('admin')->user())) {
            abort(403);
        }

        $user = User::query()->findOrFail($userId);

        $membershipStatuses = $this->membershipStatuses();

        $request->merge([
            'public_profile_slug' => $this->publicProfileSlugService->normalize($request->input('public_profile_slug')),
        ]);
        $adminRoleKeys = ['global_admin', 'industry_director', 'ded', 'circle_leader'];
        $adminRoleIds = Role::query()
            ->whereIn('key', $adminRoleKeys)
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'turnover_range' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:100'],
            'experience_summary' => ['nullable', 'string'],
            'short_bio' => ['nullable', 'string'],
            'long_bio_html' => ['nullable', 'string'],
            'public_profile_slug' => ['nullable', 'string', 'max:255', 'unique:users,public_profile_slug,' . $user->id],
            'membership_status' => ['required', Rule::in($membershipStatuses)],
            'status' => ['required', 'in:active,inactive'],
            'membership_expiry' => ['nullable', 'date'],
            'membership_starts_at' => ['nullable', 'date'],
            'membership_ends_at' => ['nullable', 'date', 'after_or_equal:membership_starts_at'],
            'zoho_plan_code' => ['nullable', 'string', 'max:100', Rule::in($this->membershipPlanCodes($user->zoho_plan_code))],
            'active_circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'additional_circle_id' => [
                'nullable',
                'uuid',
                'exists:circles,id',
                'different:active_circle_id',
                Rule::requiredIf($request->has('add_circle_membership')),
            ],
            'level1_category_id' => ['nullable', 'integer', 'exists:circle_categories,id'],
            'level2_category_id' => ['nullable', 'integer', 'exists:circle_category_level2,id'],
            'level3_category_id' => ['nullable', 'integer', 'exists:circle_category_level3,id'],
            'level4_category_id' => ['nullable', 'integer', 'exists:circle_category_level4,id'],
            'active_circle_addon_code' => ['nullable', 'string', 'max:100'],
            'active_circle_addon_name' => ['nullable', 'string', 'max:255'],
            'circle_joined_at' => [Rule::requiredIf($request->has('add_circle_membership')), 'nullable', 'date'],
            'circle_expires_at' => [Rule::requiredIf($request->has('add_circle_membership')), 'nullable', 'date', 'after_or_equal:circle_joined_at'],
            'coins_balance' => ['required', 'integer', 'min:0'],
            'influencer_stars' => ['nullable', 'integer', 'min:0'],
            'is_sponsored_member' => ['boolean'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'city' => ['nullable', 'string', 'max:150'],
            'introduced_by' => ['nullable', 'exists:users,id'],
            'members_introduced_count' => ['nullable', 'integer', 'min:0'],
            'profile_photo_file_id' => ['nullable', 'uuid'],
            'cover_photo_file_id' => ['nullable', 'uuid'],
            'industry_tags' => ['nullable', 'string', 'max:10000'],
            'target_regions' => ['nullable', 'string', 'max:10000'],
            'target_business_categories' => ['nullable', 'string', 'max:10000'],
            'hobbies_interests' => ['nullable', 'string', 'max:10000'],
            'leadership_roles' => ['nullable', 'string', 'max:10000'],
            'special_recognitions' => ['nullable', 'string', 'max:10000'],
            'skills' => ['nullable', 'string', 'max:10000'],
            'interests' => ['nullable', 'string', 'max:10000'],
            'social_links' => ['nullable', 'string', 'max:10000'],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'circle_city' => ['nullable', 'string', 'max:150'],
            'circle_country' => ['nullable', 'string', 'max:150'],
            'circle_meeting_mode' => ['nullable', 'string', 'max:50'],
            'circle_meeting_frequency' => ['nullable', 'string', 'max:50'],
            'role_ids' => ['array', 'max:1'],
            'role_ids.*' => ['exists:roles,id', Rule::in($adminRoleIds)],
        ], [
            'role_ids.max' => 'You can not assign multiple roles.',
        ]);

        $csvFields = [
            'industry_tags',
            'target_regions',
            'target_business_categories',
            'hobbies_interests',
            'leadership_roles',
            'special_recognitions',
            'skills',
            'interests',
        ];

        foreach ($csvFields as $field) {
            $validated[$field] = $this->csvToArray($request->input($field, ''));
        }

        $validated['social_links'] = $this->parseSocialLinks($request->input('social_links'));
        $validated = $this->syncMembershipExpiryInput($validated, $request, $user);

        $booleanFields = ['is_sponsored_member'];
        foreach ($booleanFields as $field) {
            $validated[$field] = $request->boolean($field);
        }

        // Manual test: update a user to inactive and verify admin list shows "Inactive".
        $updatable = Arr::except($validated, ['role_ids', 'profile_photo_file_id', 'cover_photo_file_id', 'status', 'circle_id', 'active_circle_id', 'additional_circle_id', 'circle_city', 'circle_country', 'circle_meeting_mode', 'circle_meeting_frequency']);
        if ($user->membership_status !== $validated['membership_status']) {
            $updatable['membership_ends_at'] = null;
            $updatable['membership_expiry'] = null;
        }
        $currentAdminRoleIds = $user->roles()->whereIn('roles.id', $adminRoleIds)->pluck('roles.id');

        if ($request->filled('role_ids') && $currentAdminRoleIds->isNotEmpty()) {
            return back()
                ->withErrors(['role_ids' => 'Please remove existing role first.'])
                ->withInput();
        }

        $activeCircleMemberStatus = $this->activeCircleMemberStatus();
        $selectedCircleId = $validated['active_circle_id'] ?? ($validated['circle_id'] ?? null);
        $validated['active_circle_id'] = $selectedCircleId;
        $this->applyCircleAddonFields($validated, $selectedCircleId);
        DB::transaction(function () use ($user, $updatable, $validated, $request, $activeCircleMemberStatus, $selectedCircleId) {
            $user->fill($updatable);
            $user->status = $validated['status'];
            $user->active_circle_id = $selectedCircleId;

            if ($request->filled('profile_photo_file_id')) {
                $user->profile_photo_file_id = $request->input('profile_photo_file_id');
            }

            if ($request->filled('cover_photo_file_id')) {
                $user->cover_photo_file_id = $request->input('cover_photo_file_id');
            }

            $user->save();

            if ($selectedCircleId) {
                $membershipAttributes = [
                    'role' => 'member',
                    'status' => $activeCircleMemberStatus,
                ];

                if (Schema::hasColumn('circle_members', 'joined_at')) {
                    $membershipAttributes['joined_at'] = $validated['circle_joined_at'] ?? now();
                }

                $memberRecord = CircleMember::query()->firstOrNew([
                    'user_id' => $user->id,
                    'circle_id' => $selectedCircleId,
                ]);
                $memberRecord->fill(array_merge($membershipAttributes, ['left_at' => null]));
                $memberRecord->meta = $this->mergeMembershipCategoryMeta($memberRecord->meta, $validated);
                $memberRecord->save();

                $circle = Circle::query()->whereKey($selectedCircleId)->firstOrFail();

                $city = trim((string) ($validated['circle_city'] ?? ''));
                $country = trim((string) ($validated['circle_country'] ?? ''));
                $mode = trim((string) ($validated['circle_meeting_mode'] ?? ''));
                $frequency = trim((string) ($validated['circle_meeting_frequency'] ?? ''));

                if ($city !== '') {
                    if (Schema::hasColumn('circles', 'city_id')) {
                        $cityRecord = City::query()
                            ->whereRaw('LOWER(name) = ?', [mb_strtolower($city)])
                            ->first();

                        if (! $cityRecord) {
                            $cityRecord = City::create([
                                'name' => $city,
                            ]);
                        }

                        $circle->city_id = $cityRecord->id;
                    } elseif (Schema::hasColumn('circles', 'city')) {
                        $currentCity = $circle->getAttribute('city');
                        $isJsonCity = false;

                        if (is_array($currentCity)) {
                            $isJsonCity = true;
                        } elseif (is_string($currentCity) && str_starts_with(trim($currentCity), '{')) {
                            $isJsonCity = true;
                        }

                        if ($isJsonCity) {
                            $existing = is_array($currentCity)
                                ? $currentCity
                                : (json_decode((string) $currentCity, true) ?: []);

                            $circle->city = Circle::normalizeCityPayload($city, $existing);
                        } else {
                            $circle->city = $city;
                        }
                    }
                }

                if (Schema::hasColumn('circles', 'country') && $country !== '') {
                    $circle->country = $country;
                }

                if (Schema::hasColumn('circles', 'meeting_mode') && $mode !== '') {
                    $circle->meeting_mode = $mode;
                }

                if (Schema::hasColumn('circles', 'meeting_frequency') && $frequency !== '') {
                    $circle->meeting_frequency = $frequency;
                }

                $circle->save();

                \Log::info('Circle settings save', [
                    'circle_id' => $selectedCircleId,
                    'circle_city' => $city,
                    'circle_country' => $country,
                    'circle_meeting_mode' => $mode,
                    'circle_meeting_frequency' => $frequency,
                ]);
            }

            $additionalCircleId = $validated['additional_circle_id'] ?? null;
            if ($additionalCircleId) {
                $additionalMembership = [
                    'role' => 'member',
                    'status' => $activeCircleMemberStatus,
                ];

                if (Schema::hasColumn('circle_members', 'joined_at')) {
                    $additionalMembership['joined_at'] = $validated['circle_joined_at'] ?? now();
                }

                $additionalMemberRecord = CircleMember::query()->firstOrNew([
                    'user_id' => $user->id,
                    'circle_id' => $additionalCircleId,
                ]);
                $additionalMemberRecord->fill(array_merge($additionalMembership, ['left_at' => null]));
                $additionalMemberRecord->meta = $this->mergeMembershipCategoryMeta($additionalMemberRecord->meta, $validated);
                $additionalMemberRecord->save();
            }

            if ($request->filled('role_ids')) {
                $adminUser = AdminUser::find($user->id);

                if (! $adminUser) {
                    $adminUser = AdminUser::create([
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->display_name ?? $user->first_name,
                    ]);
                }

                $adminUser->roles()->sync($validated['role_ids']);
            }
        });

        $statusMessage = $request->has('add_circle_membership')
            ? 'Circle membership added successfully.'
            : 'User updated successfully.';

        return redirect()->route('admin.users.edit', $user->id)
            ->with('status', $statusMessage);
    }

    public function removeCircleMembership(Request $request, string $userId, string $circleMemberId): RedirectResponse
    {
        if (! AdminAccess::canEditUsers(Auth::guard('admin')->user())) {
            abort(403);
        }

        $user = User::query()->findOrFail($userId);

        $member = CircleMember::query()
            ->where('id', $circleMemberId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $member->forceFill([
            'left_at' => now(),
        ])->save();

        $member->delete();

        return redirect()
            ->route('admin.users.edit', $user->id)
            ->with('status', 'Circle membership removed successfully.');
    }

    public function removeRole(Request $request, string $userId): RedirectResponse
    {
        $user = User::query()->findOrFail($userId);
        $adminUser = AdminUser::find($user->id);

        if (! $adminUser) {
            return back()->withErrors(['roles' => 'Admin user record not found for this user.']);
        }

        $adminRoleKeys = ['global_admin', 'industry_director', 'ded', 'circle_leader'];
        $adminRoleIds = Role::query()
            ->whereIn('key', $adminRoleKeys)
            ->pluck('id')
            ->all();

        $adminUser->roles()->detach($adminRoleIds);

        $remainingRoles = $adminUser->roles()->count();

        if ($remainingRoles === 0) {
            $adminUser->delete();
        }

        return back()->with('success', 'Role removed successfully.');
    }

    public function importForm(): View
    {
        return view('admin.users.import');
    }

    public function import(Request $request): View
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if (! $handle) {
            return view('admin.users.import', ['error' => 'Unable to read uploaded file.']);
        }

        $header = fgetcsv($handle);
        if (! $header) {
            return view('admin.users.import', ['error' => 'CSV header is missing.']);
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $allowed = [
            'id', 'email', 'first_name', 'last_name', 'display_name', 'phone', 'company_name', 'membership_status', 'city', 'coins_balance',
        ];

        $membershipStatuses = $this->membershipStatuses();
        $results = [
            'created' => 0,
            'updated' => 0,
            'failed' => [],
        ];

        while (($row = fgetcsv($handle)) !== false) {
            $data = [];
            foreach ($header as $index => $column) {
                if (! in_array($column, $allowed, true)) {
                    continue;
                }
                $data[$column] = trim($row[$index] ?? '');
            }

            if (empty($data['email'])) {
                $results['failed'][] = ['row' => $data, 'reason' => 'Email is required'];
                continue;
            }

            $membership = $data['membership_status'] ?? null;
            if ($membership && ! in_array($membership, $membershipStatuses, true)) {
                $membership = null;
            }

            try {
                $user = User::query()->where('email', $data['email'])->first();

                if ($user) {
                    $updateFields = Arr::only($data, ['first_name', 'last_name', 'display_name', 'phone', 'company_name', 'membership_status', 'city', 'coins_balance']);
                    $updateFields = array_filter($updateFields, fn ($v) => $v !== '');

                    if ($membership) {
                        $updateFields['membership_status'] = $membership;
                    }

                    if (isset($updateFields['coins_balance']) && $updateFields['coins_balance'] !== '') {
                        $updateFields['coins_balance'] = (int) $updateFields['coins_balance'];
                    }

                    $user->fill($updateFields);
                    $user->save();
                    $results['updated']++;
                } else {
                    $payload = [
                        'email' => $data['email'],
                        'first_name' => $data['first_name'] ?: 'Unknown',
                        'last_name' => $data['last_name'] ?? null,
                        'display_name' => $data['display_name'] ?: ($data['first_name'] ?? 'User'),
                        'phone' => $data['phone'] ?? null,
                        'company_name' => $data['company_name'] ?? null,
                        'membership_status' => $membership ?: ($membershipStatuses[0] ?? null),
                        'city' => $data['city'] ?? null,
                        'coins_balance' => isset($data['coins_balance']) && $data['coins_balance'] !== '' ? (int) $data['coins_balance'] : 0,
                        'password_hash' => bcrypt(Str::random(32)),
                    ];

                    User::create($payload);
                    $results['created']++;
                }
            } catch (\Throwable $e) {
                $results['failed'][] = ['row' => $data, 'reason' => $e->getMessage()];
            }
        }

        fclose($handle);

        return view('admin.users.import', ['results' => $results]);
    }

    public function exportCsv(Request $request)
    {
        [$query] = $this->buildUserQuery($request);

        $selectedIds = $request->input('ids', []);
        if (is_string($selectedIds)) {
            $selectedIds = array_filter(explode(',', $selectedIds));
        }

        if (! empty($selectedIds)) {
            $query->whereIn('id', $selectedIds);
        }

        $users = $query->limit(10000)->get();
        $fileName = 'users_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $columns = [
            'id',
            'first_name',
            'last_name',
            'display_name',
            'email',
            'phone',
            'company_name',
            'membership_status',
            'city',
            'coins_balance',
            'status',
            'created_at',
            'updated_at',
        ];

        $callback = function () use ($users, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($users as $user) {
                $status = $user->deleted_at ? 'deleted' : 'active';
                fputcsv($handle, [
                    $user->id,
                    $user->first_name,
                    $user->last_name,
                    $user->display_name,
                    $user->email,
                    $user->phone,
                    $user->company_name,
                    $user->membership_status,
                    $user->city?->name ?? $user->city,
                    $user->coins_balance,
                    $status,
                    optional($user->created_at)->toDateTimeString(),
                    optional($user->updated_at)->toDateTimeString(),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function membershipPlanOptions(?string $selectedCode = null): array
    {
        $cacheKey = 'zoho_active_plans';
        $allowedPlanCodes = ['012', '013', '014'];

        try {
            $plans = Cache::remember($cacheKey, 600, function () {
                return $this->zohoBillingService->listActivePlans();
            });
        } catch (\Throwable $throwable) {
            report($throwable);
            $plans = [];
        }

        $options = collect($plans)
            ->filter(fn ($plan) => in_array((string) ($plan['plan_code'] ?? ''), $allowedPlanCodes, true))
            ->map(function (array $plan): array {
                $code = (string) ($plan['plan_code'] ?? '');
                $name = trim((string) ($plan['name'] ?? ''));

                return [
                    'code' => $code,
                    'label' => $name !== '' ? sprintf('%s (%s)', $name, $code) : $code,
                ];
            })
            ->filter(fn (array $plan) => $plan['code'] !== '')
            ->values();

        if ($selectedCode !== null && trim($selectedCode) !== '' && ! $options->contains(fn (array $plan) => $plan['code'] === $selectedCode)) {
            $options->prepend([
                'code' => $selectedCode,
                'label' => 'Current Saved Plan (' . $selectedCode . ')',
            ]);
        }

        return $options->all();
    }

    private function membershipPlanCodes(?string $selectedCode = null): array
    {
        return collect($this->membershipPlanOptions($selectedCode))
            ->pluck('code')
            ->filter()
            ->values()
            ->all();
    }

    private function applyCircleAddonFields(array &$validated, ?string $circleId): void
    {
        if (! $circleId) {
            $validated['active_circle_addon_code'] = null;
            $validated['active_circle_addon_name'] = null;

            return;
        }

        $circle = Circle::query()->find($circleId);

        $validated['active_circle_addon_code'] = $circle?->zoho_addon_code;
        $validated['active_circle_addon_name'] = $circle?->zoho_addon_name;
    }

    private function membershipStatuses(): array
    {
        return config('membership.statuses', []);
    }

    private function expireTrialUsersForAdminPanel(): void
    {
        User::query()
            ->where('membership_status', User::STATUS_FREE_TRIAL)
            ->whereNotNull('membership_ends_at')
            ->where('membership_ends_at', '<=', now())
            ->update([
                'membership_status' => User::STATUS_FREE,
            ]);
    }

    private function expireTrialUserForAdminPanel(User $user): void
    {
        if ($user->membership_status === User::STATUS_FREE_TRIAL
            && $user->membership_ends_at
            && $user->membership_ends_at->lessThanOrEqualTo(now())) {
            $user->membership_status = User::STATUS_FREE;
            $user->save();
        }
    }

    private function syncMembershipExpiryInput(array $validated, Request $request, ?User $user = null): array
    {
        $rawMembershipEndsAt = $request->input('membership_ends_at');
        $rawMembershipExpiry = $request->input('membership_expiry');

        $hasMembershipEndsAtInput = $rawMembershipEndsAt !== null;
        $hasMembershipExpiryInput = $rawMembershipExpiry !== null;

        if (! $hasMembershipEndsAtInput && ! $hasMembershipExpiryInput) {
            return $validated;
        }

        $currentMembershipEndsAtDate = $user?->membership_ends_at?->format('Y-m-d');
        $currentMembershipEndsAtDateTime = $user?->membership_ends_at?->format('Y-m-d\TH:i');

        $membershipEndsAtChanged = $hasMembershipEndsAtInput
            && $rawMembershipEndsAt !== ''
            && $rawMembershipEndsAt !== $currentMembershipEndsAtDate;

        $membershipExpiryChanged = $hasMembershipExpiryInput
            && $rawMembershipExpiry !== ''
            && $rawMembershipExpiry !== $currentMembershipEndsAtDateTime;

        if (($rawMembershipEndsAt === '' || $rawMembershipEndsAt === null) && ($rawMembershipExpiry === '' || $rawMembershipExpiry === null)) {
            $resolvedExpiry = null;
        } elseif ($membershipEndsAtChanged) {
            $resolvedExpiry = $validated['membership_ends_at'] ?? null;
        } elseif ($membershipExpiryChanged) {
            $resolvedExpiry = $validated['membership_expiry'] ?? null;
        } else {
            $resolvedExpiry = $validated['membership_ends_at']
                ?? $validated['membership_expiry']
                ?? null;
        }

        $validated['membership_ends_at'] = $resolvedExpiry;
        $validated['membership_expiry'] = $resolvedExpiry;

        return $validated;
    }

    private function csvToArray(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, fn ($v) => $v !== '');

        return array_values($parts);
    }

    private function parseSocialLinks(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $value)));

        $isKeyValue = false;
        foreach ($parts as $p) {
            if (str_contains($p, '=')) {
                $isKeyValue = true;
                break;
            }
        }

        if ($isKeyValue) {
            $obj = [];
            foreach ($parts as $p) {
                if (! str_contains($p, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $p, 2));
                if ($k !== '' && $v !== '') {
                    $obj[$k] = $v;
                }
            }
            return $obj;
        }

        return array_values($parts);
    }

    private function activeCircleMemberStatus(): string
    {
        return (string) config('circle.member_joined_status', 'approved');
    }

    private function activeCircleMembershipQuery(string $userId, string $joinedStatus)
    {
        return CircleMember::query()
            ->where('user_id', $userId)
            ->where('status', $joinedStatus)
            ->whereNull('deleted_at')
            ->whereNull('left_at')
            ->where(function ($query): void {
                $query->whereNull('paid_ends_at')->orWhere('paid_ends_at', '>=', now());
            });
    }

    private function buildUserQuery(Request $request): array
    {
        $allowedCircleIds = $request->attributes->get('allowed_circle_ids');
        $isCircleScoped = (bool) $request->attributes->get('is_circle_scoped');

        $joinedStatus = $this->activeCircleMemberStatus();

        $query = User::query()
            ->select([
                'id',
                'email',
                'phone',
                'first_name',
                'last_name',
                'display_name',
                'designation',
                'company_name',
                'profile_photo_url',
                'short_bio',
                'long_bio_html',
                'business_type',
                'industry_tags',
                'turnover_range',
                'city_id',
                'membership_status',
                'membership_expiry',
                'introduced_by',
                'members_introduced_count',
                'target_regions',
                'target_business_categories',
                'hobbies_interests',
                'leadership_roles',
                'is_sponsored_member',
                'public_profile_slug',
                'special_recognitions',
                'gdpr_deleted_at',
                'anonymized_at',
                'is_gdpr_exported',
                'coins_balance',
                'coin_medal_rank',
                'coin_milestone_title',
                'coin_milestone_meaning',
                'contribution_award_name',
                'contribution_award_recognition',
                'influencer_stars',
                'last_login_at',
                'created_at',
                'updated_at',
                'city',
                'skills',
                'interests',
                'gender',
                'dob',
                'experience_years',
                'experience_summary',
                'profile_photo_file_id',
                'cover_photo_file_id',
                'deleted_at',
                'status',
                'zoho_customer_id',
                'zoho_subscription_id',
                'zoho_plan_code',
                'zoho_last_invoice_id',
                'membership_starts_at',
                'membership_ends_at',
                'last_payment_at',
            ])
            ->with([
                'city',
                'circleMembers' => function ($circleMembersQuery) use ($joinedStatus) {
                    $circleMembersQuery
                        ->where('status', $joinedStatus)
                        ->whereNull('deleted_at')
                        ->whereNull('left_at')
                        ->where(function ($query): void {
                            $query->whereNull('paid_ends_at')->orWhere('paid_ends_at', '>=', now());
                        })
                        ->orderByDesc('joined_at')
                        ->with(['circle:id,name']);
                },
            ]);

        if ($isCircleScoped && is_array($allowedCircleIds)) {
            if ($allowedCircleIds === []) {
                $query->whereRaw('1=0');
            } else {
                $query->whereExists(function ($subQuery) use ($allowedCircleIds, $joinedStatus) {
                    $subQuery->selectRaw(1)
                        ->from('circle_members as cm')
                        ->whereColumn('cm.user_id', 'users.id')
                        ->where('cm.status', $joinedStatus)
                        ->whereNull('cm.deleted_at')
                        ->whereIn('cm.circle_id', $allowedCircleIds);
                });
            }
        }

        $search = trim((string) $request->query('q', $request->input('search', '')));
        $circleId = (string) $request->query('circle_id', 'all');
        $membership = $request->input('membership_status');
        $phone = $request->input('phone');
        $perPage = $request->integer('per_page') ?: 20;

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";

                $searchableColumns = [
                    'name',
                    'display_name',
                    'first_name',
                    'last_name',
                    'email',
                    'company',
                    'company_name',
                    'business_name',
                    'city',
                    'phone',
                ];

                $hasSearchColumn = false;
                foreach ($searchableColumns as $column) {
                    if (! Schema::hasColumn('users', $column)) {
                        continue;
                    }

                    if (! $hasSearchColumn) {
                        $q->where($column, 'ILIKE', $like);
                        $hasSearchColumn = true;
                        continue;
                    }

                    $q->orWhere($column, 'ILIKE', $like);
                }

                if (! $hasSearchColumn) {
                    $q->whereRaw('1=0');
                }

                $q->orWhereHas('city', function ($cityQuery) use ($like) {
                    $cityQuery->where('name', 'ILIKE', $like);
                });
            });
        }

        if ($circleId !== '' && $circleId !== 'all') {
            $query->whereHas('circleMembers', function ($circleMembersQuery) use ($circleId, $joinedStatus) {
                $circleMembersQuery
                    ->where('circle_id', $circleId)
                    ->where('status', $joinedStatus)
                    ->whereNull('deleted_at');
            });
        }

        if ($membership && $membership !== 'all') {
            $query->where('membership_status', $membership);
        }

        if ($phone) {
            $query->where('phone', 'ILIKE', "%{$phone}%");
        }

        $sortable = ['display_name', 'coins_balance', 'last_login_at', 'created_at'];
        $sort = $request->input('sort');
        $direction = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sort && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByDesc('last_login_at');
        }

        $perPage = in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;

        if ($search !== '' || filled($phone) || ($circleId !== '' && $circleId !== 'all') || ($membership && $membership !== 'all')) {
            Log::info('admin.users.index.filters_applied', [
                'search' => $search,
                'phone_filter' => $phone,
                'circle_id' => $circleId,
                'membership_status' => $membership,
                'is_circle_scoped' => $isCircleScoped,
            ]);
        }

        $filters = [
            'search' => $search,
            'circle_id' => $circleId,
            'membership_status' => $membership,
            'phone' => $phone,
            'per_page' => $perPage,
            'sort' => $sort,
            'dir' => $direction,
        ];

        return [$query, $filters, $perPage];
    }

    private function buildCircleCategoryPickerData($circles)
    {
        $circleIds = collect($circles)->pluck('id')->filter()->unique()->values();

        if ($circleIds->isEmpty()) {
            return [];
        }

        $circleCategoryIdsMap = DB::table('circle_category_mappings')
            ->whereIn('circle_id', $circleIds)
            ->orderBy('category_id')
            ->get(['circle_id', 'category_id'])
            ->groupBy('circle_id')
            ->map(fn ($rows) => collect($rows)->pluck('category_id')->unique()->values());

        $allMainCategoryIds = $circleCategoryIdsMap->flatten()->unique()->values();

        $mainCategories = $allMainCategoryIds->isEmpty()
            ? collect()
            : CircleCategory::query()
                ->whereIn('id', $allMainCategoryIds)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'slug']);

        $level2 = $allMainCategoryIds->isEmpty()
            ? collect()
            : CircleCategoryLevel2::query()
                ->whereIn('circle_category_id', $allMainCategoryIds)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'circle_category_id', 'name']);

        $level2Ids = $level2->pluck('id')->values();

        $level3 = $level2Ids->isEmpty()
            ? collect()
            : CircleCategoryLevel3::query()
                ->whereIn('level2_id', $level2Ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'circle_category_id', 'level2_id', 'name']);

        $level3Ids = $level3->pluck('id')->values();

        $level4 = $level3Ids->isEmpty()
            ? collect()
            : CircleCategoryLevel4::query()
                ->whereIn('level3_id', $level3Ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'circle_category_id', 'level3_id', 'name']);

        $mainById = $mainCategories->keyBy('id');
        $level2ByMain = $level2->groupBy('circle_category_id');
        $level3ByLevel2 = [];
        foreach ($level3 as $row) {
            $level2Id = $row->level2_id ?? null;
            if (! $level2Id) {
                continue;
            }
            $level3ByLevel2[$level2Id][] = $row;
        }
        $level4ByLevel3 = [];
        foreach ($level4 as $row) {
            $level3Id = $row->level3_id ?? null;
            if (! $level3Id) {
                continue;
            }
            $level4ByLevel3[$level3Id][] = $row;
        }

        $result = [];
        foreach ($circleIds as $circleId) {
            $mainIds = $circleCategoryIdsMap->get($circleId, collect());
            $mainOptions = [];
            $level2Options = [];
            $level3Options = [];
            $level4Options = [];

            foreach ($mainIds as $mainId) {
                $main = $mainById->get($mainId);
                if (! $main) {
                    continue;
                }

                $mainOptions[] = [
                    'id' => $main->id,
                    'name' => $main->name,
                ];

                foreach ($level2ByMain->get($main->id, collect()) as $l2) {
                    $level2Options[] = [
                        'id' => $l2->id,
                        'parent_id' => $main->id,
                        'name' => $l2->name,
                    ];

                    foreach (($level3ByLevel2[$l2->id] ?? []) as $l3) {
                        $level3Options[] = [
                            'id' => $l3->id,
                            'parent_id' => $l2->id,
                            'name' => $l3->name,
                        ];

                        foreach (($level4ByLevel3[$l3->id] ?? []) as $l4) {
                            $level4Options[] = [
                                'id' => $l4->id,
                                'parent_id' => $l3->id,
                                'name' => $l4->name,
                            ];
                        }
                    }
                }
            }

            $result[(string) $circleId] = [
                'level1' => $mainOptions,
                'level2' => $level2Options,
                'level3' => $level3Options,
                'level4' => $level4Options,
            ];
        }

        return $result;
    }

    private function mergeMembershipCategoryMeta($existingMeta, array $validated): array
    {
        $meta = is_array($existingMeta) ? $existingMeta : [];

        $level1 = (int) ($validated['level1_category_id'] ?? 0);
        $level2 = (int) ($validated['level2_category_id'] ?? 0);
        $level3 = (int) ($validated['level3_category_id'] ?? 0);
        $level4 = (int) ($validated['level4_category_id'] ?? 0);

        if ($level1 <= 0 && $level2 <= 0 && $level3 <= 0 && $level4 <= 0) {
            return $meta;
        }

        $meta['category_path'] = [
            'level1_category_id' => $level1 > 0 ? $level1 : null,
            'level2_category_id' => $level2 > 0 ? $level2 : null,
            'level3_category_id' => $level3 > 0 ? $level3 : null,
            'level4_category_id' => $level4 > 0 ? $level4 : null,
        ];

        return $meta;
    }

}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Circle\StoreCircleRequest;
use App\Http\Requests\Circle\UpdateCircleMemberRequest;
use App\Http\Resources\CircleMemberResource;
use App\Http\Resources\CircleResource;
use App\Models\Circle;
use App\Models\CircleMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CircleController extends BaseApiController
{
    /**
     * Example GET /api/v1/circles response item:
     * {
     *   "id": "uuid",
     *   "name": "Winners",
     *   "slug": "winners",
     *   "type": "private",
     *   "status": "active",
     *   "country": "India",
     *   "city_id": "uuid",
     *   "city": { "id":"uuid","name":"Ahmedabad","state":null,"district":null,"country":"India","country_code":null },
     *   "industry_tags": ["MSME","Real Estate"],
     *   "meeting_mode": "online",
     *   "meeting_frequency": "monthly",
     *   "meeting_repeat": { "repeat_every": 1, "unit": "month", "weekday": "saturday", "time": "10:00" },
     *   "launch_date": "2026-01-19",
     *   "founder_user_id": "uuid",
     *   "director_user_id": "uuid",
     *   "industry_director_user_id": "uuid",
     *   "ded_user_id": "uuid",
     *   "cover_file_id": "uuid",
     *   "cover_image_url": "https://peersunity.com/api/v1/files/{id}",
     *   "peers_count": 4,
     *   "created_at": "...",
     *   "updated_at": "..."
     * }
     */
    public function index(Request $request)
    {
        $query = Circle::query()
            ->with([
                'founder:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                'founder.cityRelation:id,name',
                'director:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                'director.cityRelation:id,name',
                'industryDirector:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                'industryDirector.cityRelation:id,name',
                'ded:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                'ded.cityRelation:id,name',
                'city:id,name,state,district,country,country_code',
                'categories:id,category_name,sector',
            ])
            ->withCount([
                'members as members_count' => function ($q) {
                    $q->where('status', 'approved');
                },
                'members as peers_count' => function ($q) {
                    $q->where('status', 'approved');
                },
            ]);

        if ($search = trim((string) ($request->input('search') ?? $request->input('q', '')))) {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('name', 'ILIKE', $like)
                    ->orWhere('description', 'ILIKE', $like)
                    ->orWhere('purpose', 'ILIKE', $like)
                    ->orWhere('slug', 'ILIKE', $like);
            });
        }

        foreach (['city_id', 'status', 'type', 'country'] as $filter) {
            if ($value = $request->input($filter)) {
                $query->where($filter, $value);
            }
        }

        $paginator = $query->orderBy('name')->paginate((int) $request->input('per_page', 20));

        $circleIds = collect($paginator->items())->pluck('id');
        $memberships = CircleMember::whereIn('circle_id', $circleIds)
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('circle_id');

        foreach ($paginator->items() as $circle) {
            $circle->setRelation('currentMember', $memberships->get($circle->id));
        }

        return $this->success([
            'items' => CircleResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $circle = Circle::with([
            'city:id,name,state,district,country,country_code',
            'founder:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
            'founder.cityRelation:id,name',
            'director:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
            'director.cityRelation:id,name',
            'industryDirector:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
            'industryDirector.cityRelation:id,name',
            'ded:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
            'ded.cityRelation:id,name',
            'categories' => function ($query) {
                $query->select(['categories.id', 'category_name', 'sector', 'remarks'])
                    ->orderBy('category_name');
            },
        ])
            ->withCount([
                'members as members_count' => function ($q) {
                    $q->where('status', 'approved');
                },
                'members as peers_count' => function ($q) {
                    $q->where('status', 'approved');
                },
            ])
            ->find($id);

        if (! $circle) {
            return $this->error('Circle not found', 404);
        }

        $circle->setRelation(
            'currentMember',
            $circle->members()
                ->where('user_id', auth()->id())
                ->first()
        );

        return $this->success(new CircleResource($circle));
    }

    public function store(StoreCircleRequest $request)
    {
        $authUser = $request->user();
        $data = $request->validated();

        return DB::transaction(function () use ($data, $authUser) {
            $circle = new Circle();
            $circle->fill($data);
            $circle->slug = Circle::generateUniqueSlug((string) ($data['name'] ?? 'circle'));
            $circle->founder_user_id = $authUser->id;
            $circle->save();

            CircleMember::create([
                'circle_id' => $circle->id,
                'user_id' => $authUser->id,
                'role' => 'founder',
                'status' => 'approved',
                'joined_at' => now(),
            ]);

            $circle->load(['city', 'founder']);

            return $this->success(new CircleResource($circle), 'Circle created successfully', 201);
        });
    }

    public function join(Request $request, string $circleId)
    {
        $user = $request->user();

        if (! Str::isUuid($circleId)) {
            return $this->error('Invalid circle id format', 422);
        }

        $circle = Circle::find($circleId);
        if (! $circle) {
            return $this->error('Circle not found', 404);
        }

        if ($circle->founder_user_id === $user->id) {
            return $this->success(null, 'You are the founder of this circle');
        }

        $existing = CircleMember::where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return $this->success(null, 'You have already requested to join or are already a member');
        }

        $member = CircleMember::create([
            'circle_id' => $circle->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'pending',
            'substitute_count' => 0,
        ]);

        return $this->success($member, 'Join request submitted successfully', 201);
    }

    public function myCircles(Request $request)
    {
        $user = $request->user();

        $circleIds = CircleMember::where('user_id', $user->id)
            ->where('status', '!=', 'rejected')
            ->pluck('circle_id');

        $paginator = Circle::whereIn('id', $circleIds)
            ->with([
                'founder:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                'founder.cityRelation:id,name',
                'director:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                'director.cityRelation:id,name',
                'industryDirector:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                'industryDirector.cityRelation:id,name',
                'ded:id,first_name,last_name,display_name,profile_photo_url,email,phone,city,city_id,company_name',
                'ded.cityRelation:id,name',
                'city:id,name,state,district,country,country_code',
                'categories:id,category_name,sector',
            ])
            ->withCount([
                'members as members_count' => function ($q) {
                    $q->where('status', 'approved');
                },
                'members as peers_count' => function ($q) {
                    $q->where('status', 'approved');
                },
            ])
            ->orderBy('name')
            ->paginate((int) $request->input('per_page', 20));

        $memberships = CircleMember::whereIn('circle_id', $paginator->pluck('id'))
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('circle_id');

        foreach ($paginator->items() as $circle) {
            $circle->setRelation('currentMember', $memberships->get($circle->id));
        }

        return $this->success([
            'items' => CircleResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Example GET /api/v1/circles/{circle}/members response item:
     * {
     *   "id":"uuid",
     *   "circle_id":"uuid",
     *   "role":"member",
     *   "status":"approved",
     *   "joined_at":"2026-01-19T00:00:00Z",
     *   "left_at":null,
     *   "substitute_count":0,
     *   "role_id":"uuid-or-null",
     *   "user":{"id":"uuid","name":"Demo1 Demo1","email":"work.jaykanjariya@gmail.com"},
     *   "role_details": { "id":"uuid","name":"Circle Member","slug":"member" }
     * }
     */
    public function members(Request $request, string $id)
    {
        $circle = Circle::find($id);
        if (! $circle) {
            return $this->error('Circle not found', 404);
        }

        $membersQuery = CircleMember::query()
            ->with('user')
            ->where('circle_id', $circle->id)
            ->whereNull('deleted_at');

        if (Schema::hasTable('roles')) {
            $membersQuery->with('roleModel');
        }

        if ($status = $request->input('status')) {
            $membersQuery->where('status', $status);
        }

        if ($role = $request->input('role')) {
            $membersQuery->where('role', $role);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $membersQuery->whereHas('user', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where('display_name', 'ILIKE', $like)
                    ->orWhere('first_name', 'ILIKE', $like)
                    ->orWhere('last_name', 'ILIKE', $like)
                    ->orWhere('email', 'ILIKE', $like);
            });
        }

        $members = $membersQuery->orderBy('role')->orderBy('joined_at')->paginate((int) $request->input('per_page', 20));

        return $this->success([
            'items' => CircleMemberResource::collection($members->items()),
            'pagination' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    public function updateMember(UpdateCircleMemberRequest $request, string $circleId, string $memberId)
    {
        $authUser = $request->user();

        $circle = Circle::find($circleId);
        if (! $circle) {
            return $this->error('Circle not found', 404);
        }

        $authMembership = CircleMember::where('circle_id', $circle->id)
            ->where('user_id', $authUser->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $authMembership) {
            return $this->error('You are not a member of this circle', 403);
        }

        $adminRoles = ['founder', 'director', 'chair', 'vice_chair', 'secretary'];
        if (! in_array($authMembership->role, $adminRoles, true)) {
            return $this->error('You are not allowed to manage circle members', 403);
        }

        $member = CircleMember::with('user')
            ->where('circle_id', $circle->id)
            ->where('id', $memberId)
            ->whereNull('deleted_at')
            ->first();

        if (! $member) {
            return $this->error('Circle member not found', 404);
        }

        $data = $request->validated();

        if (isset($data['status']) && $data['status'] === 'approved' && ! $member->joined_at) {
            $member->joined_at = now();
        }

        $member->fill($data);
        $member->save();

        return $this->success(new CircleMemberResource($member), 'Circle member updated');
    }
}

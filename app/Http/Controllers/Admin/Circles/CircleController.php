<?php

namespace App\Http\Controllers\Admin\Circles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Circles\StoreCircleRequest;
use App\Http\Requests\Admin\Circles\UpdateCircleRequest;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\City;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CircleController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('q', $request->input('search'));
        $status = $request->input('status');
        $cityId = $request->input('city_id');
        $type = $request->input('type');
        $allowedCircleIds = $request->attributes->get('allowed_circle_ids');

        $query = Circle::query()->with(['founder', 'director', 'industryDirector', 'ded', 'city'])->withCount('members');

        if (is_array($allowedCircleIds)) {
            if ($allowedCircleIds === []) {
                $query->whereRaw('1=0');
            } else {
                $query->whereIn('id', $allowedCircleIds);
            }
        }

        if ($search) {
            $query->where('name', 'ILIKE', "%{$search}%");
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $circles = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $statuses = Circle::query()
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $cities = City::query()->orderBy('name')->get();

        return view('admin.circles.index', [
            'circles' => $circles,
            'statuses' => $statuses,
            'cities' => $cities,
            'types' => Circle::TYPE_OPTIONS,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'city_id' => $cityId,
                'type' => $type,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $defaultFounder = $this->defaultFounderUser();
        $countries = $this->countriesList();
        $selectedCountry = $request->input('country', $countries->first() ?? 'India');

        $cities = City::query()
            ->when($selectedCountry, fn ($query) => $query->where('country', $selectedCountry))
            ->orderBy('name')
            ->get();

        return view('admin.circles.create', [
            'circle' => new Circle(),
            'countries' => $countries,
            'selectedCountry' => $selectedCountry,
            'cities' => $cities,
            'statuses' => Circle::STATUS_OPTIONS,
            'types' => Circle::TYPE_OPTIONS,
            'defaultFounder' => $defaultFounder,
            'defaultFounderLabel' => $this->founderLabel($defaultFounder),
            'meetingModes' => Circle::MEETING_MODE_OPTIONS,
            'meetingFrequencies' => Circle::MEETING_FREQUENCY_OPTIONS,
            'allUsers' => $this->allUsers(),
        ]);
    }

    public function store(StoreCircleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['industry_tags'] = $this->normalizeIndustryTags($data['industry_tags'] ?? null);
        $data['calendar'] = $this->normalizeCalendarMeetings($request->input('calendar_meetings', []));

        if (empty($data['status'])) {
            unset($data['status']);
        }

        $circle = new Circle($data);
        $circle->save();

        return redirect()
            ->route('admin.circles.show', $circle)
            ->with('success', 'Circle created successfully.');
    }

    public function show(Request $request, Circle $circle): View
    {
        $allowedCircleIds = $request->attributes->get('allowed_circle_ids');

        if (is_array($allowedCircleIds) && ! in_array($circle->id, $allowedCircleIds, true)) {
            abort(403);
        }

        $circle->load(['city', 'founder', 'director', 'industryDirector', 'ded', 'members.user', 'members.roleRef']);

        return view('admin.circles.show', [
            'circle' => $circle,
            'allUsers' => $this->allUsers(),
            'roles' => CircleMember::roleOptions(),
        ]);
    }

    public function edit(Request $request, Circle $circle): View
    {
        $circle->load('city');

        $defaultFounder = $circle->founder ?? $this->defaultFounderUser();
        $countries = $this->countriesList();
        $selectedCountry = $request->input('country', $circle->city?->country ?? $countries->first() ?? 'India');

        $cities = City::query()
            ->when($selectedCountry, fn ($query) => $query->where('country', $selectedCountry))
            ->orderBy('name')
            ->get();

        return view('admin.circles.edit', [
            'circle' => $circle,
            'countries' => $countries,
            'selectedCountry' => $selectedCountry,
            'cities' => $cities,
            'statuses' => Circle::STATUS_OPTIONS,
            'types' => Circle::TYPE_OPTIONS,
            'defaultFounder' => $defaultFounder,
            'defaultFounderLabel' => $this->founderLabel($defaultFounder),
            'meetingModes' => Circle::MEETING_MODE_OPTIONS,
            'meetingFrequencies' => Circle::MEETING_FREQUENCY_OPTIONS,
            'allUsers' => $this->allUsers(),
        ]);
    }

    public function update(UpdateCircleRequest $request, Circle $circle): RedirectResponse
    {
        $data = $request->validated();
        $data['industry_tags'] = $this->normalizeIndustryTags($data['industry_tags'] ?? null);
        $data['calendar'] = $this->normalizeCalendarMeetings($request->input('calendar_meetings', []));

        $originalName = $circle->name;

        $circle->fill($data);

        if ($circle->name !== $originalName) {
            $circle->slug = Circle::generateUniqueSlug($circle->name, $circle->id);
        }

        $circle->save();

        return redirect()
            ->route('admin.circles.show', $circle)
            ->with('success', 'Circle updated successfully.');
    }

    public function destroy(Circle $circle): RedirectResponse
    {
        $circle->delete();

        return redirect()
            ->route('admin.circles.index')
            ->with('success', 'Circle deleted successfully.');
    }

    private function normalizeIndustryTags(null|string|array $tags): ?array
    {
        if (is_array($tags)) {
            return array_values(array_filter(array_map('trim', $tags)));
        }

        if (is_string($tags)) {
            $trimmed = array_filter(array_map('trim', explode(',', $tags)));
            return $trimmed ? array_values($trimmed) : null;
        }

        return null;
    }


    private function normalizeCalendarMeetings(mixed $meetings): ?array
    {
        if (! is_array($meetings)) {
            return null;
        }

        $normalized = [];

        foreach ($meetings as $meeting) {
            if (! is_array($meeting)) {
                continue;
            }

            $frequency = strtolower(trim((string) ($meeting['frequency'] ?? '')));
            $day = strtolower(trim((string) ($meeting['default_meet_day'] ?? '')));
            $time = trim((string) ($meeting['default_meet_time'] ?? ''));
            $monthlyRule = strtolower(trim((string) ($meeting['monthly_rule'] ?? '')));

            if ($frequency === '' && $day === '' && $time === '' && $monthlyRule === '') {
                continue;
            }

            if (! in_array($frequency, ['weekly', 'monthly', 'quarterly'], true)) {
                continue;
            }

            if ($day === '' || $time === '') {
                continue;
            }

            $row = [
                'frequency' => $frequency,
                'default_meet_day' => $day,
                'default_meet_time' => $time,
            ];

            if (in_array($frequency, ['monthly', 'quarterly'], true) && $monthlyRule !== '') {
                $row['monthly_rule'] = $monthlyRule;
            }

            $normalized[] = $row;
        }

        if ($normalized === []) {
            return null;
        }

        $payload = [
            'timezone' => 'Asia/Kolkata',
            'meetings' => array_values($normalized),
        ];

        $first = $payload['meetings'][0];
        $payload['frequency'] = $first['frequency'];
        $payload['default_meet_day'] = $first['default_meet_day'];
        $payload['default_meet_time'] = $first['default_meet_time'];

        if (isset($first['monthly_rule'])) {
            $payload['monthly_rule'] = $first['monthly_rule'];
        }

        return $payload;
    }

    private function countriesList()
    {
        $countries = City::query()
            ->select('country')
            ->whereNotNull('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        if (!$countries->contains('India')) {
            $countries->prepend('India');
        }

        return $countries->unique()->values();
    }

    private function defaultFounderUser(): ?User
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return null;
        }

        return User::query()
            ->where('email', $admin->email)
            ->with(['circleMembers' => function ($query) {
                $query->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }])
            ->first();
    }

    private function allUsers()
    {
        $columns = ['id', 'display_name', 'first_name', 'last_name', 'email', 'city'];

        foreach (['company_name', 'business_name'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $columns[] = $column;
            }
        }

        return User::query()
            ->whereNull('deleted_at')
            ->with(['circleMembers' => function ($query) {
                $query->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }])
            ->orderByRaw("COALESCE(NULLIF(display_name,''), NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)),''), email) ASC")
            ->limit(2000)
            ->get($columns);
    }

    private function founderLabel(?User $user): string
    {
        if (! $user) {
            return '';
        }

        $peerName = trim((string) ($user->display_name ?? ''));

        if ($peerName === '') {
            $peerName = trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? '')));
        }

        if ($peerName === '') {
            $peerName = '—';
        }

        $company = 'No Company';

        if (isset($user->company_name) && trim((string) $user->company_name) !== '') {
            $company = trim((string) $user->company_name);
        } elseif (isset($user->business_name) && trim((string) $user->business_name) !== '') {
            $company = trim((string) $user->business_name);
        }

        $city = (isset($user->city) && trim((string) $user->city) !== '')
            ? trim((string) $user->city)
            : 'No City';

        $circle = (string) (optional($user->circleMembers->first()?->circle)->name ?? 'No Circle');
        $circle = trim($circle) !== '' ? trim($circle) : 'No Circle';

        return "{$peerName} — {$company} — {$city} — {$circle}";
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Impacts\StoreImpactRequest;
use App\Http\Requests\Impacts\ReviewImpactRequest;
use App\Models\Impact;
use App\Models\User;
use App\Services\Impacts\ImpactService;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ImpactsController extends Controller
{
    public function __construct(private readonly ImpactService $impactService)
    {
    }

    public function index(Request $request): View
    {
        $this->ensureGlobalAdmin();

        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        if (! in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
            $status = 'all';
        }

        $impacts = Impact::query()
            ->with([
                'user:id,display_name,first_name,last_name,email,company,company_name,business_name',
                'impactedPeer:id,display_name,first_name,last_name,email,company,company_name,business_name',
                'approvedBy:id,name',
            ])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $term = "%{$search}%";

                $query->where(function ($subQuery) use ($term) {
                    $subQuery->where('action', 'ILIKE', $term)
                        ->orWhere('story_to_share', 'ILIKE', $term)
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery->where('display_name', 'ILIKE', $term)
                                ->orWhere('first_name', 'ILIKE', $term)
                                ->orWhere('last_name', 'ILIKE', $term)
                                ->orWhere('email', 'ILIKE', $term);
                        })
                        ->orWhereHas('impactedPeer', function ($userQuery) use ($term) {
                            $userQuery->where('display_name', 'ILIKE', $term)
                                ->orWhere('first_name', 'ILIKE', $term)
                                ->orWhere('last_name', 'ILIKE', $term)
                                ->orWhere('email', 'ILIKE', $term);
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $impactActions = array_values((array) config('impact.actions', []));
        $peers = User::query()
            ->select(['id', 'display_name', 'first_name', 'last_name', 'email', 'company', 'company_name', 'business_name'])
            ->orderByRaw("COALESCE(NULLIF(display_name, ''), NULLIF(TRIM(CONCAT(first_name, ' ', last_name)), ''), email) ASC")
            ->get();

        return view('admin.impacts.index', [
            'impacts' => $impacts,
            'impactActions' => $impactActions,
            'peers' => $peers,
            'filters' => [
                'status' => $status,
                'q' => $search,
            ],
        ]);
    }

    public function store(StoreImpactRequest $request): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $data = $request->validated();
        $submittedBy = User::query()->findOrFail($data['impacted_peer_id']);

        $this->impactService->submitImpact($submittedBy, $data);

        return redirect()
            ->route('admin.impacts.index')
            ->with('success', 'Impact created successfully.');
    }

    public function pending(): View
    {
        $this->ensureGlobalAdmin();

        $impacts = Impact::query()
            ->with([
                'user:id,display_name,first_name,last_name,email',
                'impactedPeer:id,display_name,first_name,last_name,email',
            ])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.impacts.pending', compact('impacts'));
    }

    public function posts(): RedirectResponse
    {
        return redirect()->route('admin.impacts.index', ['status' => 'approved']);
    }

    public function show(string $id): View
    {
        $this->ensureGlobalAdmin();

        $impact = Impact::query()
            ->with(['user:id,display_name,first_name,last_name,email,phone', 'impactedPeer:id,display_name,first_name,last_name,email,phone'])
            ->findOrFail($id);

        return view('admin.impacts.show', compact('impact'));
    }

    public function approve(string $id, ReviewImpactRequest $request): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $this->impactService->approveImpact($id, (string) Auth::guard('admin')->id(), $request->validated('review_remarks'));

        return redirect()->back()->with('success', 'Impact approved successfully.');
    }

    public function reject(string $id, ReviewImpactRequest $request): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $this->impactService->rejectImpact($id, (string) Auth::guard('admin')->id(), $request->validated('review_remarks'));

        return redirect()->back()->with('success', 'Impact rejected successfully.');
    }

    private function ensureGlobalAdmin(): void
    {
        if (! AdminAccess::isGlobalAdmin(Auth::guard('admin')->user())) {
            abort(403);
        }
    }
}

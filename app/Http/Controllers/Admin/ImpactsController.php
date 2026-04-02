<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Impacts\ReviewImpactRequest;
use App\Models\Impact;
use App\Services\Impacts\ImpactService;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ImpactsController extends Controller
{
    public function __construct(private readonly ImpactService $impactService)
    {
    }

    public function pending(): View
    {
        $this->ensureGlobalAdmin();

        $impacts = Impact::query()
            ->with(['user:id,display_name,first_name,last_name', 'impactedPeer:id,display_name,first_name,last_name'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.impacts.pending', compact('impacts'));
    }

    public function posts(): View
    {
        $this->ensureGlobalAdmin();

        $impacts = Impact::query()
            ->with(['user:id,display_name,first_name,last_name', 'impactedPeer:id,display_name,first_name,last_name'])
            ->where('status', 'approved')
            ->orderByDesc('timeline_posted_at')
            ->paginate(25);

        return view('admin.impacts.posts', compact('impacts'));
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

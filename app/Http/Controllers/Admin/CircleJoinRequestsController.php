<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Services\Circles\CircleJoinRequestService;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CircleJoinRequestsController extends Controller
{
    public function __construct(private readonly CircleJoinRequestService $service)
    {
    }

    public function index(Request $request): View
    {
        $admin = Auth::guard('admin')->user();
        $actor = AdminAccess::resolveAppUser($admin);

        $query = CircleJoinRequest::query()->with(['user', 'circle', 'category:id,category_name,sector', 'cdApprovedBy', 'cdRejectedBy', 'idApprovedBy', 'idRejectedBy']);
        $query->visibleToAdminUser($admin);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->whereHas('user', fn ($q) => $q->where('display_name', 'ILIKE', $like)
                ->orWhere('email', 'ILIKE', $like)
                ->orWhere('phone', 'ILIKE', $like)
                ->orWhere('company_name', 'ILIKE', $like)
                ->orWhere('city', 'ILIKE', $like));
        }

        $query->when($request->query('circle_id'), fn ($q, $v) => $q->where('circle_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('requested_at', '>=', $v))
            ->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('requested_at', '<=', $v));

        $requests = $query->latest('created_at')->paginate(25)->appends($request->query());

        $requests->getCollection()->transform(function (CircleJoinRequest $joinRequest) use ($admin, $actor) {
            $joinRequest->setAttribute('can_approve_cd', $this->canApproveCd($admin, $actor, $joinRequest));
            $joinRequest->setAttribute('can_reject_cd', $this->canApproveCd($admin, $actor, $joinRequest));
            $joinRequest->setAttribute('can_approve_id', $this->canApproveId($admin, $actor, $joinRequest));
            $joinRequest->setAttribute('can_reject_id', $this->canApproveId($admin, $actor, $joinRequest));

            return $joinRequest;
        });

        return view('admin.circle_join_requests.index', [
            'requests' => $requests,
            'circles' => Circle::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'circle_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function show(string $id): View
    {
        $admin = Auth::guard('admin')->user();
        $actor = AdminAccess::resolveAppUser($admin);

        $record = CircleJoinRequest::query()->with(['user', 'circle', 'category:id,category_name,sector', 'cdApprovedBy', 'cdRejectedBy', 'idApprovedBy', 'idRejectedBy'])->findOrFail($id);
        abort_unless($this->canAccessRecord($admin, $actor, $record), 403);

        return view('admin.circle_join_requests.show', [
            'record' => $record,
            'canApproveCd' => $this->canApproveCd($admin, $actor, $record),
            'canApproveId' => $this->canApproveId($admin, $actor, $record),
        ]);
    }

    public function approveCd(string $id): RedirectResponse
    {
        return $this->runAction($id, function (CircleJoinRequest $record, $admin, $actor): void {
            abort_unless($this->canApproveCd($admin, $actor, $record), 403);
            $this->service->approveByCd($record, $actor);
        });
    }

    public function rejectCd(Request $request, string $id): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->runAction($id, function (CircleJoinRequest $record, $admin, $actor) use ($request): void {
            abort_unless($this->canApproveCd($admin, $actor, $record), 403);
            $this->service->rejectByCd($record, $actor, (string) $request->input('reason'));
        });
    }

    public function approveId(string $id): RedirectResponse
    {
        return $this->runAction($id, function (CircleJoinRequest $record, $admin, $actor): void {
            abort_unless($this->canApproveId($admin, $actor, $record), 403);
            $this->service->approveById($record, $actor);
        });
    }

    public function rejectId(Request $request, string $id): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->runAction($id, function (CircleJoinRequest $record, $admin, $actor) use ($request): void {
            abort_unless($this->canApproveId($admin, $actor, $record), 403);
            $this->service->rejectById($record, $actor, (string) $request->input('reason'));
        });
    }

    private function runAction(string $id, callable $callback): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        $actor = AdminAccess::resolveAppUser($admin);
        abort_unless($admin !== null && $actor !== null, 403);

        try {
            $record = CircleJoinRequest::query()->with('circle')->findOrFail($id);
            abort_unless($this->canAccessRecord($admin, $actor, $record), 403);
            $callback($record, $admin, $actor);

            return back()->with('success', 'Action completed successfully.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
    }

    private function canAccessRecord($admin, $actor, CircleJoinRequest $record): bool
    {
        if (AdminAccess::isGlobalAdmin($admin)) {
            return true;
        }

        $allowedCircleIds = AdminAccess::allowedCircleIds($admin);

        if (! in_array($record->circle_id, $allowedCircleIds, true)) {
            return false;
        }

        if (! $record->relationLoaded('circle')) {
            $record->load('circle');
        }

        return (string) $record->circle?->director_user_id === (string) $actor->id
            || (string) $record->circle?->industry_director_user_id === (string) $actor->id;
    }

    private function canApproveCd($admin, $actor, CircleJoinRequest $record): bool
    {
        if (! $this->canAccessRecord($admin, $actor, $record)) {
            return false;
        }

        if ($record->status !== CircleJoinRequest::STATUS_PENDING_CD_APPROVAL) {
            return false;
        }

        if (AdminAccess::isGlobalAdmin($admin)) {
            return true;
        }

        return (string) $record->circle?->director_user_id === (string) $actor->id;
    }

    private function canApproveId($admin, $actor, CircleJoinRequest $record): bool
    {
        if (! $this->canAccessRecord($admin, $actor, $record)) {
            return false;
        }

        if ($record->status !== CircleJoinRequest::STATUS_PENDING_ID_APPROVAL) {
            return false;
        }

        if (AdminAccess::isGlobalAdmin($admin)) {
            return true;
        }

        return (string) $record->circle?->industry_director_user_id === (string) $actor->id;
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\CircleJoinRequests\AdminListCircleJoinRequests;
use App\Http\Requests\Api\CircleJoinRequests\RejectCircleJoinRequest;
use App\Models\CircleJoinRequest;
use App\Models\Role;
use App\Services\Circles\CircleJoinRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CircleJoinRequestAdminController extends BaseApiController
{
    public function __construct(private readonly CircleJoinRequestService $service)
    {
    }

    public function index(AdminListCircleJoinRequests $request): JsonResponse
    {
        $this->ensureCanView($request->user());

        $query = CircleJoinRequest::query()->with(['user', 'circle', 'category:id,category_name,sector', 'cdApprovedBy', 'cdRejectedBy', 'idApprovedBy', 'idRejectedBy']);
        $this->applyUserScope($query, $request->user());

        $validated = $request->validated();

        if (! empty($validated['search'])) {
            $search = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $validated['search']) . '%';
            $query->whereHas('user', fn ($q) => $q->where('display_name', 'ILIKE', $search)
                ->orWhere('first_name', 'ILIKE', $search)
                ->orWhere('last_name', 'ILIKE', $search)
                ->orWhere('email', 'ILIKE', $search)
                ->orWhere('phone', 'ILIKE', $search)
                ->orWhere('company_name', 'ILIKE', $search))
                ->orWhereHas('circle', fn ($q) => $q->where('name', 'ILIKE', $search));
        }

        $query->when($validated['circle_id'] ?? null, fn ($q, $v) => $q->where('circle_id', $v))
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($validated['date_from'] ?? null, fn ($q, $v) => $q->whereDate('requested_at', '>=', $v))
            ->when($validated['date_to'] ?? null, fn ($q, $v) => $q->whereDate('requested_at', '<=', $v));

        $items = $query->latest('created_at')->paginate(20);

        return $this->success([
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->ensureCanView($request->user());

        $record = CircleJoinRequest::query()->with(['user', 'circle', 'category:id,category_name,sector', 'cdApprovedBy', 'cdRejectedBy', 'idApprovedBy', 'idRejectedBy'])->findOrFail($id);
        $this->ensureCanAccessRecord($request->user(), $record);

        return $this->success($record);
    }

    public function approveCd(Request $request, string $id): JsonResponse
    {
        $record = CircleJoinRequest::query()->findOrFail($id);
        $this->ensureCanApproveCd($request->user(), $record);

        try {
            return $this->success($this->service->approveByCd($record, $request->user()), 'Circle Director approval completed.');
        } catch (ValidationException $exception) {
            return $this->error('Validation failed.', 422, $exception->errors());
        }
    }

    public function rejectCd(RejectCircleJoinRequest $request, string $id): JsonResponse
    {
        $record = CircleJoinRequest::query()->findOrFail($id);
        $this->ensureCanApproveCd($request->user(), $record);

        try {
            return $this->success($this->service->rejectByCd($record, $request->user(), $request->validated('reason')), 'Circle Director rejection completed.');
        } catch (ValidationException $exception) {
            return $this->error('Validation failed.', 422, $exception->errors());
        }
    }

    public function approveId(Request $request, string $id): JsonResponse
    {
        $record = CircleJoinRequest::query()->findOrFail($id);
        $this->ensureCanApproveId($request->user(), $record);

        try {
            return $this->success($this->service->approveById($record, $request->user()), 'Industry Director approval completed.');
        } catch (ValidationException $exception) {
            return $this->error('Validation failed.', 422, $exception->errors());
        }
    }

    public function rejectId(RejectCircleJoinRequest $request, string $id): JsonResponse
    {
        $record = CircleJoinRequest::query()->findOrFail($id);
        $this->ensureCanApproveId($request->user(), $record);

        try {
            return $this->success($this->service->rejectById($record, $request->user(), $request->validated('reason')), 'Industry Director rejection completed.');
        } catch (ValidationException $exception) {
            return $this->error('Validation failed.', 422, $exception->errors());
        }
    }

    private function ensureCanView($user): void
    {
        abort_unless($user !== null && $this->hasAnyRole($user, ['global_admin', 'circle_leader', 'director', 'industry_director']), 403);
    }

    private function ensureCanApproveCd($user, CircleJoinRequest $record): void
    {
        $this->ensureCanAccessRecord($user, $record);
        abort_unless($this->hasAnyRole($user, ['global_admin', 'circle_leader', 'director']) || (string) $record->circle?->director_user_id === (string) $user->id, 403);
    }

    private function ensureCanApproveId($user, CircleJoinRequest $record): void
    {
        $this->ensureCanAccessRecord($user, $record);
        abort_unless($this->hasAnyRole($user, ['global_admin', 'industry_director']) || (string) $record->circle?->industry_director_user_id === (string) $user->id, 403);
    }

    private function ensureCanAccessRecord($user, CircleJoinRequest $record): void
    {
        if ($this->hasAnyRole($user, ['global_admin'])) {
            return;
        }

        if ((string) $record->circle?->director_user_id === (string) $user->id || (string) $record->circle?->industry_director_user_id === (string) $user->id) {
            return;
        }

        abort(403);
    }

    private function applyUserScope($query, $user): void
    {
        if ($this->hasAnyRole($user, ['global_admin'])) {
            return;
        }

        $query->where(function ($q) use ($user) {
            $q->whereHas('circle', fn ($cq) => $cq->where('director_user_id', $user->id)->orWhere('industry_director_user_id', $user->id));
        });
    }

    private function hasAnyRole($user, array $keys): bool
    {
        $roleIds = Role::query()->whereIn('key', $keys)->pluck('id');

        return $user->roles()->whereIn('roles.id', $roleIds)->exists();
    }
}

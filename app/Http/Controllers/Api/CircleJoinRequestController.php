<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CircleJoinRequests\ListMyCircleJoinRequests;
use App\Http\Requests\Api\CircleJoinRequests\StoreCircleJoinRequest;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Services\Circles\CircleJoinRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CircleJoinRequestController extends BaseApiController
{
    public function __construct(private readonly CircleJoinRequestService $service)
    {
    }

    public function store(StoreCircleJoinRequest $request): JsonResponse
    {
        $circle = Circle::query()->where('id', $request->validated('circle_id'))->firstOrFail();

        if ($circle->status !== 'active') {
            return $this->error('Circle is not active.', 422);
        }

        try {
            $record = $this->service->submitRequest(
                $request->user(),
                $circle,
                $request->validated('reason_for_joining'),
                [
                    'level1_category_id' => $request->validated('level1_category_id'),
                    'level2_category_id' => $request->validated('level2_category_id'),
                    'level3_category_id' => $request->validated('level3_category_id'),
                    'level4_category_id' => $request->validated('level4_category_id'),
                ]
            );

            $record->load(['circle:id,name', 'user:id,display_name,email,phone,company_name,city']);
            if (Schema::hasColumns('circle_join_requests', ['level1_category_id', 'level2_category_id', 'level3_category_id', 'level4_category_id'])) {
                $record->load([
                    'level1Category:id,name',
                    'level2Category:id,name',
                    'level3Category:id,name',
                    'level4Category:id,name',
                ]);
            }

            return $this->success($this->transformJoinRequest($record), 'Circle join request submitted successfully.', 201);
        } catch (ValidationException $exception) {
            return $this->error('Validation failed.', 422, $exception->errors());
        }
    }

    public function myRequests(ListMyCircleJoinRequests $request): JsonResponse
    {
        $status = $request->validated('status');

        $items = CircleJoinRequest::query()
            ->where('user_id', $request->user()->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['circle:id,name'])
            ->latest('created_at')
            ->paginate(20);

        return $this->success([
            'items' => collect($items->items())->map(fn (CircleJoinRequest $joinRequest) => $this->transformJoinRequest($joinRequest))->values(),
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
        $record = CircleJoinRequest::query()->with(['circle', 'user', 'cdApprovedBy', 'cdRejectedBy', 'idApprovedBy', 'idRejectedBy'])->findOrFail($id);

        if ((string) $record->user_id !== (string) $request->user()->id) {
            return $this->error('Forbidden.', 403);
        }

        return $this->success($this->transformJoinRequest($record));
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $record = CircleJoinRequest::query()->findOrFail($id);

        try {
            $updated = $this->service->cancelByUser($record, $request->user());

            return $this->success($this->transformJoinRequest($updated), 'Circle join request cancelled successfully.');
        } catch (ValidationException $exception) {
            return $this->error('Validation failed.', 422, $exception->errors());
        }
    }

    private function transformJoinRequest(CircleJoinRequest $request): array
    {
        $status = (string) $request->status;
        $isPaid = in_array($status, [CircleJoinRequest::STATUS_PAID, CircleJoinRequest::STATUS_CIRCLE_MEMBER], true) || $request->fee_paid_at !== null;

        $payload = array_merge($request->toArray(), [
            'level1_category_id' => $this->resolveCategoryIdFromJoinRequest($request, 'level1_category_id'),
            'level2_category_id' => $this->resolveCategoryIdFromJoinRequest($request, 'level2_category_id'),
            'level3_category_id' => $this->resolveCategoryIdFromJoinRequest($request, 'level3_category_id'),
            'level4_category_id' => $this->resolveCategoryIdFromJoinRequest($request, 'level4_category_id'),
            'status_label' => $isPaid ? 'Paid' : $this->statusLabel($status),
            'payment_status' => $isPaid ? 'paid' : 'unpaid',
            'display_status' => $isPaid ? 'Paid' : $this->statusLabel($status),
        ]);

        if ($request->relationLoaded('level1Category') && $request->level1Category) {
            $payload['level1_category'] = ['id' => $request->level1Category->id, 'name' => $request->level1Category->name];
        }

        if ($request->relationLoaded('level2Category') && $request->level2Category) {
            $payload['level2_category'] = ['id' => $request->level2Category->id, 'name' => $request->level2Category->name];
        }

        if ($request->relationLoaded('level3Category') && $request->level3Category) {
            $payload['level3_category'] = ['id' => $request->level3Category->id, 'name' => $request->level3Category->name];
        }

        if ($request->relationLoaded('level4Category') && $request->level4Category) {
            $payload['level4_category'] = ['id' => $request->level4Category->id, 'name' => $request->level4Category->name];
        }

        return $payload;
    }

    private function resolveCategoryIdFromJoinRequest(CircleJoinRequest $request, string $key): ?int
    {
        $value = $request->getAttribute($key);
        if ($value !== null) {
            return (int) $value;
        }

        $notes = $request->notes;
        $notesSelection = is_array($notes) ? ($notes['category_selection'] ?? null) : null;

        if (! is_array($notesSelection) || ! array_key_exists($key, $notesSelection) || $notesSelection[$key] === null) {
            return null;
        }

        return (int) $notesSelection[$key];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            CircleJoinRequest::STATUS_PENDING_CD_APPROVAL => 'Pending for CD Approval',
            CircleJoinRequest::STATUS_PENDING_ID_APPROVAL => 'Pending for ID Approval',
            CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE => 'Pending for Circle Fee',
            CircleJoinRequest::STATUS_CIRCLE_MEMBER => 'Circle Member',
            CircleJoinRequest::STATUS_PAID => 'Paid',
            CircleJoinRequest::STATUS_REJECTED_BY_CD => 'Rejected by CD',
            CircleJoinRequest::STATUS_REJECTED_BY_ID => 'Rejected by ID',
            CircleJoinRequest::STATUS_CANCELLED => 'Cancelled',
            default => $status,
        };
    }
}

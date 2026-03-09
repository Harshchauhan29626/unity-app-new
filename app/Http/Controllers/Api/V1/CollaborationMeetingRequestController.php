<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCollaborationMeetingRequestStatusRequest;
use App\Http\Resources\CollaborationMeetingRequestResource;
use App\Models\CollaborationPostMeetingRequest;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaborationMeetingRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = CollaborationPostMeetingRequest::query()
            ->with(['fromUser:id,display_name,city', 'toUser:id,display_name,city'])
            ->where('to_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'status' => true,
            'message' => 'Meeting requests fetched successfully.',
            'data' => CollaborationMeetingRequestResource::collection($items),
        ]);
    }

    public function updateStatus(UpdateCollaborationMeetingRequestStatusRequest $request, string $id, NotifyUserService $notifyUserService): JsonResponse
    {
        $meeting = CollaborationPostMeetingRequest::query()->with(['fromUser', 'toUser', 'post'])->where('id', $id)->firstOrFail();
        $authUser = $request->user();
        $nextStatus = $request->validated('status');

        if ($meeting->status !== CollaborationPostMeetingRequest::STATUS_PENDING && $nextStatus !== 'cancelled') {
            return response()->json(['status' => false, 'message' => 'Only pending requests can be accepted or rejected.', 'data' => null], 422);
        }

        if (in_array($nextStatus, ['accepted', 'rejected'], true)) {
            abort_unless((string) $meeting->to_user_id === (string) $authUser->id, 403, 'Only receiver can accept/reject.');
        }

        if ($nextStatus === 'cancelled') {
            abort_unless(in_array((string) $authUser->id, [(string) $meeting->from_user_id, (string) $meeting->to_user_id], true), 403, 'Not allowed to cancel.');
        }

        $meeting->update(['status' => $nextStatus]);

        if ($nextStatus === 'accepted') {
            $notifyUserService->notifyUser(
                $meeting->fromUser,
                $meeting->toUser,
                'collaboration_meeting_accepted',
                [
                    'title' => 'Meeting request accepted',
                    'body' => $meeting->toUser->display_name . ' accepted your meeting request.',
                    'post_id' => $meeting->post_id,
                    'post_title' => $meeting->post?->title,
                    'meeting_request_id' => $meeting->id,
                ],
                $meeting
            );
        }

        if ($nextStatus === 'rejected') {
            $notifyUserService->notifyUser(
                $meeting->fromUser,
                $meeting->toUser,
                'collaboration_meeting_rejected',
                [
                    'title' => 'Meeting request rejected',
                    'body' => $meeting->toUser->display_name . ' rejected your meeting request.',
                    'post_id' => $meeting->post_id,
                    'post_title' => $meeting->post?->title,
                    'meeting_request_id' => $meeting->id,
                ],
                $meeting
            );
        }

        $meeting->load(['fromUser:id,display_name,city', 'toUser:id,display_name,city']);

        return response()->json(['status' => true, 'message' => 'Meeting request status updated successfully.', 'data' => new CollaborationMeetingRequestResource($meeting)]);
    }
}

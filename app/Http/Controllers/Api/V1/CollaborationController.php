<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCollaborationInterestRequest;
use App\Http\Requests\Api\V1\StoreCollaborationPostRequest;
use App\Http\Requests\Api\V1\StoreCollaborationMeetingRequestRequest;
use App\Http\Requests\Api\V1\UpdateCollaborationPostRequest;
use App\Http\Resources\CollaborationMeetingRequestResource;
use App\Http\Resources\CollaborationPostListResource;
use App\Http\Resources\CollaborationPostResource;
use App\Models\CollaborationPost;
use App\Models\CollaborationPostInterest;
use App\Models\CollaborationPostMeetingRequest;
use App\Models\User;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaborationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $query = CollaborationPost::query()
            ->with(['user:id,first_name,last_name,display_name,city,membership_status,membership_expiry,profile_photo_file_id,profile_photo_url', 'industry.parent'])
            ->withCount(['interests', 'meetingRequests']);

        if (($request->query('status') ?? 'active') === 'active') {
            $query->where('status', CollaborationPost::STATUS_ACTIVE)
                ->where('expires_at', '>=', now());
        } elseif ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        foreach (['collaboration_type', 'industry_id', 'scope', 'business_stage', 'urgency'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter));
            }
        }

        if ($request->filled('city')) {
            $city = $request->string('city');
            $query->whereHas('user', fn (Builder $q) => $q->where('city', 'ILIKE', "%{$city}%"));
        }

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('title', 'ILIKE', "%{$q}%");
        }

        if ($authUser) {
            $query->withExists(['interests as is_interested_by_me' => fn ($q) => $q->where('from_user_id', $authUser->id)]);
        }

        $query->where('status', '!=', CollaborationPost::STATUS_DELETED)
            ->orderByRaw("CASE WHEN users.membership_status IN ('visitor','free_peer','suspended') THEN 0 ELSE 1 END DESC")
            ->join('users', 'users.id', '=', 'collaboration_posts.user_id')
            ->select('collaboration_posts.*')
            ->orderByDesc('posted_at');

        $posts = $query->paginate(15);

        return response()->json([
            'status' => true,
            'message' => 'Collaboration posts fetched successfully.',
            'data' => CollaborationPostListResource::collection($posts),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $query = CollaborationPost::query()
            ->with(['user:id,first_name,last_name,display_name,city,membership_status,membership_expiry,profile_photo_file_id,profile_photo_url', 'industry.parent'])
            ->withCount(['interests', 'meetingRequests']);

        if ($request->user()) {
            $query->withExists(['interests as is_interested_by_me' => fn ($q) => $q->where('from_user_id', $request->user()->id)]);
        }

        $post = $query->where('id', $id)->where('status', '!=', CollaborationPost::STATUS_DELETED)->firstOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Collaboration post fetched successfully.',
            'data' => new CollaborationPostResource($post),
        ]);
    }

    public function store(StoreCollaborationPostRequest $request): JsonResponse
    {
        $user = $request->user();

        $post = CollaborationPost::query()->create([
            ...$request->validated(),
            'user_id' => $user->id,
            'posted_at' => now(),
            'expires_at' => now()->addDays(60),
            'status' => CollaborationPost::STATUS_ACTIVE,
        ]);

        $post->load(['user', 'industry.parent'])->loadCount(['interests', 'meetingRequests']);

        return response()->json([
            'status' => true,
            'message' => 'Collaboration post created successfully.',
            'data' => new CollaborationPostResource($post),
        ], 201);
    }

    public function update(UpdateCollaborationPostRequest $request, string $id): JsonResponse
    {
        $post = CollaborationPost::query()->where('id', $id)->firstOrFail();
        abort_unless((string) $post->user_id === (string) $request->user()->id, 403, 'You are not allowed to update this post.');

        if ($post->status === CollaborationPost::STATUS_EXPIRED || ($post->expires_at && $post->expires_at->isPast())) {
            return response()->json(['status' => false, 'message' => 'Expired posts cannot be edited. Please renew first.', 'data' => null], 422);
        }

        $post->update($request->validated());
        $post->load(['user', 'industry.parent'])->loadCount(['interests', 'meetingRequests']);

        return response()->json([
            'status' => true,
            'message' => 'Collaboration post updated successfully.',
            'data' => new CollaborationPostResource($post),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $post = CollaborationPost::query()->where('id', $id)->firstOrFail();
        abort_unless((string) $post->user_id === (string) $request->user()->id, 403, 'You are not allowed to delete this post.');

        $post->update(['status' => CollaborationPost::STATUS_DELETED]);

        return response()->json(['status' => true, 'message' => 'Collaboration post deleted successfully.', 'data' => null]);
    }

    public function renew(Request $request, string $id): JsonResponse
    {
        $post = CollaborationPost::query()->where('id', $id)->firstOrFail();
        abort_unless((string) $post->user_id === (string) $request->user()->id, 403, 'You are not allowed to renew this post.');

        $post->update([
            'status' => CollaborationPost::STATUS_ACTIVE,
            'renewed_at' => now(),
            'expires_at' => now()->addDays(60),
        ]);

        $post->load(['user', 'industry.parent'])->loadCount(['interests', 'meetingRequests']);

        return response()->json(['status' => true, 'message' => 'Collaboration post renewed successfully.', 'data' => new CollaborationPostResource($post)]);
    }

    public function myPosts(Request $request): JsonResponse
    {
        $posts = CollaborationPost::query()
            ->with(['user:id,first_name,last_name,display_name,city,membership_status,membership_expiry,profile_photo_file_id,profile_photo_url', 'industry.parent'])
            ->withCount(['interests', 'meetingRequests'])
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', CollaborationPost::STATUS_DELETED)
            ->orderByDesc('posted_at')
            ->paginate(15);

        return response()->json(['status' => true, 'message' => 'My collaboration posts fetched successfully.', 'data' => CollaborationPostListResource::collection($posts)]);
    }

    public function interest(StoreCollaborationInterestRequest $request, string $postId, NotifyUserService $notifyUserService): JsonResponse
    {
        $post = CollaborationPost::query()->with('user')->where('id', $postId)->firstOrFail();
        $authUser = $request->user();

        $interest = CollaborationPostInterest::query()->firstOrCreate(
            ['post_id' => $post->id, 'from_user_id' => $authUser->id],
            ['to_user_id' => $post->user_id, 'message' => $request->validated('message')]
        );

        if ($interest->wasRecentlyCreated) {
            $notifyUserService->notifyUser(
                $post->user,
                $authUser,
                'collaboration_interest_received',
                [
                    'title' => 'New collaboration interest',
                    'body' => $authUser->display_name . ' showed interest in your collaboration post.',
                    'post_id' => $post->id,
                    'post_title' => $post->title,
                    'from_user' => [
                        'id' => $authUser->id,
                        'name' => $authUser->display_name,
                        'city' => $authUser->city,
                        'profile_photo_url' => $authUser->profile_photo_file_id ? url('/api/v1/files/' . $authUser->profile_photo_file_id) : $authUser->profile_photo_url,
                    ],
                ],
                $post
            );
        }

        return response()->json(['status' => true, 'message' => 'Interest submitted successfully.', 'data' => ['id' => $interest->id]]);
    }

    public function removeInterest(Request $request, string $postId): JsonResponse
    {
        CollaborationPostInterest::query()->where('post_id', $postId)->where('from_user_id', $request->user()->id)->delete();

        return response()->json(['status' => true, 'message' => 'Interest removed successfully.', 'data' => null]);
    }

    public function storeMeetingRequest(StoreCollaborationMeetingRequestRequest $request, string $postId, NotifyUserService $notifyUserService): JsonResponse
    {
        $post = CollaborationPost::query()->with('user')->where('id', $postId)->firstOrFail();
        $authUser = $request->user();

        $meeting = CollaborationPostMeetingRequest::query()->create([
            'post_id' => $post->id,
            'from_user_id' => $authUser->id,
            'to_user_id' => $post->user_id,
            ...$request->validated(),
            'status' => CollaborationPostMeetingRequest::STATUS_PENDING,
        ]);

        $notifyUserService->notifyUser(
            $post->user,
            $authUser,
            'collaboration_meeting_requested',
            [
                'title' => 'New collaboration meeting request',
                'body' => $authUser->display_name . ' requested a collaboration meeting.',
                'post_id' => $post->id,
                'post_title' => $post->title,
                'meeting_request_id' => $meeting->id,
            ],
            $meeting
        );

        $meeting->load(['fromUser:id,display_name,city', 'toUser:id,display_name,city']);

        return response()->json(['status' => true, 'message' => 'Meeting request submitted successfully.', 'data' => new CollaborationMeetingRequestResource($meeting)], 201);
    }
}

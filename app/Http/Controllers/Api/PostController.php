<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Post\StorePostCommentRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Resources\PostCommentResource;
use App\Models\CircleMember;
use App\Models\Connection;
use App\Models\File;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Services\AdFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends BaseApiController
{
    public function feed(Request $request, AdFeedService $adFeedService)
    {
        $user = $request->user();
        $perPage = max(1, min((int) $request->integer('per_page', 20), 50));

        $query = Post::query()
            ->with([
                'author:id,display_name,first_name,last_name,profile_photo_file_id',
            ])
            ->withCount(['likes', 'comments', 'saves'])
            ->withExists([
                'likes as is_liked_by_me' => fn ($query) => $query->where('user_id', $user->id),
                'saves as is_saved_by_me' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->orderByDesc('created_at');

        $query->where('visibility', 'public');
        $query->where('posts.is_deleted', false)
            ->whereNull('posts.deleted_at');

        $posts = $query->paginate($perPage)->appends($request->query());

        $postItems = $posts->getCollection()->map(function (Post $post) {
            return $this->formatPostFeedItem($post);
        });

        $timelineAds = $adFeedService->timelineAds();
        $items = $adFeedService->mergeTimelineFeed($postItems, $timelineAds);

        return $this->success([
            'items' => $items,
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    private function formatPostFeedItem(Post $post): array
    {
        return [
            'type'              => 'post',
            'id'                => $post->id,
            'content_text'      => $post->content_text,
            'media'             => $post->media ?? [],
            'tags'              => $post->tags ?? [],
            'visibility'        => $post->visibility,
            'moderation_status' => $post->moderation_status,
            'author'            => $post->relationLoaded('author') && $post->author ? [
                'id'               => $post->author->id,
                'display_name'     => $post->author->display_name,
                'first_name'       => $post->author->first_name,
                'last_name'        => $post->author->last_name,
                'profile_photo_url'=> $post->author->profile_photo_url,
            ] : null,
            'circle'            => $post->relationLoaded('circle') && $post->circle ? [
                'id'   => $post->circle->id,
                'name' => $post->circle->name,
            ] : null,
            'likes_count'       => isset($post->likes_count) ? (int) $post->likes_count : 0,
            'comments_count'    => isset($post->comments_count) ? (int) $post->comments_count : 0,
            'is_liked_by_me'    => (bool) ($post->is_liked_by_me ?? false),
            'saves_count'       => isset($post->saves_count) ? (int) $post->saves_count : 0,
            'is_saved'          => (bool) ($post->is_saved_by_me ?? false),
            'created_at'        => $post->created_at,
            'updated_at'        => $post->updated_at,
        ];
    }

    public function store(StorePostRequest $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'content_text'   => ['required', 'string', 'max:5000'],
            'media'          => ['nullable', 'array'],
            'media.*.id'     => ['required_with:media', 'uuid', 'exists:files,id'],
            'media.*.type'   => ['required_with:media', 'string', 'max:50'],
            'tags'           => ['nullable', 'array'],
            'tags.*'         => ['string', 'max:100'],
            'visibility'     => ['required', 'in:public,connections,private'],
            'circle_id'      => ['nullable', 'uuid'],
        ]);

        $mediaItems = [];

        if (! empty($data['media'])) {
            $fileIds = collect($data['media'])->pluck('id')->all();

            $files = File::whereIn('id', $fileIds)->get()->keyBy('id');

            foreach ($data['media'] as $item) {
                $file = $files->get($item['id']);
                if (! $file) {
                    continue;
                }

                $mediaItems[] = [
                    'id'   => $file->id,
                    'type' => $item['type'],
                    'url'  => url("/api/v1/files/{$file->id}"),
                ];
            }
        }

        $post = Post::create([
            'user_id'           => $user->id,
            'circle_id'         => $data['circle_id'] ?? null,
            'content_text'      => $data['content_text'],
            'media'             => $mediaItems ?: [],
            'tags'              => $data['tags'] ?? [],
            'visibility'        => $data['visibility'],
            'moderation_status' => 'pending',
            'sponsored'         => false,
            'is_deleted'        => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'circle_id' => $post->circle_id,
                'content_text' => $post->content_text,
                'media' => $post->media ?? [],
                'tags' => $post->tags ?? [],
                'visibility' => $post->visibility,
                'moderation_status' => $post->moderation_status,
                'sponsored' => $post->sponsored,
                'is_deleted' => $post->is_deleted,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $post = Post::with(['user', 'circle'])
            ->withCount(['likes', 'comments', 'saves'])
            ->withExists([
                'likes as is_liked_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
                'saves as is_saved_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])
            ->where('id', $id)
            ->where('posts.is_deleted', false)
            ->whereNull('posts.deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        return $this->success([
            'id'                => $post->id,
            'content_text'      => $post->content_text,
            'media'             => $post->media ?? [],
            'tags'              => $post->tags ?? [],
            'visibility'        => $post->visibility,
            'moderation_status' => $post->moderation_status,
            'author'            => $post->relationLoaded('user') && $post->user ? [
                'id'               => $post->user->id,
                'display_name'     => $post->user->display_name,
                'first_name'       => $post->user->first_name,
                'last_name'        => $post->user->last_name,
                'profile_photo_url'=> $post->user->profile_photo_url,
            ] : null,
            'circle'            => $post->relationLoaded('circle') && $post->circle ? [
                'id'   => $post->circle->id,
                'name' => $post->circle->name,
            ] : null,
            'likes_count'       => isset($post->likes_count) ? (int) $post->likes_count : 0,
            'comments_count'    => isset($post->comments_count) ? (int) $post->comments_count : 0,
            'is_liked_by_me'    => (bool) ($post->is_liked_by_me ?? false),
            'saves_count'       => isset($post->saves_count) ? (int) $post->saves_count : 0,
            'is_saved'          => (bool) ($post->is_saved_by_me ?? false),
            'created_at'        => $post->created_at,
            'updated_at'        => $post->updated_at,
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();

        // User can delete ONLY their own posts
        $post = Post::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $post) {
            return $this->error('Post not found or you are not allowed to delete it', 404);
        }

        $post->delete(); // respects SoftDeletes if used on the model

        return $this->success(null, 'Post deleted successfully');
    }

    public function like(Request $request, string $id)
    {
        $authUser = $request->user();

        $post = Post::where('id', $id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        PostLike::firstOrCreate([
            'post_id' => $post->id,
            'user_id' => $authUser->id,
        ]);

        $likeCount = PostLike::where('post_id', $post->id)->count();

        return $this->success(['like_count' => $likeCount], 'Post liked');
    }

    public function unlike(Request $request, string $id)
    {
        $authUser = $request->user();

        $post = Post::where('id', $id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        PostLike::where('post_id', $post->id)
            ->where('user_id', $authUser->id)
            ->delete();

        $likeCount = PostLike::where('post_id', $post->id)->count();

        return $this->success(['like_count' => $likeCount], 'Post unliked');
    }

    public function storeComment(StorePostCommentRequest $request, string $id)
    {
        $authUser = $request->user();

        $post = Post::where('id', $id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $data = $request->validated();

        $comment = new PostComment();
        $comment->post_id = $post->id;
        $comment->user_id = $authUser->id;
        $comment->content = $data['content'];
        $comment->parent_id = $data['parent_id'] ?? null;
        $comment->save();

        $comment->load('user');

        return $this->success(new PostCommentResource($comment), 'Comment added', 201);
    }

    public function listComments(Request $request, string $id)
    {
        $post = Post::where('id', $id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $paginator = PostComment::with('user')
            ->where('post_id', $post->id)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        $data = [
            'items' => PostCommentResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }
}

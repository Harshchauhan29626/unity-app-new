<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\Post;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PostModerationController extends Controller
{
    private function ensureGlobalAdmin(): void
    {
        $admin = Auth::guard('admin')->user();

        if (! AdminAccess::isGlobalAdmin($admin)) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->ensureGlobalAdmin();

        $circleId = $request->query('circle_id', 'all');

        $filters = [
            'active' => $request->input('active', 'all'),
            'visibility' => $request->input('visibility'),
            'moderation_status' => $request->input('moderation_status'),
            'search' => $request->input('search'),
        ];

        $postId = $request->query('post_id');
        $peer = $request->query('peer');
        $inlineVisibility = $request->query('inline_visibility', 'any');
        $inlineModerationStatus = $request->query('inline_moderation_status', 'any');
        $inlineActive = $request->query('inline_active', 'any');
        $content = $request->query('content');
        $media = $request->query('media', 'any');
        $actions = $request->query('actions', 'any'); // UI only

        $query = Post::query()
            ->with(['user', 'circle'])
            ->when($circleId !== 'all' && filled($circleId), fn ($q) => $q->where('circle_id', $circleId));

        if ($filters['active'] === 'active') {
            $query->where('posts.is_deleted', false)
                ->whereNull('posts.deleted_at');
        }

        if ($filters['active'] === 'deactivated') {
            $query->where(function ($subQuery) {
                $subQuery->where('posts.is_deleted', true)
                    ->orWhereNotNull('posts.deleted_at');
            });
        }


        if (filled($filters['visibility']) && $filters['visibility'] !== 'any') {
            $query->where('posts.visibility', $filters['visibility']);
        }

        if (filled($filters['moderation_status']) && $filters['moderation_status'] !== 'any') {
            $query->where('posts.moderation_status', $filters['moderation_status']);
        }

        if (filled($inlineVisibility) && $inlineVisibility !== 'any') {
            $query->where('posts.visibility', $inlineVisibility);
        }

        if (filled($inlineModerationStatus) && $inlineModerationStatus !== 'any') {
            $query->where('posts.moderation_status', $inlineModerationStatus);
        }

        if ($inlineActive === 'yes') {
            $query->where('posts.is_deleted', false)
                ->whereNull('posts.deleted_at');
        }

        if ($inlineActive === 'no') {
            $query->where(function ($subQuery) {
                $subQuery->where('posts.is_deleted', true)
                    ->orWhereNotNull('posts.deleted_at');
            });
        }

        if ($filters['search']) {
            $search = '%' . $filters['search'] . '%';

            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('posts.content_text', 'ILIKE', $search)
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('display_name', 'ILIKE', $search)
                            ->orWhere('name', 'ILIKE', $search)
                            ->orWhere('first_name', 'ILIKE', $search)
                            ->orWhere('last_name', 'ILIKE', $search);
                    });
            });
        }

        if (filled($postId)) {
            $query->where('posts.id', 'ILIKE', '%' . $postId . '%');
        }

        if (filled($peer)) {
            $peerQuery = '%' . $peer . '%';
            $query->whereHas('user', function ($userQuery) use ($peerQuery) {
                $userQuery->where(function ($subQuery) use ($peerQuery) {
                    $subQuery->where('name', 'ILIKE', $peerQuery)
                        ->orWhere('display_name', 'ILIKE', $peerQuery)
                        ->orWhere('first_name', 'ILIKE', $peerQuery)
                        ->orWhere('last_name', 'ILIKE', $peerQuery)
                        ->orWhere('company', 'ILIKE', $peerQuery)
                        ->orWhere('company_name', 'ILIKE', $peerQuery)
                        ->orWhere('business_name', 'ILIKE', $peerQuery)
                        ->orWhere('organization', 'ILIKE', $peerQuery)
                        ->orWhere('city', 'ILIKE', $peerQuery)
                        ->orWhere('current_city', 'ILIKE', $peerQuery)
                        ->orWhere('location_city', 'ILIKE', $peerQuery);
                });
            });
        }

        if (filled($content)) {
            $query->where('posts.content_text', 'ILIKE', '%' . $content . '%');
        }

        if ($media === 'has') {
            $query->where(function ($subQuery) {
                $subQuery->whereNotNull('posts.media')
                    ->whereRaw("NULLIF(TRIM(posts.media::text), '') IS NOT NULL")
                    ->whereRaw("posts.media::text NOT IN ('[]', '{}', 'null')");
            });
        }

        if ($media === 'none') {
            $query->where(function ($subQuery) {
                $subQuery->whereNull('posts.media')
                    ->orWhereRaw("TRIM(posts.media::text) = ''")
                    ->orWhereRaw("posts.media::text IN ('[]', '{}', 'null')");
            });
        }

        $posts = $query
            ->orderByDesc('posts.created_at')
            ->paginate(25);

        $posts->appends($request->query());

        $visibilities = ['public', 'connections', 'private'];
        $moderationStatuses = Post::query()
            ->whereNotNull('moderation_status')
            ->distinct()
            ->orderBy('moderation_status')
            ->pluck('moderation_status')
            ->values();

        $circles = Circle::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.posts.index', [
            'posts' => $posts,
            'filters' => $filters,
            'visibilities' => $visibilities,
            'moderationStatuses' => $moderationStatuses,
            'circles' => $circles,
            'circleId' => $circleId,
            'postId' => $postId,
            'peer' => $peer,
            'inlineVisibility' => $inlineVisibility,
            'inlineModerationStatus' => $inlineModerationStatus,
            'inlineActive' => $inlineActive,
            'content' => $content,
            'media' => $media,
            'actions' => $actions,
        ]);
    }

    public function show(string $postId): View
    {
        $this->ensureGlobalAdmin();

        $post = Post::withTrashed()
            ->with([
                'user:id,display_name,first_name,last_name',
                'circle:id,name',
            ])
            ->findOrFail($postId);

        return view('admin.posts.show', [
            'post' => $post,
        ]);
    }

    public function deactivate(string $postId): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $post = Post::withTrashed()->findOrFail($postId);

        DB::transaction(function () use ($post): void {
            $post->is_deleted = true;
            $post->deleted_at = now();
            $post->save();
        });

        return redirect()
            ->back()
            ->with('success', 'Post deactivated.');
    }

    public function restore(string $postId): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $post = Post::withTrashed()->findOrFail($postId);

        DB::transaction(function () use ($post): void {
            $post->is_deleted = false;
            $post->deleted_at = null;
            $post->save();
        });

        return redirect()
            ->back()
            ->with('success', 'Post restored.');
    }
}

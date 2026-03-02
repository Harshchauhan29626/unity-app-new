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

        if ($filters['visibility']) {
            $query->where('posts.visibility', $filters['visibility']);
        }

        if ($filters['moderation_status']) {
            $query->where('posts.moderation_status', $filters['moderation_status']);
        }

        if ($filters['search']) {
            $search = '%' . $filters['search'] . '%';

            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('posts.content_text', 'ILIKE', $search)
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('display_name', 'ILIKE', $search)
                            ->orWhere('first_name', 'ILIKE', $search)
                            ->orWhere('last_name', 'ILIKE', $search);
                    });
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

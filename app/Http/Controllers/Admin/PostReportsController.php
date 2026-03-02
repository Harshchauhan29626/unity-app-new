<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\Post;
use App\Models\PostReport;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PostReportsController extends Controller
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

        $filters = [
            'status' => $request->input('status'),
            'reason' => $request->input('reason'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'circle_id' => $request->input('circle_id', 'all'),
        ];

        $query = PostReport::query()
            ->select('post_reports.*')
            ->selectSub(
                PostReport::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('post_id', 'post_reports.post_id')
                    ->whereNull('deleted_at'),
                'total_reports'
            )
            ->with([
                'post.user.profile',
                'post.circle:id,name',
                'reporter:id,display_name,first_name,last_name',
                'reasonOption:id,title',
            ]);

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        if ($filters['reason']) {
            $query->where('reason', $filters['reason']);
        }

        if ($filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if ($filters['circle_id'] && $filters['circle_id'] !== 'all') {
            $query->whereHas('post', function ($postQuery) use ($filters) {
                $postQuery->where('circle_id', $filters['circle_id']);
            });
        }

        $reports = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $reasons = [
            'spam',
            'abuse',
            'fake',
            'harassment',
            'nudity',
            'hate',
            'scam',
            'other',
        ];

        $statuses = ['open', 'reviewed', 'dismissed', 'resolved'];
        $circles = Circle::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.post_reports.index', [
            'reports' => $reports,
            'filters' => $filters,
            'reasons' => $reasons,
            'statuses' => $statuses,
            'circles' => $circles,
        ]);
    }

    public function show(string $reportId): View
    {
        $this->ensureGlobalAdmin();

        $report = PostReport::query()
            ->with([
                'post.user:id,display_name,first_name,last_name',
                'reporter:id,display_name,first_name,last_name',
                'reasonOption:id,title',
                'reviewer:id,name',
            ])
            ->findOrFail($reportId);

        $postReports = PostReport::query()
            ->with(['reporter:id,display_name,first_name,last_name'])
            ->where('post_id', $report->post_id)
            ->orderByDesc('created_at')
            ->get();

        return view('admin.post_reports.show', [
            'report' => $report,
            'postReports' => $postReports,
        ]);
    }

    public function markReviewed(string $reportId): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $admin = Auth::guard('admin')->user();

        $report = PostReport::query()->findOrFail($reportId);
        $report->status = 'reviewed';
        $report->reviewed_by_admin_user_id = $admin?->id;
        $report->reviewed_at = now();
        $report->save();

        return redirect()
            ->back()
            ->with('success', 'Report marked as reviewed.');
    }

    public function dismiss(Request $request, string $reportId): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $report = PostReport::query()->findOrFail($reportId);
        $report->status = 'dismissed';
        $report->admin_note = $validated['admin_note'] ?? null;
        $report->save();

        return redirect()
            ->back()
            ->with('success', 'Report dismissed.');
    }

    public function resolve(Request $request, string $reportId): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $report = PostReport::query()->findOrFail($reportId);
        $report->status = 'resolved';
        $report->admin_note = $validated['admin_note'] ?? null;
        $report->save();

        return redirect()
            ->back()
            ->with('success', 'Report resolved.');
    }

    public function deactivatePost(string $postId): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $post = Post::withTrashed()->findOrFail($postId);
        $post->is_deleted = true;
        $post->deleted_at = now();
        $post->save();

        return redirect()
            ->back()
            ->with('success', 'Post deactivated.');
    }

    public function restorePost(string $postId): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $post = Post::withTrashed()->findOrFail($postId);
        $post->is_deleted = false;
        $post->deleted_at = null;
        $post->save();

        return redirect()
            ->back()
            ->with('success', 'Post restored.');
    }
}

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

        $circleId = $request->query('circle_id', 'all');

        $filters = [
            'status' => $request->input('status'),
            'reason' => $request->input('reason'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $postId = $request->query('post_id');
        $peer = $request->query('peer');
        $reporter = $request->query('reporter');
        $reasonText = $request->query('reason_text');
        $totalReports = $request->query('total_reports', 'any');
        $postActive = $request->query('post_active', 'any');
        $media = $request->query('media', 'any');

        $query = PostReport::query()
            ->select('post_reports.*')
            ->selectSub(function ($subQuery) {
                $subQuery->from('post_reports as pr2')
                    ->selectRaw('count(*)')
                    ->whereColumn('pr2.post_id', 'post_reports.post_id')
                    ->whereNull('pr2.deleted_at');
            }, 'total_reports')
            ->with(['post.user', 'post.circle', 'reporter', 'reasonOption'])
            ->when($circleId !== 'all' && filled($circleId), function ($q) use ($circleId) {
                $q->whereHas('post', fn ($postQuery) => $postQuery->where('circle_id', $circleId));
            });

        if (filled($filters['status']) && $filters['status'] !== 'any') {
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

        if (filled($postId)) {
            $query->whereHas('post', fn ($postQuery) => $postQuery->where('id', 'ILIKE', '%' . $postId . '%'));
        }

        if (filled($peer)) {
            $peerLike = '%' . $peer . '%';
            $query->whereHas('post.user', function ($userQuery) use ($peerLike) {
                $userQuery->where(function ($subQuery) use ($peerLike) {
                    $subQuery->where('name', 'ILIKE', $peerLike)
                        ->orWhere('display_name', 'ILIKE', $peerLike)
                        ->orWhere('first_name', 'ILIKE', $peerLike)
                        ->orWhere('last_name', 'ILIKE', $peerLike)
                        ->orWhere('company', 'ILIKE', $peerLike)
                        ->orWhere('company_name', 'ILIKE', $peerLike)
                        ->orWhere('business_name', 'ILIKE', $peerLike)
                        ->orWhere('city', 'ILIKE', $peerLike);
                });
            });
        }

        if (filled($reporter)) {
            $reporterLike = '%' . $reporter . '%';
            $query->whereHas('reporter', function ($reporterQuery) use ($reporterLike) {
                $reporterQuery->where(function ($subQuery) use ($reporterLike) {
                    $subQuery->where('name', 'ILIKE', $reporterLike)
                        ->orWhere('display_name', 'ILIKE', $reporterLike)
                        ->orWhere('first_name', 'ILIKE', $reporterLike)
                        ->orWhere('last_name', 'ILIKE', $reporterLike)
                        ->orWhere('email', 'ILIKE', $reporterLike);
                });
            });
        }

        if (filled($reasonText)) {
            $query->where(function ($subQuery) use ($reasonText) {
                $subQuery->where('reason', 'ILIKE', '%' . $reasonText . '%')
                    ->orWhereHas('reasonOption', fn ($reasonQuery) => $reasonQuery->where('title', 'ILIKE', '%' . $reasonText . '%'));
            });
        }

        if ($totalReports === '1') {
            $query->whereRaw('(select count(*) from post_reports pr2 where pr2.post_id = post_reports.post_id and pr2.deleted_at is null) = ?', [1]);
        }

        if ($totalReports === '2-5') {
            $query->whereRaw('(select count(*) from post_reports pr2 where pr2.post_id = post_reports.post_id and pr2.deleted_at is null) between ? and ?', [2, 5]);
        }

        if ($totalReports === '6+') {
            $query->whereRaw('(select count(*) from post_reports pr2 where pr2.post_id = post_reports.post_id and pr2.deleted_at is null) >= ?', [6]);
        }

        if ($postActive === 'yes') {
            $query->whereHas('post', function ($postQuery) {
                $postQuery->where('is_deleted', false)->whereNull('deleted_at');
            });
        }

        if ($postActive === 'no') {
            $query->whereHas('post', function ($postQuery) {
                $postQuery->where(function ($subQuery) {
                    $subQuery->where('is_deleted', true)->orWhereNotNull('deleted_at');
                });
            });
        }

        if ($media === 'has') {
            $query->whereHas('post', function ($postQuery) {
                $postQuery->where(function ($subQuery) {
                    $subQuery->whereNotNull('media')
                        ->whereRaw("NULLIF(TRIM(media::text), '') IS NOT NULL")
                        ->whereRaw("media::text NOT IN ('[]', '{}', 'null')");
                });
            });
        }

        if ($media === 'none') {
            $query->whereHas('post', function ($postQuery) {
                $postQuery->where(function ($subQuery) {
                    $subQuery->whereNull('media')
                        ->orWhereRaw("TRIM(media::text) = ''")
                        ->orWhereRaw("media::text IN ('[]', '{}', 'null')");
                });
            });
        }

        $reports = $query->orderByDesc('created_at')->paginate(25)->appends($request->query());

        $reasons = ['spam', 'abuse', 'fake', 'harassment', 'nudity', 'hate', 'scam', 'other'];
        $statuses = ['open', 'reviewed', 'dismissed', 'resolved'];
        $circles = Circle::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.post_reports.index', [
            'reports' => $reports,
            'filters' => $filters,
            'reasons' => $reasons,
            'statuses' => $statuses,
            'circles' => $circles,
            'circleId' => $circleId,
            'postId' => $postId,
            'peer' => $peer,
            'reporter' => $reporter,
            'reasonText' => $reasonText,
            'totalReports' => $totalReports,
            'postActive' => $postActive,
            'media' => $media,
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

        return redirect()->back()->with('success', 'Report marked as reviewed.');
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

        return redirect()->back()->with('success', 'Report dismissed.');
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

        return redirect()->back()->with('success', 'Report resolved.');
    }

    public function deactivatePost(string $postId): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $post = Post::withTrashed()->findOrFail($postId);
        $post->is_deleted = true;
        $post->deleted_at = now();
        $post->save();

        return redirect()->back()->with('success', 'Post deactivated.');
    }

    public function restorePost(string $postId): RedirectResponse
    {
        $this->ensureGlobalAdmin();

        $post = Post::withTrashed()->findOrFail($postId);
        $post->is_deleted = false;
        $post->deleted_at = null;
        $post->save();

        return redirect()->back()->with('success', 'Post restored.');
    }
}

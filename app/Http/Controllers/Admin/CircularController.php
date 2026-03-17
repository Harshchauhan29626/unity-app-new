<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCircularRequest;
use App\Http\Requests\Admin\UpdateCircularRequest;
use App\Models\Circular;
use App\Models\Circle;
use App\Models\City;
use App\Services\Circulars\CircularNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CircularController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => (string) $request->query('category', ''),
            'priority' => (string) $request->query('priority', ''),
            'status' => (string) $request->query('status', ''),
            'audience_type' => (string) $request->query('audience_type', ''),
            'city_id' => (string) $request->query('city_id', ''),
            'circle_id' => (string) $request->query('circle_id', ''),
            'publish_date_from' => (string) $request->query('publish_date_from', ''),
            'publish_date_to' => (string) $request->query('publish_date_to', ''),
        ];

        $query = Circular::query()->with(['city', 'circle', 'creator', 'updater']);

        if ($filters['search'] !== '') {
            $like = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q
                ->where('title', 'ILIKE', $like)
                ->orWhere('summary', 'ILIKE', $like));
        }

        foreach (['category', 'priority', 'status', 'audience_type', 'city_id', 'circle_id'] as $field) {
            if ($filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        if ($filters['publish_date_from'] !== '') {
            $query->where('publish_date', '>=', $filters['publish_date_from']);
        }

        if ($filters['publish_date_to'] !== '') {
            $query->where('publish_date', '<=', $filters['publish_date_to']);
        }

        $circulars = $query->orderByDesc('created_at')->paginate(20)->appends($request->query());

        return view('admin.circulars.index', [
            'circulars' => $circulars,
            'filters' => $filters,
            'cities' => City::query()->orderBy('name')->get(['id', 'name']),
            'circles' => Circle::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Circular::CATEGORY_OPTIONS,
            'priorities' => Circular::PRIORITY_OPTIONS,
            'audiences' => Circular::AUDIENCE_OPTIONS,
            'statuses' => Circular::STATUS_OPTIONS,
        ]);
    }

    public function create(): View
    {
        return $this->formView(new Circular(), 'admin.circulars.create');
    }

    public function store(StoreCircularRequest $request): RedirectResponse
    {
        $data = $this->payload($request);

        $publishDate = ! empty($data['publish_date'])
            ? Carbon::parse($data['publish_date'], 'Asia/Kolkata')->utc()
            : now()->utc();

        $expiryDate = ! empty($data['expiry_date'])
            ? Carbon::parse($data['expiry_date'], 'Asia/Kolkata')->utc()
            : null;

        $data['publish_date'] = $publishDate;
        $data['expiry_date'] = $expiryDate;
        $data['created_by'] = (string) Auth::guard('admin')->id();
        $data['updated_by'] = (string) Auth::guard('admin')->id();

        $circular = Circular::create($data);
        app(CircularNotificationService::class)->send($circular);

        return redirect()->route('admin.circulars.index')->with('success', 'Circular created successfully.');
    }

    public function show(Circular $circular): View
    {
        $circular->load(['city', 'circle']);

        return view('admin.circulars.show', compact('circular'));
    }

    public function edit(Circular $circular): View
    {
        return $this->formView($circular, 'admin.circulars.edit');
    }

    public function update(UpdateCircularRequest $request, Circular $circular): RedirectResponse
    {
        $data = $this->payload($request);

        $publishDate = ! empty($data['publish_date'])
            ? Carbon::parse($data['publish_date'], 'Asia/Kolkata')->utc()
            : now()->utc();

        $expiryDate = ! empty($data['expiry_date'])
            ? Carbon::parse($data['expiry_date'], 'Asia/Kolkata')->utc()
            : null;

        $data['publish_date'] = $publishDate;
        $data['expiry_date'] = $expiryDate;

        $circular->fill($data);
        $circular->updated_by = (string) Auth::guard('admin')->id();

        if ($circular->isDirty('status') && $circular->status === 'published') {
            $circular->notification_sent_at = null;
        }

        $circular->save();
        app(CircularNotificationService::class)->send($circular);

        return redirect()->route('admin.circulars.show', $circular)->with('success', 'Circular updated successfully.');
    }

    public function destroy(Circular $circular): RedirectResponse
    {
        $circular->delete();

        return redirect()->route('admin.circulars.index')->with('success', 'Circular deleted successfully.');
    }

    private function formView(Circular $circular, string $view): View
    {
        return view($view, [
            'circular' => $circular,
            'cities' => City::query()->orderBy('name')->get(['id', 'name']),
            'circles' => Circle::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Circular::CATEGORY_OPTIONS,
            'priorities' => Circular::PRIORITY_OPTIONS,
            'audiences' => Circular::AUDIENCE_OPTIONS,
            'statuses' => Circular::STATUS_OPTIONS,
        ]);
    }

    private function payload(Request $request): array
    {
        $validated = $request->validated();

        $featuredImageFileId = $validated['featured_image_file_id'] ?? null;
        $attachmentFileId = $validated['attachment_file_id'] ?? null;

        $validated['featured_image_url'] = $featuredImageFileId ? url('/api/v1/files/'.$featuredImageFileId) : null;
        $validated['attachment_url'] = $attachmentFileId ? url('/api/v1/files/'.$attachmentFileId) : null;

        $validated['send_push_notification'] = $request->boolean('send_push_notification', true);
        $validated['allow_comments'] = $request->boolean('allow_comments', false);
        $validated['is_pinned'] = $request->boolean('is_pinned', false);

        unset($validated['featured_image_file_id'], $validated['attachment_file_id']);

        return $validated;
    }
}

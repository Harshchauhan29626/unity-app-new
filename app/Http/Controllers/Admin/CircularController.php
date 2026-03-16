<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Circulars\StoreCircularRequest;
use App\Http\Requests\Admin\Circulars\UpdateCircularRequest;
use App\Models\Circular;
use App\Models\Circle;
use App\Models\City;
use App\Services\Circulars\CircularNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CircularController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only([
            'search', 'category', 'priority', 'audience_type', 'status', 'city_id', 'circle_id', 'from_date', 'to_date',
        ]);

        $query = Circular::query()->with(['city:id,name', 'circle:id,name', 'creator:id,name,email']);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('summary', 'ILIKE', "%{$search}%");
            });
        }

        foreach (['category', 'priority', 'audience_type', 'status', 'city_id', 'circle_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('publish_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('publish_date', '<=', $filters['to_date']);
        }

        $circulars = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.circulars.index', [
            'circulars' => $circulars,
            'filters' => $filters,
            'categories' => Circular::CATEGORY_OPTIONS,
            'priorities' => Circular::PRIORITY_OPTIONS,
            'audiences' => Circular::AUDIENCE_OPTIONS,
            'cities' => City::query()->orderBy('name')->get(['id', 'name']),
            'circles' => Circle::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.circulars.create', $this->formData());
    }

    public function store(StoreCircularRequest $request, CircularNotificationService $notificationService): RedirectResponse
    {
        $circular = new Circular($this->payload($request));
        $circular->created_by = Auth::guard('admin')->id();
        $circular->updated_by = Auth::guard('admin')->id();
        $circular->save();

        if ($circular->send_push_notification) {
            $notificationService->notify($circular);
        }

        return redirect()->route('admin.circulars.index')->with('success', 'Circular created successfully.');
    }

    public function edit(Circular $circular): View
    {
        return view('admin.circulars.edit', $this->formData(['circular' => $circular]));
    }

    public function update(UpdateCircularRequest $request, Circular $circular, CircularNotificationService $notificationService): RedirectResponse
    {
        $circular->fill($this->payload($request));
        $circular->updated_by = Auth::guard('admin')->id();
        $circular->save();

        if ($circular->send_push_notification) {
            $notificationService->notify($circular);
        }

        return redirect()->route('admin.circulars.index')->with('success', 'Circular updated successfully.');
    }

    public function destroy(Circular $circular): RedirectResponse
    {
        $circular->delete();

        return redirect()->route('admin.circulars.index')->with('success', 'Circular deleted successfully.');
    }

    public function toggleStatus(Circular $circular): RedirectResponse
    {
        $circular->status = $circular->status === 'active' ? 'inactive' : 'active';
        $circular->updated_by = Auth::guard('admin')->id();
        $circular->save();

        return redirect()->route('admin.circulars.index')->with('success', 'Circular status updated.');
    }

    private function payload(Request $request): array
    {
        $data = Arr::except($request->validated(), ['featured_image', 'attachment']);

        $data['send_push_notification'] = $request->boolean('send_push_notification');
        $data['allow_comments'] = $request->boolean('allow_comments');
        $data['is_pinned'] = $request->boolean('is_pinned');

        if ($request->hasFile('featured_image')) {
            $path = $request->file('featured_image')->store('circulars/images', config('filesystems.default', 'public'));
            $data['featured_image_url'] = Storage::url($path);
            $data['featured_image_file_id'] = null;
        }

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('circulars/attachments', config('filesystems.default', 'public'));
            $data['attachment_url'] = Storage::url($path);
            $data['attachment_file_id'] = null;
        }

        return $data;
    }

    private function formData(array $extra = []): array
    {
        return array_merge([
            'categories' => Circular::CATEGORY_OPTIONS,
            'priorities' => Circular::PRIORITY_OPTIONS,
            'audiences' => Circular::AUDIENCE_OPTIONS,
            'cities' => City::query()->orderBy('name')->get(['id', 'name']),
            'circles' => Circle::query()->orderBy('name')->get(['id', 'name']),
        ], $extra);
    }
}

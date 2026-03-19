<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ads\StoreAdRequest;
use App\Http\Requests\Admin\Ads\UpdateAdRequest;
use App\Models\Ad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $ads = Ad::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('title', 'ILIKE', '%' . $search . '%');
            })
            ->orderBy('placement')
            ->orderByRaw('CASE WHEN timeline_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('timeline_position')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.ads.index', compact('ads', 'search'));
    }

    public function create(): View
    {
        return view('admin.ads.create', [
            'ad' => new Ad(['placement' => 'timeline', 'is_active' => true, 'sort_order' => 0]),
            'placements' => $this->placements(),
        ]);
    }

    public function store(StoreAdRequest $request): RedirectResponse
    {
        $data = $this->payload($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('ads', 'public');
        }

        $adminUser = Auth::guard('admin')->user();
        $data['created_by'] = $adminUser?->id;

        Ad::query()->create($data);

        return redirect()->route('admin.ads.index')->with('success', 'Ad created successfully.');
    }

    public function edit(Ad $ad): View
    {
        return view('admin.ads.edit', [
            'ad' => $ad,
            'placements' => $this->placements(),
        ]);
    }

    public function update(UpdateAdRequest $request, Ad $ad): RedirectResponse
    {
        $data = $this->payload($request);

        if ($request->hasFile('image')) {
            if ($ad->image_path) {
                Storage::disk('public')->delete($ad->image_path);
            }

            $data['image_path'] = $request->file('image')->store('ads', 'public');
        }

        $ad->update($data);

        return redirect()->route('admin.ads.index')->with('success', 'Ad updated successfully.');
    }

    public function destroy(Ad $ad): RedirectResponse
    {
        if ($ad->image_path) {
            Storage::disk('public')->delete($ad->image_path);
        }

        $ad->delete();

        return redirect()->route('admin.ads.index')->with('success', 'Ad deleted successfully.');
    }

    public function toggleStatus(Ad $ad): RedirectResponse
    {
        $ad->update(['is_active' => ! $ad->is_active]);

        return redirect()->route('admin.ads.index')->with('success', 'Ad status updated successfully.');
    }

    private function payload(StoreAdRequest|UpdateAdRequest $request): array
    {
        $data = $request->validated();

        unset($data['image']);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if (($data['placement'] ?? null) !== 'timeline') {
            $data['timeline_position'] = null;
        }

        return $data;
    }

    private function placements(): array
    {
        return ['timeline', 'home', 'category', 'sidebar'];
    }
}

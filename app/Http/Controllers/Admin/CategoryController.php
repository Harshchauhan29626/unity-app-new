<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\StoreCategoryRequest;
use App\Http\Requests\Admin\Categories\UpdateCategoryRequest;
use App\Imports\CategoriesImport;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel2;
use App\Models\CircleCategoryLevel3;
use App\Models\CircleCategoryLevel4;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $categories = CircleCategory::query()
            ->where('level', 1)
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'ILIKE', '%' . $search . '%');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'category' => new CircleCategory([
                'level' => 1,
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function show(CircleCategory $category): View
    {
        abort_unless((int) $category->level === 1 && $category->is_active, 404);

        $level2Categories = CircleCategoryLevel2::query()
            ->where('circle_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $level3Categories = CircleCategoryLevel3::query()
            ->where('circle_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $level4Categories = CircleCategoryLevel4::query()
            ->where('circle_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $level3ByLevel2 = [];
        foreach ($level3Categories as $level3Category) {
            $level2Id = $level3Category->level2_id ?? $level3Category->circle_category_level2_id ?? null;
            if ($level2Id === null) {
                continue;
            }

            $level3ByLevel2[$level2Id][] = $level3Category;
        }

        $level4ByLevel3 = [];
        foreach ($level4Categories as $level4Category) {
            $level3Id = $level4Category->level3_id ?? $level4Category->circle_category_level3_id ?? null;
            if ($level3Id === null) {
                continue;
            }

            $level4ByLevel3[$level3Id][] = $level4Category;
        }

        $children = [];
        foreach ($level2Categories as $level2Category) {
            $level3Children = $level3ByLevel2[$level2Category->id] ?? [];

            $children[] = [
                'category' => $level2Category,
                'children' => collect($level3Children)->map(function ($level3Category) use ($level4ByLevel3) {
                    return [
                        'category' => $level3Category,
                        'children' => $level4ByLevel3[$level3Category->id] ?? [],
                    ];
                })->all(),
            ];
        }

        $level2Count = $level2Categories->count();
        $level3Count = $level3Categories->count();
        $level4Count = $level4Categories->count();

        return view('admin.categories.view', [
            'category' => $category,
            'level2Count' => $level2Count,
            'level3Count' => $level3Count,
            'level4Count' => $level4Count,
            'totalChildren' => $level2Count + $level3Count + $level4Count,
            'children' => $children,
            'level2Options' => $level2Categories,
            'level3Options' => $level3Categories,
        ]);
    }

    public function storeLevel2(Request $request, CircleCategory $category): RedirectResponse
    {
        abort_unless((int) $category->level === 1, 404);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_category_level2', 'name')->where(fn ($query) => $query->where('circle_category_id', $category->id)),
            ],
        ]);

        CircleCategoryLevel2::query()->create([
            'circle_category_id' => $category->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
            'sort_order' => ((int) CircleCategoryLevel2::query()->where('circle_category_id', $category->id)->max('sort_order')) + 1,
        ]);

        return redirect()->route('admin.categories.view', $category)->with('success', 'Level 2 category added successfully.');
    }

    public function storeLevel3(Request $request, CircleCategory $category): RedirectResponse
    {
        abort_unless((int) $category->level === 1, 404);

        $validated = $request->validate([
            'level2_id' => [
                'required',
                'integer',
                Rule::exists('circle_category_level2', 'id')->where(fn ($query) => $query->where('circle_category_id', $category->id)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_category_level3', 'name')->where(fn ($query) => $query->where('level2_id', (int) $request->input('level2_id'))),
            ],
        ]);

        CircleCategoryLevel3::query()->create([
            'circle_category_id' => $category->id,
            'level2_id' => (int) $validated['level2_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
            'sort_order' => ((int) CircleCategoryLevel3::query()->where('level2_id', (int) $validated['level2_id'])->max('sort_order')) + 1,
        ]);

        return redirect()->route('admin.categories.view', $category)->with('success', 'Level 3 category added successfully.');
    }

    public function storeLevel4(Request $request, CircleCategory $category): RedirectResponse
    {
        abort_unless((int) $category->level === 1, 404);

        $validated = $request->validate([
            'level2_id' => [
                'required',
                'integer',
                Rule::exists('circle_category_level2', 'id')->where(fn ($query) => $query->where('circle_category_id', $category->id)),
            ],
            'level3_id' => [
                'required',
                'integer',
                Rule::exists('circle_category_level3', 'id')->where(fn ($query) => $query->where('level2_id', (int) $request->input('level2_id'))->where('circle_category_id', $category->id)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('circle_category_level4', 'name')->where(fn ($query) => $query->where('level3_id', (int) $request->input('level3_id'))),
            ],
        ]);

        CircleCategoryLevel4::query()->create([
            'circle_category_id' => $category->id,
            'level2_id' => (int) $validated['level2_id'],
            'level3_id' => (int) $validated['level3_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
            'sort_order' => ((int) CircleCategoryLevel4::query()->where('level3_id', (int) $validated['level3_id'])->max('sort_order')) + 1,
        ]);

        return redirect()->route('admin.categories.view', $category)->with('success', 'Level 4 category added successfully.');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['level'] = 1;
        $payload['is_active'] = $request->boolean('is_active');

        CircleCategory::query()->create($payload);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(CircleCategory $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, CircleCategory $category): RedirectResponse
    {
        $payload = $request->validated();
        $payload['level'] = 1;
        $payload['is_active'] = $request->boolean('is_active');

        $category->update($payload);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(CircleCategory $category): RedirectResponse
    {
        try {
            if ((int) $category->level !== 1) {
                return redirect()
                    ->route('admin.categories.index')
                    ->with('error', 'Category not found.');
            }

            if (! $category->is_active) {
                return redirect()
                    ->route('admin.categories.index')
                    ->with('error', 'Category is already inactive.');
            }

            DB::transaction(function () use ($category): void {
                $now = now();

                CircleCategoryLevel4::query()
                    ->where('circle_category_id', $category->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                CircleCategoryLevel3::query()
                    ->where('circle_category_id', $category->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                CircleCategoryLevel2::query()
                    ->where('circle_category_id', $category->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                $deactivatedMain = CircleCategory::query()
                    ->where('id', $category->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                if ($deactivatedMain !== 1) {
                    throw new \RuntimeException('Circle category deactivation did not affect any rows.');
                }
            });

            return redirect()
                ->route('admin.categories.index')
                ->with('success', 'Category deactivated successfully.');
        } catch (\Throwable $e) {
            Log::error('admin.circle_category.delete_failed', [
                'category_id' => (int) $category->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.categories.index')
                ->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        try {
            $search = trim((string) $request->query('q', ''));

            $categories = CircleCategory::query()
                ->select([
                    'id',
                    'name',
                    'slug',
                    'circle_key',
                    'level',
                    'sort_order',
                    'is_active',
                    'created_at',
                    'updated_at',
                ])
                ->where('level', 1)
                ->where('is_active', true)
                ->when($search !== '', function ($query) use ($search) {
                    $query->where('name', 'ILIKE', '%' . $search . '%');
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->streamDownload(
                function () use ($categories): void {
                    $handle = fopen('php://output', 'w');

                    if ($handle === false) {
                        throw new \RuntimeException('Could not open output stream for CSV export.');
                    }

                    fwrite($handle, "\xEF\xBB\xBF");
                    fputcsv($handle, ['ID', 'Name', 'Slug', 'Circle Key', 'Level', 'Sort Order', 'Is Active', 'Created At', 'Updated At']);

                    foreach ($categories as $category) {
                        fputcsv($handle, [
                            $category->id,
                            (string) ($category->name ?? ''),
                            (string) ($category->slug ?? ''),
                            (string) ($category->circle_key ?? ''),
                            (string) ($category->level ?? ''),
                            (string) ($category->sort_order ?? ''),
                            $category->is_active ? 'true' : 'false',
                            (string) ($category->created_at ?? ''),
                            (string) ($category->updated_at ?? ''),
                        ]);
                    }

                    fclose($handle);
                },
                'circle_categories.csv',
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]
            );
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Unable to export categories: ' . $e->getMessage());
        }
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx',
        ]);

        try {
            $result = (new CategoriesImport())->import($request->file('file'));
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Unable to import categories: ' . $e->getMessage());
        }

        return redirect()
            ->back()
            ->with('success', "Categories import completed. Imported: {$result['imported_count']}, Skipped duplicates: {$result['skipped_duplicate_count']}, Skipped empty: {$result['skipped_empty_count']}")
            ->with('imported_count', $result['imported_count'])
            ->with('skipped_duplicate_count', $result['skipped_duplicate_count'])
            ->with('skipped_empty_count', $result['skipped_empty_count']);
    }
}

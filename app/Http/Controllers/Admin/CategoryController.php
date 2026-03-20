<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\StoreCategoryRequest;
use App\Http\Requests\Admin\Categories\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $categories = Category::query()
            ->when($search !== '', fn ($query) => $query->where('category_name', 'ILIKE', '%' . $search . '%'))
            ->orderBy('category_name')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'category' => new Category(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        try {
            if (
                $category->circleMappings()->exists() ||
                (
                    DB::getSchemaBuilder()->hasColumn('event_galleries', 'circle_category_id') &&
                    DB::table('event_galleries')->where('circle_category_id', $category->id)->exists()
                )
            ) {
                return redirect()
                    ->route('admin.categories.index')
                    ->with('error', 'This category is in use and cannot be deleted.');
            }

            $category->delete();

            return redirect()
                ->route('admin.categories.index')
                ->with('success', 'Category deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.categories.index')
                ->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        try {
            $search = trim((string) $request->query('q', ''));
            $hasNameColumn = Schema::hasColumn('categories', 'name');
            $nameColumn = $hasNameColumn ? 'name' : 'category_name';

            $categories = Category::query()
                ->with('sector')
                ->when($search !== '', fn ($query) => $query->where($nameColumn, 'ILIKE', '%' . $search . '%'))
                ->orderBy($nameColumn)
                ->get();

            return response()->streamDownload(
                function () use ($categories): void {
                    $handle = fopen('php://output', 'w');

                    if ($handle === false) {
                        throw new \RuntimeException('Could not open output stream for CSV export.');
                    }

                    fwrite($handle, "\xEF\xBB\xBF");
                    fputcsv($handle, ['ID', 'Category Name', 'Sector', 'Remarks']);

                    foreach ($categories as $category) {
                        $name = (string) ($category->name ?? $category->category_name ?? '');
                        $sectorName = (string) (
                            $category->getRelationValue('sector')?->name
                            ?? $category->sector
                            ?? $category->sector_id
                            ?? ''
                        );

                        fputcsv($handle, [
                            $category->id,
                            $name,
                            $sectorName,
                            (string) ($category->remarks ?? ''),
                        ]);
                    }

                    fclose($handle);
                },
                'categories.csv',
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
            'file' => 'required|mimes:csv,txt',
        ]);

        try {
            $file = $request->file('file');
            $stream = fopen($file->getRealPath(), 'r');

            if ($stream === false) {
                throw new \RuntimeException('Unable to read uploaded CSV file.');
            }

            $headers = fgetcsv($stream);
            if (! is_array($headers)) {
                fclose($stream);
                throw new \RuntimeException('Invalid CSV header row.');
            }

            $headers = array_map(static function ($header): string {
                $normalized = strtolower(trim((string) $header));
                return $normalized === "\xEF\xBB\xBFid" ? 'id' : $normalized;
            }, $headers);

            $hasNameColumn = Schema::hasColumn('categories', 'name');
            $hasSectorIdColumn = Schema::hasColumn('categories', 'sector_id');

            while (($row = fgetcsv($stream)) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }

                $record = array_combine($headers, array_pad($row, count($headers), null));

                if (! is_array($record)) {
                    continue;
                }

                $name = trim((string) ($record['name'] ?? $record['category_name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $payload = ['remarks' => $record['remarks'] ?? null];

                if ($hasNameColumn) {
                    $payload['name'] = $name;
                } else {
                    $payload['category_name'] = $name;
                }

                if ($hasSectorIdColumn) {
                    $payload['sector_id'] = $record['sector_id'] ?? null;
                } else {
                    $payload['sector'] = $record['sector'] ?? $record['sector_id'] ?? null;
                }

                $identifier = $hasNameColumn ? ['name' => $name] : ['category_name' => $name];
                Category::query()->updateOrCreate($identifier, $payload);
            }

            fclose($stream);
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Unable to import categories: ' . $e->getMessage());
        }

        return redirect()
            ->back()
            ->with('success', 'Categories imported successfully.');
    }
}

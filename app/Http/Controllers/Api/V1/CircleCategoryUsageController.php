<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel2;
use App\Models\CircleCategoryLevel3;
use App\Models\CircleCategoryLevel4;
use App\Models\JoinedCircleCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CircleCategoryUsageController extends Controller
{
    public function circleCategoryTree(string $circleId): JsonResponse
    {
        $circle = Circle::query()->select(['id', 'name'])->where('id', $circleId)->first();

        if (! $circle) {
            return response()->json([
                'success' => false,
                'message' => 'Circle not found.',
                'data' => null,
            ], 404);
        }

        $mainCategoryId = $this->resolveCircleMainCategoryId($circle->id);

        if (! $mainCategoryId) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => [
                    'circle' => [
                        'id' => $circle->id,
                        'name' => $circle->name,
                    ],
                    'category' => null,
                    'children' => [],
                ],
            ]);
        }

        $mainCategory = CircleCategory::query()
            ->select(['id', 'name', 'slug', 'circle_key'])
            ->where('id', $mainCategoryId)
            ->first();

        if (! $mainCategory) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => [
                    'circle' => [
                        'id' => $circle->id,
                        'name' => $circle->name,
                    ],
                    'category' => null,
                    'children' => [],
                ],
            ]);
        }

        $level2 = CircleCategoryLevel2::query()
            ->where('circle_category_id', $mainCategory->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $level2Ids = $level2->pluck('id')->values();

        $level3 = $level2Ids->isEmpty()
            ? collect()
            : CircleCategoryLevel3::query()
                ->whereIn('level2_id', $level2Ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'level2_id']);

        $level3Ids = $level3->pluck('id')->values();

        $level4 = $level3Ids->isEmpty()
            ? collect()
            : CircleCategoryLevel4::query()
                ->whereIn('level3_id', $level3Ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'level3_id']);

        $level4ByLevel3 = [];
        foreach ($level4 as $row) {
            $parentId = (int) ($row->level3_id ?? 0);
            if ($parentId <= 0) {
                continue;
            }

            $level4ByLevel3[$parentId][] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 4,
            ];
        }

        $level3ByLevel2 = [];
        foreach ($level3 as $row) {
            $parentId = (int) ($row->level2_id ?? 0);
            if ($parentId <= 0) {
                continue;
            }

            $level3ByLevel2[$parentId][] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 3,
                'children' => $level4ByLevel3[$row->id] ?? [],
            ];
        }

        $children = [];
        foreach ($level2 as $row) {
            $children[] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 2,
                'children' => $level3ByLevel2[$row->id] ?? [],
            ];
        }

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'circle' => [
                    'id' => $circle->id,
                    'name' => $circle->name,
                ],
                'category' => [
                    'id' => $mainCategory->id,
                    'name' => $mainCategory->name,
                    'slug' => $mainCategory->slug,
                    'circle_key' => $mainCategory->circle_key,
                ],
                'children' => $children,
            ],
        ]);
    }

    public function memberSelectedCategories(string $memberId): JsonResponse
    {
        $member = User::query()->select(['id'])->where('id', $memberId)->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'data' => null,
            ], 404);
        }

        if (! Schema::hasTable('joined_circle_categories')) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => ['items' => []],
            ]);
        }

        $rows = JoinedCircleCategory::query()
            ->where('user_id', $member->id)
            ->with([
                'circle:id,name',
                'level1Category:id,name',
                'level2Category:id,name',
                'level3Category:id,name',
                'level4Category:id,name',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $items = $rows->map(function (JoinedCircleCategory $row): array {
            return [
                'circle' => [
                    'id' => $row->circle_id,
                    'name' => $row->circle?->name,
                ],
                'level1_category' => $row->level1Category
                    ? ['id' => $row->level1Category->id, 'name' => $row->level1Category->name]
                    : null,
                'level2_category' => $row->level2Category
                    ? ['id' => $row->level2Category->id, 'name' => $row->level2Category->name]
                    : null,
                'level3_category' => $row->level3Category
                    ? ['id' => $row->level3Category->id, 'name' => $row->level3Category->name]
                    : null,
                'level4_category' => $row->level4Category
                    ? ['id' => $row->level4Category->id, 'name' => $row->level4Category->name]
                    : null,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    public function memberAvailableCategories(Request $request, string $memberId): JsonResponse
    {
        $member = User::query()->select(['id'])->where('id', $memberId)->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
                'data' => null,
            ], 404);
        }

        $circleId = (string) $request->query('circle_id', '');
        if ($circleId === '') {
            return response()->json([
                'success' => false,
                'message' => 'circle_id is required.',
                'data' => null,
            ], 422);
        }

        $circle = Circle::query()->select(['id', 'name'])->where('id', $circleId)->first();
        if (! $circle) {
            return response()->json([
                'success' => false,
                'message' => 'Circle not found.',
                'data' => null,
            ], 404);
        }

        $mainCategoryId = $this->resolveCircleMainCategoryId($circle->id);
        if (! $mainCategoryId) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => [
                    'circle' => [
                        'id' => $circle->id,
                        'name' => $circle->name,
                    ],
                    'level1_category' => null,
                    'available_categories' => [],
                ],
            ]);
        }

        $mainCategory = CircleCategory::query()
            ->select(['id', 'name'])
            ->where('id', $mainCategoryId)
            ->first();

        $selectedLevel4Id = 0;
        if (Schema::hasTable('joined_circle_categories')) {
            $selectedLevel4Id = (int) (JoinedCircleCategory::query()
                ->where('user_id', $member->id)
                ->where('circle_id', $circle->id)
                ->latest('updated_at')
                ->value('level4_category_id') ?? 0);
        }

        $level2 = CircleCategoryLevel2::query()
            ->where('circle_category_id', $mainCategoryId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $level2Ids = $level2->pluck('id')->values();

        $level3 = $level2Ids->isEmpty()
            ? collect()
            : CircleCategoryLevel3::query()
                ->whereIn('level2_id', $level2Ids)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'level2_id']);

        $level3Ids = $level3->pluck('id')->values();

        $level4Query = CircleCategoryLevel4::query()
            ->whereIn('level3_id', $level3Ids)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($selectedLevel4Id > 0) {
            $level4Query->where('id', '!=', $selectedLevel4Id);
        }

        $level4 = $level3Ids->isEmpty()
            ? collect()
            : $level4Query->get(['id', 'name', 'level3_id']);

        $level4ByLevel3 = [];
        foreach ($level4 as $row) {
            $parentId = (int) ($row->level3_id ?? 0);
            if ($parentId <= 0) {
                continue;
            }

            $level4ByLevel3[$parentId][] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 4,
            ];
        }

        $level3ByLevel2 = [];
        foreach ($level3 as $row) {
            $parentId = (int) ($row->level2_id ?? 0);
            if ($parentId <= 0) {
                continue;
            }

            $level3ByLevel2[$parentId][] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 3,
                'children' => $level4ByLevel3[$row->id] ?? [],
            ];
        }

        $availableCategories = [];
        foreach ($level2 as $row) {
            $availableCategories[] = [
                'id' => $row->id,
                'name' => $row->name,
                'level' => 2,
                'children' => $level3ByLevel2[$row->id] ?? [],
            ];
        }

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'circle' => [
                    'id' => $circle->id,
                    'name' => $circle->name,
                ],
                'level1_category' => $mainCategory
                    ? ['id' => $mainCategory->id, 'name' => $mainCategory->name]
                    : null,
                'available_categories' => $availableCategories,
            ],
        ]);
    }

    private function resolveCircleMainCategoryId(string $circleId): ?int
    {
        if (! Schema::hasTable('circle_category_mappings')) {
            return null;
        }

        $id = DB::table('circle_category_mappings')
            ->where('circle_id', $circleId)
            ->orderBy('id')
            ->value('category_id');

        return $id ? (int) $id : null;
    }
}

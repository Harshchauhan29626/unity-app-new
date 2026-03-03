<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Support\UserOptionLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CirclePeersController extends Controller
{
    public function peerOptions(Request $request, Circle $circle): JsonResponse
    {
        $allowedCircleIds = $request->attributes->get('allowed_circle_ids');

        if (is_array($allowedCircleIds) && ! in_array($circle->id, $allowedCircleIds, true)) {
            abort(403);
        }

        $queryString = trim((string) $request->query('q', ''));

        $hasName = Schema::hasColumn('users', 'name');
        $hasDisplayName = Schema::hasColumn('users', 'display_name');
        $hasCompanyName = Schema::hasColumn('users', 'company_name');
        $hasCompany = Schema::hasColumn('users', 'company');
        $hasCity = Schema::hasColumn('users', 'city');

        $nameExpr = $hasName
            ? 'users.name'
            : ($hasDisplayName
                ? 'users.display_name'
                : "TRIM(CONCAT_WS(' ', COALESCE(users.first_name, ''), COALESCE(users.last_name, '')))"
            );

        $companyExpr = $hasCompanyName
            ? 'users.company_name'
            : ($hasCompany ? 'users.company' : "''");

        $cityExpr = $hasCity ? 'users.city' : "''";

        $rows = DB::table('users')
            ->whereNull('users.deleted_at')
            ->whereNotIn('users.id', function ($subQuery) use ($circle): void {
                $subQuery->select('user_id')
                    ->from('circle_members')
                    ->where('circle_id', $circle->id)
                    ->whereNull('deleted_at');
            })
            ->when($queryString !== '', function ($query) use ($queryString, $nameExpr, $companyExpr, $cityExpr): void {
                $like = "%{$queryString}%";

                $query->where(function ($searchQuery) use ($like, $nameExpr, $companyExpr, $cityExpr): void {
                    $searchQuery->whereRaw("{$nameExpr} ILIKE ?", [$like])
                        ->orWhere('users.email', 'ILIKE', $like)
                        ->orWhereRaw("COALESCE({$companyExpr}, '') ILIKE ?", [$like])
                        ->orWhereRaw("COALESCE({$cityExpr}, '') ILIKE ?", [$like]);
                });
            })
            ->selectRaw(
                "users.id,
                {$nameExpr} as name,
                COALESCE({$companyExpr}, '') as company,
                COALESCE({$cityExpr}, '') as city,
                COALESCE((
                    SELECT c.name
                    FROM circle_members cm
                    JOIN circles c ON c.id = cm.circle_id
                    WHERE cm.user_id = users.id
                      AND cm.deleted_at IS NULL
                    ORDER BY cm.created_at DESC
                    LIMIT 1
                ), '') as circle"
            )
            ->orderByRaw("{$nameExpr} ASC")
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $rows
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'text' => UserOptionLabel::makeFromRow((array) $row),
                ])
                ->values(),
        ]);
    }
}

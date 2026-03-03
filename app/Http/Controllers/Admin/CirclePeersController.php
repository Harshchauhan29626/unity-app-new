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
        $hasBusinessName = Schema::hasColumn('users', 'business_name');
        $hasCity = Schema::hasColumn('users', 'city');

        $nameExpr = $hasName
            ? 'users.name'
            : ($hasDisplayName
                ? 'users.display_name'
                : "TRIM(CONCAT_WS(' ', COALESCE(users.first_name, ''), COALESCE(users.last_name, '')))"
            );

        $companyExpr = $hasCompanyName
            ? 'users.company_name'
            : ($hasCompany
                ? 'users.company'
                : ($hasBusinessName ? 'users.business_name' : "''")
            );

        $cityExpr = $hasCity ? 'users.city' : "''";

        $rows = DB::table('users')
            ->leftJoin('circle_members as cm2', function ($join): void {
                $join->on('cm2.user_id', '=', 'users.id')
                    ->where('cm2.status', '=', 'approved')
                    ->whereNull('cm2.deleted_at');
            })
            ->leftJoin('circles as c2', 'c2.id', '=', 'cm2.circle_id')
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
            ->groupBy('users.id')
            ->selectRaw(
                "users.id,
                {$nameExpr} as name,
                users.email,
                COALESCE({$companyExpr}, '') as company,
                COALESCE({$cityExpr}, '') as city,
                COALESCE(string_agg(DISTINCT c2.name, ' | ') FILTER (WHERE c2.name IS NOT NULL), '') as circles"
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

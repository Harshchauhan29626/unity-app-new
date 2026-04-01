<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if ($search === '') {
            return response()->json([]);
        }

        $users = User::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($search): void {
                $query->where('display_name', 'ILIKE', "%{$search}%")
                    ->orWhere('first_name', 'ILIKE', "%{$search}%")
                    ->orWhere('last_name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('company_name', 'ILIKE', "%{$search}%")
                    ->orWhere('city', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
            })
            ->with(['circleMembers' => function ($query) {
                $query->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }])
            ->orderByRaw("COALESCE(NULLIF(display_name,''), NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)),''), email) ASC")
            ->limit(10)
            ->get(['id', 'display_name', 'first_name', 'last_name', 'email', 'company_name', 'company', 'business_name', 'city']);

        if ($users->isEmpty()) {
            Log::info('admin.users.search.no_results', [
                'search' => $search,
            ]);
        }

        $results = $users->map(function (User $user): array {
            [$name, $company, $city, $circle] = $user->adminDisplayParts();

            return [
                'id' => $user->id,
                'name' => $name,
                'company' => $company,
                'city' => $city,
                'circle' => $circle,
                'label' => $user->adminDisplayLabel(),
                'label_inline' => $user->adminDisplayInlineLabel(),
            ];
        })->values();

        return response()->json($results);
    }
}

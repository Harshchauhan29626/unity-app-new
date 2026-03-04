<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        $roleKeys = $user->roles()->pluck('key')->all();

        if (! in_array('global_admin', $roleKeys, true) && empty(array_intersect($roleKeys, ['industry_director', 'ded', 'circle_leader']))) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}

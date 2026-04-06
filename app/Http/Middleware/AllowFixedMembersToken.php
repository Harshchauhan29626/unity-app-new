<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowFixedMembersToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization');

        if (! is_string($authorization) || ! str_starts_with($authorization, 'Bearer ')) {
            return $this->unauthorized();
        }

        $providedToken = substr($authorization, 7);
        $fixedToken = (string) config(
            'services.members_with_circles.fixed_token',
            '302|cO0VMR2dmr9j8c3JtIU9dfkuZfSfvzaCCF1GVxJAdc6fdd2d'
        );

        if (! hash_equals($fixedToken, $providedToken)) {
            return $this->unauthorized();
        }

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 401);
    }
}

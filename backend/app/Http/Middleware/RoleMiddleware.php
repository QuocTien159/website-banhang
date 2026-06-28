<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user || !$user->hasRole(...$roles)) {
            return response()->json([
                'message' => 'Bạn không có quyền thực hiện chức năng này.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->vai_tro) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập tài nguyên này.',
            ], 403);
        }

        return $next($request);
    }
}

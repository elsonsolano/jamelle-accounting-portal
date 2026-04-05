<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = config('app.api_key');

        if (! $key || $request->bearerToken() !== $key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

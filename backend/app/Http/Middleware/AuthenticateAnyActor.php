<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAnyActor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Try to authenticate using any of the available multi-actor guards
        $guards = ['sanctum', 'specialist', 'portal'];

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Set the default guard for this request so auth()->user() works correctly
                Auth::shouldUse($guard);
                return $next($request);
            }
        }

        // If none of the guards successfully authenticated the request
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}

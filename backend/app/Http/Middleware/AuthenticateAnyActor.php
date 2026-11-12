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
     * Checks whether the request is authenticated by any of the supported
     * guard drivers (sanctum, specialist, portal). When exactly one guard has
     * a valid token, that guard becomes the default for the request so that
     * $request->user() / auth()->user() resolves the intended actor.
     *
     * When multiple guards have valid tokens simultaneously, the default guard
     * is intentionally left unchanged. This prevents actor confusion where a
     * user holding tokens for multiple guard types could be resolved to the
     * wrong actor type by controllers using $request->user(). Route-specific
     * middleware (auth:sanctum, auth:specialist, auth:portal) and controllers
     * that need a specific actor should use explicit guard resolution
     * (e.g. auth('specialist')->user()).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guards = ['sanctum', 'specialist', 'portal'];
        $authenticatedGuards = [];

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $authenticatedGuards[] = $guard;
            }
        }

        if (empty($authenticatedGuards)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Only set the default guard when exactly one guard is authenticated.
        // Multiple simultaneous tokens are ambiguous — do not silently pick one.
        if (count($authenticatedGuards) === 1) {
            Auth::shouldUse($authenticatedGuards[0]);
        }

        return $next($request);
    }
}

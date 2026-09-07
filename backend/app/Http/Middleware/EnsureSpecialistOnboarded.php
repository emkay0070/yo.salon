<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSpecialistOnboarded
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if (!$account || !$account->onboarding_completed_at) {
            return response()->json([
                'message' => 'Specialist onboarding is required.',
                'code' => 'SPECIALIST_ONBOARDING_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}

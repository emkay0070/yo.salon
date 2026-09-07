<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSpecialistVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if (!$account || !$account->specialist) {
            return response()->json([
                'message' => 'Specialist profile not found.',
                'code' => 'SPECIALIST_NOT_FOUND',
            ], 404);
        }

        if (!$account->specialist->isVerified()) {
            return response()->json([
                'message' => 'Verified specialist required.',
                'code' => 'VERIFIED_SPECIALIST_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}

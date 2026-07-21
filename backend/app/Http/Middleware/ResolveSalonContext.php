<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResolveSalonContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            
            // Handle onboarding status - redirect to onboarding if not completed
            if ($user->isOnboarding()) {
                // Allow onboarding endpoints to pass through
                if ($request->is('api/v1/onboarding/*') || $request->is('api/v1/membership/plans')) {
                    return $next($request);
                }
                
                return response()->json([
                    'message' => 'Onboarding required',
                    'status' => $user->status,
                    'redirect' => '/onboarding',
                ], 403);
            }
            
            // For active users, ensure they have a salon
            if ($user->isActive()) {
                $currentSalon = $user->currentSalon();
                if (!$currentSalon) {
                    return response()->json([
                        'message' => 'User is not associated with a salon',
                    ], 403);
                }
                
                // Inject salon context into request for controllers to use
                $request->attributes->set('salon_id', $currentSalon->id);
            }
            
            // For portal users, they access through customer relationship
            // Salon context is resolved through the customer relationship
        }

        return $next($request);
    }
}

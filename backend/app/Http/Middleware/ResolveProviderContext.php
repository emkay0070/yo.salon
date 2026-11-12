<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResolveProviderContext
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
        // Skip context resolution for specific endpoints
        if ($request->is('api/v1/check-slug') || $request->is('api/v1/providers/check-slug') || $request->is('api/v1/locations/*/staff')) {
            return $next($request);
        }

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
            
            // For active users, ensure they have a provider
            if ($user->isActive()) {
                $currentProvider = $user->currentProvider();
                if (!$currentProvider) {
                    return response()->json([
                        'message' => 'User is not associated with a provider',
                    ], 403);
                }
                
                // Inject provider context into request for controllers to use
                $request->attributes->set('provider', $currentProvider);
                $request->attributes->set('provider_id', $currentProvider->id);

                // Location context is now resolved by ResolveLocationContext middleware
                // Do not auto-inject location_id based on provider.location_id
                // Location must be explicitly requested via X-Location-Id header or location_id param
            }
            
            // For portal users, they access through customer relationship
            // Provider/Location context is resolved through the customer relationship
        }

        return $next($request);
    }
}

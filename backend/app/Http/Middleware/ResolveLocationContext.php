<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveLocationContext
{
    /**
     * Handle an incoming request.
     *
     * Canonical authorization chain:
     * authenticated user → current Provider → active Location → verify Location belongs to Provider → verify user access
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $providerId = $request->attributes->get('provider_id');
        
        // Location context is only valid within a Provider context
        if (!$providerId) {
            return $next($request);
        }

        // Resolve location_id from explicit sources (header, query param, or request body)
        // Priority: X-Location-Id header > location_id query param > location_id in request body
        $locationId = $request->header('X-Location-Id')
            ?? $request->query('location_id')
            ?? $request->input('location_id');

        // Special case: "all" or null means Provider-wide context (no Location filtering)
        if ($locationId === 'all' || $locationId === null) {
            $request->attributes->set('location_id', null);
            $request->attributes->set('location_scope', 'all');
            
            // Enforce: location-scoped users cannot request provider-wide scope
            $authorityService = app(\App\Services\ProviderUserAuthorityService::class);
            if (!$authorityService->validateLocationScope($providerId, null)) {
                return response()->json([
                    'message' => 'Location-scoped users must specify a permitted location',
                ], Response::HTTP_FORBIDDEN);
            }
            
            return $next($request);
        }

        // Validate the Location exists and belongs to the current Provider
        $location = \App\Models\Location::where('id', $locationId)
            ->where('provider_id', $providerId)
            ->first();

        if (!$location) {
            return response()->json([
                'message' => 'Location not found or does not belong to this Provider',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate user has access to this specific location based on authority_scope
        $authorityService = app(\App\Services\ProviderUserAuthorityService::class);
        if (!$authorityService->validateLocationScope($providerId, $locationId)) {
            return response()->json([
                'message' => 'You do not have access to this location',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Inject validated location context
        $request->attributes->set('location_id', $location->id);
        $request->attributes->set('location', $location);
        $request->attributes->set('location_scope', 'specific');

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolvePortalContext
{
    /**
     * Handle an incoming request for portal routes.
     * Resolves provider context through customer-location relationship.
     * Provider can be specified via query parameter or header, defaults to customer's first location's provider.
     *
     * New customers who have not yet visited any location will pass through without a location context
     * so they can browse and book for the first time.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('portal')->check()) {
            return $next($request);
        }

        $portalAccount = Auth::guard('portal')->user();
        $customer = $portalAccount->customer;

        // No linked customer profile yet — let them through
        if (!$customer) {
            return $next($request);
        }

        // 1. Explicit location_id from the request is respected.
        // 2. X-Location-Id is supported only when explicitly supplied by the caller.
        // 3. Legacy salon_id is accepted for compatibility.
        $locationId = $request->query('location_id') ?? $request->header('X-Location-Id')
                     ?? $request->query('salon_id') ?? $request->header('X-Salon-Id');

        $providerId = null;

        if ($locationId) {
            // Resolve the location and verify it belongs to a provider
            // the customer is authorized to access through the customer_providers pivot.
            $location = \App\Models\Location::where('id', $locationId)
                ->whereHas('provider', function ($query) use ($customer) {
                    $query->whereIn('id', $customer->providers()->pluck('providers.id'));
                })
                ->first();

            if (!$location) {
                // Invalid location ID, or customer is not authorized to access
                // this location's provider. Do not inject any provider context.
                return response()->json([
                    'message' => 'Location not found or not accessible to this customer.',
                ], Response::HTTP_FORBIDDEN);
            }

            $providerId = $location->provider_id;
        }

        // Inject validated context into request for controllers to use
        $request->attributes->set('provider_id', $providerId);
        $request->attributes->set('location_id', $locationId);
        $request->attributes->set('customer_id', $customer->id);
        $request->attributes->set('portal_account_id', $portalAccount->id);

        return $next($request);
    }
}

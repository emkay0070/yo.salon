<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            // 4. A supplied location ID may resolve to any valid location;
            // it must NOT require that the customer has previously visited that location.
            $location = \App\Models\Location::find($locationId);
            if ($location) {
                $providerId = $location->provider_id;
            } else {
                // If an invalid location ID is passed, treat it as no context
                $locationId = null;
            }
        }

        // 5. Do NOT fall back to $locations->first().
        // 6. Set location_id and provider_id to null when no location context exists.

        // Inject context into request for controllers to use
        $request->attributes->set('provider_id', $providerId);
        $request->attributes->set('location_id', $locationId);
        $request->attributes->set('customer_id', $customer->id);
        $request->attributes->set('portal_account_id', $portalAccount->id);

        return $next($request);
    }
}

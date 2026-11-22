<?php

namespace App\Http\Middleware;

use App\Models\Location;
use App\Models\Provider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolvePortalContext
{
    /**
     * Handle an incoming request for portal routes.
     *
     * Customers are platform-global: they may book any active provider, not
     * only providers they have previously visited. The provider/location
     * context therefore validates that the requested location belongs to the
     * requested (or resolved) provider, rather than requiring a pre-existing
     * customer_providers relationship. That relationship is created by the
     * first booking.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('portal')->check()) {
            return $next($request);
        }

        $portalAccount = Auth::guard('portal')->user();
        $customer = $portalAccount->customer;

        // No linked customer profile yet - let them through.
        if (!$customer) {
            return $next($request);
        }

        // 1. Explicit provider_id / X-Provider-Id.
        // 2. Explicit location_id / X-Location-Id (legacy salon_id accepted).
        $providerId = $request->query('provider_id') ?? $request->header('X-Provider-Id');
        $locationId = $request->query('location_id') ?? $request->header('X-Location-Id')
                     ?? $request->query('salon_id') ?? $request->header('X-Salon-Id');

        if ($locationId) {
            $location = Location::find($locationId);

            if (!$location) {
                return response()->json([
                    'message' => 'Location not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            if ($providerId && $location->provider_id !== $providerId) {
                return response()->json([
                    'message' => 'Location does not belong to the requested provider.',
                ], Response::HTTP_FORBIDDEN);
            }

            $providerId = $location->provider_id;
        }

        if ($providerId) {
            $provider = Provider::find($providerId);

            if (!$provider) {
                return response()->json([
                    'message' => 'Provider not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$provider->active || in_array($provider->status, ['inactive', 'suspended'], true)) {
                return response()->json([
                    'message' => 'This provider is not currently accepting bookings.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Inject validated context into request for controllers to use.
        $request->attributes->set('provider_id', $providerId);
        $request->attributes->set('location_id', $locationId);
        $request->attributes->set('customer_id', $customer->id);
        $request->attributes->set('portal_account_id', $portalAccount->id);

        return $next($request);
    }
}

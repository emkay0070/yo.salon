<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResolvePortalContext
{
    /**
     * Handle an incoming request for portal routes.
     * Resolves salon context through customer-salon relationship.
     * Salon can be specified via query parameter or header, defaults to customer's first salon.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if portal user is authenticated
        if (Auth::guard('portal')->check()) {
            $portalAccount = Auth::guard('portal')->user();

            // Get the customer through the portal account relationship
            $customer = $portalAccount->customer;

            if (!$customer) {
                return response()->json([
                    'message' => 'Portal account is not linked to a customer',
                ], 403);
            }

            // Get customer's salons
            $salons = $customer->salons;

            if ($salons->isEmpty()) {
                return response()->json([
                    'message' => 'Customer is not associated with any salon',
                ], 403);
            }

            // Resolve salon_id from query parameter, header, or default to first salon
            $salonId = $request->query('salon_id')
                ?? $request->header('X-Salon-Id')
                ?? $salons->first()->id;

            // Verify customer has access to this salon
            $salon = $salons->firstWhere('id', $salonId);

            if (!$salon) {
                return response()->json([
                    'message' => 'Customer does not have access to this salon',
                    'available_salons' => $salons->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug]),
                ], 403);
            }

            // Inject salon context into request for controllers to use
            $request->attributes->set('salon_id', $salonId);
            $request->attributes->set('customer_id', $customer->id);
            $request->attributes->set('portal_account_id', $portalAccount->id);
        }

        return $next($request);
    }
}

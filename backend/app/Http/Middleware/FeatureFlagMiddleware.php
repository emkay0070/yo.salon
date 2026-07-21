<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CapabilityService;
use App\Models\Salon;

class FeatureFlagMiddleware
{
    protected CapabilityService $capabilityService;

    public function __construct(CapabilityService $capabilityService)
    {
        $this->capabilityService = $capabilityService;
    }

    /**
     * Handle an incoming request.
     * Checks if the specified feature is enabled for the salon.
     * Returns 403 if feature not enabled.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  $feature  The feature key to check
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $feature)
    {
        $salonId = $request->attributes->get('salon_id');

        if (!$salonId) {
            return response()->json([
                'message' => 'Salon context not found',
            ], 400);
        }

        $salon = Salon::find($salonId);
        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found',
            ], 404);
        }

        if (!$this->capabilityService->allows($salon, $feature)) {
            return response()->json([
                'message' => "Feature '{$feature}' is not enabled for this salon",
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}

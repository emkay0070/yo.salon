<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CapabilityService;
use App\Models\Provider;

class FeatureFlagMiddleware
{
    protected CapabilityService $capabilityService;

    public function __construct(CapabilityService $capabilityService)
    {
        $this->capabilityService = $capabilityService;
    }

    /**
     * Handle an incoming request.
     * Checks if the specified feature is enabled for the provider.
     * Returns 403 if feature not enabled.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  $feature  The feature key to check
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $feature)
    {
        $providerId = $request->attributes->get('provider_id');

        if (!$providerId) {
            return response()->json([
                'message' => 'Provider context not found',
            ], 400);
        }

        $provider = Provider::find($providerId);
        if (!$provider) {
            return response()->json([
                'message' => 'Provider not found',
            ], 404);
        }

        if (!$this->capabilityService->allows($provider, $feature)) {
            return response()->json([
                'message' => "Feature '{$feature}' is not enabled for this provider",
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}

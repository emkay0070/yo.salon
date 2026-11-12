<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CapabilityService;
use App\Models\Provider;

class CapabilityMiddleware
{
    protected CapabilityService $capabilityService;

    public function __construct(CapabilityService $capabilityService)
    {
        $this->capabilityService = $capabilityService;
    }

    /**
     * Handle an incoming request.
     * Evaluates capability policies and auto-enables features when conditions are met.
     * Returns feature availability in response headers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $providerId = $request->attributes->get('provider_id');

        if ($providerId) {
            $provider = Provider::find($providerId);
            
            if ($provider) {
                // Get available features for the provider
                $availableFeatures = $this->capabilityService->getAvailableFeatures($provider);
                
                // Get suggested features based on provider metrics
                $suggestedFeatures = $this->capabilityService->suggestFeatures($provider);

                // Add to request attributes for controllers to use
                $request->attributes->set('available_features', $availableFeatures);
                $request->attributes->set('suggested_features', $suggestedFeatures);

                // Add to response headers
                $response = $next($request);
                
                if (method_exists($response, 'header')) {
                    $response->header('X-Available-Features', implode(',', $availableFeatures));
                    $response->header('X-Suggested-Features', implode(',', array_column($suggestedFeatures, 'feature')));
                }

                return $response;
            }
        }

        return $next($request);
    }
}

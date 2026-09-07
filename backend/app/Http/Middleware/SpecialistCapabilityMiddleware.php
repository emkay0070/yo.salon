<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SpecialistCapabilityResolver;
use App\Models\Specialist;
use Symfony\Component\HttpFoundation\Response;

class SpecialistCapabilityMiddleware
{
    protected SpecialistCapabilityResolver $capabilityResolver;

    public function __construct(SpecialistCapabilityResolver $capabilityResolver)
    {
        $this->capabilityResolver = $capabilityResolver;
    }

    /**
     * Handle an incoming request.
     * Checks if the specialist has access to the required capability.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  $capabilityCode The capability code to check (e.g., 'SPECIALIST_INTELLIGENCE')
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $capabilityCode)
    {
        $specialist = $request->user('specialist')?->specialist;

        if (!$specialist) {
            return response()->json([
                'message' => 'Specialist not authenticated',
            ], 401);
        }

        // Check if specialist has access to the required capability
        if (!$this->capabilityResolver->allows($specialist->id, $capabilityCode)) {
            return response()->json([
                'message' => 'This feature requires a Pro subscription',
                'capability' => $capabilityCode,
                'required_plan' => 'Pro',
            ], 403);
        }

        return $next($request);
    }
}

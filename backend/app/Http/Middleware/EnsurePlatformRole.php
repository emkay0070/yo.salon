<?php

namespace App\Http\Middleware;

use App\Domain\Platform\PlatformRole;
use App\Models\PortalAccount;
use App\Models\SpecialistAccount;
use App\Models\User;
use App\Services\PlatformAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsurePlatformRole middleware protects administrative endpoints.
 *
 * Verifies that the authenticated actor is a User possessing the required
 * platform governance role or administrative capability.
 *
 * SpecialistAccount and PortalAccount actors are rejected with 403 Forbidden.
 */
class EnsurePlatformRole
{
    protected PlatformAuthorizationService $authService;

    public function __construct(PlatformAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * Usage:
     *   middleware('platform.role:platform_owner,platform_super_admin')
     *   middleware('platform.role:seeder_execution')
     *   middleware('platform.role:invoices_view')
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$requirements): Response
    {
        $user = $request->user();

        // 1. Must be authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // 2. Explicitly forbid non-User identities
        if ($user instanceof SpecialistAccount || $user instanceof PortalAccount) {
            return response()->json([
                'message' => 'Unauthorized: Specialist and customer portal accounts are forbidden from platform administration.',
            ], 403);
        }

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Forbidden: Invalid administrative principal.',
            ], 403);
        }

        // 3. If no specific requirements passed, any valid platform role is required
        if (empty($requirements)) {
            $role = $this->authService->resolvePlatformRole($user);
            if (!$role) {
                return response()->json([
                    'message' => 'Forbidden: Platform administrative privileges required.',
                ], 403);
            }

            return $next($request);
        }

        // 4. Flatten comma-separated requirements
        $parsedRequirements = [];
        foreach ($requirements as $req) {
            foreach (explode(',', $req) as $item) {
                $trimmed = trim($item);
                if (!empty($trimmed)) {
                    $parsedRequirements[] = $trimmed;
                }
            }
        }

        // 5. Check if requirement matches a configured capability
        $configuredCapabilities = array_keys(config('platform.capabilities', []));
        $capabilityChecks = array_intersect($parsedRequirements, $configuredCapabilities);

        if (!empty($capabilityChecks)) {
            foreach ($capabilityChecks as $capability) {
                // Production environment check for seeder execution
                if ($capability === 'seeder_execution' && $this->authService->isSeederExecutionDisabledInCurrentEnvironment()) {
                    return response()->json([
                        'message' => 'Database seeding is disabled in production environments.',
                    ], 403);
                }

                if ($this->authService->canPerform($user, $capability)) {
                    return $next($request);
                }
            }

            return response()->json([
                'message' => 'Forbidden: Insufficient platform permissions for this action.',
            ], 403);
        }

        // 6. Otherwise, check role match
        if ($this->authService->hasPlatformRole($user, $parsedRequirements)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Forbidden: Platform administrative privileges required.',
        ], 403);
    }
}

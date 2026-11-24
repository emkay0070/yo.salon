<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SpecialistAccount;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Support\TenantBootstrap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResolveOrganizationContext
{
    /**
     * Handle an incoming request.
     *
     * Resolves and validates the Organization context for authenticated requests.
     * Supports both User (auth:sanctum) and SpecialistAccount (auth:specialist) principals.
     *
     * Invariants:
     * - If X-Organization-Id is provided, principal MUST have an active membership in it (or 403).
     * - If no header is provided:
     *     - Exactly 1 active membership -> auto-resolves.
     *     - More than 1 active membership -> returns 409 Conflict.
     *     - 0 memberships -> continues without org context (e.g. for onboarding routes).
     * - Injects 'organization', 'organization_id', and 'organization_membership' into request attributes.
     */
    public function handle(Request $request, Closure $next)
    {
        $principal = Auth::guard('sanctum')->user()
            ?? Auth::guard('specialist')->user()
            ?? Auth::user();

        if (!$principal || (!($principal instanceof User) && !($principal instanceof SpecialistAccount))) {
            return $next($request);
        }

        $headerOrgId = $request->header('X-Organization-Id');

        // Tenant discovery is a pre-tenant operation: `organization_memberships`
        // is RLS-protected and therefore unreadable until a context exists.
        // TenantBootstrap performs that single privileged read; every query
        // after this middleware runs on the RLS-scoped connection.
        $memberships = TenantBootstrap::activeMemberships($principal);

        if (!empty($headerOrgId)) {
            // Explicit header provided: principal MUST have an active membership in it
            $membership = $memberships->firstWhere('organization_id', $headerOrgId);

            if (!$membership) {
                return response()->json([
                    'message' => 'Unauthorized organization context.',
                ], 403);
            }

            $organization = TenantBootstrap::organization($membership->organization_id);

            if (!$organization || !$organization->isActive()) {
                return response()->json([
                    'message' => 'Organization is inactive or suspended.',
                ], 403);
            }

            $this->injectContext($request, $organization, $membership);
            return $next($request);
        }

        if ($memberships->count() > 1) {
            return response()->json([
                'message' => 'Multiple organizations found. Organization context required (X-Organization-Id header).',
            ], 409);
        }

        if ($memberships->count() === 1) {
            $membership = $memberships->first();
            $organization = TenantBootstrap::organization($membership->organization_id);

            if ($organization && $organization->isActive()) {
                $this->injectContext($request, $organization, $membership);
            }
        }

        return $next($request);
    }

    /**
     * Inject organization context into request and OrganizationContext singleton.
     */
    protected function injectContext(Request $request, Organization $organization, OrganizationMembership $membership): void
    {
        $request->attributes->set('organization', $organization);
        $request->attributes->set('organization_id', $organization->id);
        $request->attributes->set('organization_membership', $membership);

        if (app()->bound(OrganizationContext::class)) {
            app(OrganizationContext::class)->resolveForOrganization($organization, $membership);
        }
    }
}

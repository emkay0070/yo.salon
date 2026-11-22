<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SpecialistAccount;
use App\Models\User;
use App\Services\OrganizationContext;
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

        if (!empty($headerOrgId)) {
            // Explicit header provided: validate active membership
            $membershipQuery = OrganizationMembership::where('organization_id', $headerOrgId)
                ->where('status', OrganizationMembership::STATUS_ACTIVE);

            if ($principal instanceof User) {
                $membershipQuery->where('user_id', $principal->id);
            } else {
                $membershipQuery->where('specialist_account_id', $principal->id);
            }

            $membership = $membershipQuery->first();

            if (!$membership) {
                return response()->json([
                    'message' => 'Unauthorized organization context.',
                ], 403);
            }

            $organization = $membership->organization;

            if (!$organization || !$organization->isActive()) {
                return response()->json([
                    'message' => 'Organization is inactive or suspended.',
                ], 403);
            }

            $this->injectContext($request, $organization, $membership);
            return $next($request);
        }

        // No header provided: inspect principal's active memberships
        $membershipsQuery = OrganizationMembership::where('status', OrganizationMembership::STATUS_ACTIVE);
        if ($principal instanceof User) {
            $membershipsQuery->where('user_id', $principal->id);
        } else {
            $membershipsQuery->where('specialist_account_id', $principal->id);
        }

        $memberships = $membershipsQuery->get();

        if ($memberships->count() > 1) {
            return response()->json([
                'message' => 'Multiple organizations found. Organization context required (X-Organization-Id header).',
            ], 409);
        }

        if ($memberships->count() === 1) {
            $membership = $memberships->first();
            $organization = $membership->organization;

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

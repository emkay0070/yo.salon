<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrganizationMembership;
use App\Models\SpecialistAccount;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * OrganizationController exposes commercial Organization management.
 * Protected by auth.any (sanctum/specialist) and organization.context.
 */
class OrganizationController extends Controller
{
    protected OrganizationService $organizationService;
    protected OrganizationContext $organizationContext;

    public function __construct(
        OrganizationService $organizationService,
        OrganizationContext $organizationContext
    ) {
        $this->organizationService = $organizationService;
        $this->organizationContext = $organizationContext;
    }

    /**
     * Get the authenticated actor (User or SpecialistAccount).
     */
    protected function getAuthenticatedActor(): User|SpecialistAccount|null
    {
        return Auth::guard('sanctum')->user()
            ?? Auth::guard('specialist')->user()
            ?? Auth::user();
    }

    /**
     * Get the Organization for the authenticated principal's current context.
     */
    public function current(Request $request): JsonResponse
    {
        $this->organizationContext->resolveFromRequest($request);
        $org = $this->organizationContext->getOrganization();

        if (!$org) {
            return response()->json([
                'message' => 'No organization associated with your current context.',
            ], 404);
        }

        return response()->json([
            'organization' => $org->load(['providers.primaryLocation', 'providers.providerType']),
            'membership'   => $this->organizationContext->getMembership(),
            'context'      => $this->organizationContext->toArray(),
        ]);
    }

    /**
     * Get all operational Providers belonging to this Organization.
     */
    public function providers(Request $request): JsonResponse
    {
        $this->organizationContext->resolveFromRequest($request);
        $org = $this->organizationContext->getOrganization();

        if (!$org) {
            return response()->json([
                'message' => 'No organization found.',
            ], 404);
        }

        $providers = $org->providers()
            ->with(['primaryLocation', 'providerType'])
            ->get();

        return response()->json([
            'organization_id' => $org->id,
            'providers'       => $providers,
        ]);
    }

    /**
     * Get all members of this Organization (requires admin authority).
     */
    public function members(Request $request): JsonResponse
    {
        $this->organizationContext->resolveFromRequest($request);
        $org = $this->organizationContext->getOrganization();

        if (!$org) {
            return response()->json(['message' => 'No organization found.'], 404);
        }

        if (!$this->organizationContext->isAdmin()) {
            return response()->json(['message' => 'Insufficient organization authority.'], 403);
        }

        $members = $org->memberships()
            ->with(['user:id,name,email,status', 'specialistAccount.specialist:id,name,email'])
            ->get();

        return response()->json([
            'organization_id' => $org->id,
            'members'         => $members,
        ]);
    }

    /**
     * Invite or add a member to the Organization.
     */
    public function addMember(Request $request): JsonResponse
    {
        $this->organizationContext->resolveFromRequest($request);
        $org = $this->organizationContext->getOrganization();

        if (!$org) {
            return response()->json(['message' => 'No organization found.'], 404);
        }

        if (!$this->organizationContext->isAdmin()) {
            return response()->json(['message' => 'Insufficient organization authority.'], 403);
        }

        $validated = $request->validate([
            'email'                 => 'nullable|email',
            'user_id'               => 'nullable|integer|exists:users,id',
            'specialist_account_id' => 'nullable|uuid|exists:specialist_accounts,id',
            'role'                  => ['required', Rule::in([
                OrganizationMembership::ROLE_ADMIN,
                OrganizationMembership::ROLE_MEMBER,
            ])],
        ]);

        $principal = null;
        if (!empty($validated['user_id'])) {
            $principal = User::find($validated['user_id']);
        } elseif (!empty($validated['specialist_account_id'])) {
            $principal = SpecialistAccount::find($validated['specialist_account_id']);
        } elseif (!empty($validated['email'])) {
            $principal = User::where('email', $validated['email'])->first()
                ?? SpecialistAccount::where('email', $validated['email'])->first();
        }

        if (!$principal) {
            return response()->json(['message' => 'Target user or specialist not found.'], 404);
        }

        $membership = $this->organizationService->addMember(
            $org,
            $principal,
            $validated['role']
        );

        return response()->json([
            'message'    => 'Member added successfully.',
            'membership' => $membership->load(['user:id,name,email,status', 'specialistAccount.specialist:id,name,email']),
        ], 201);
    }

    /**
     * Remove a member from the Organization.
     */
    public function removeMember(Request $request, string $memberIdentifier): JsonResponse
    {
        $this->organizationContext->resolveFromRequest($request);
        $org = $this->organizationContext->getOrganization();

        if (!$org) {
            return response()->json(['message' => 'No organization found.'], 404);
        }

        if (!$this->organizationContext->isAdmin()) {
            return response()->json(['message' => 'Insufficient organization authority.'], 403);
        }

        // Try finding by membership UUID, or user_id (int), or specialist_account_id (uuid)
        $membership = OrganizationMembership::where('organization_id', $org->id)
            ->where(function ($q) use ($memberIdentifier) {
                $q->where('id', $memberIdentifier)
                  ->orWhere('user_id', $memberIdentifier)
                  ->orWhere('specialist_account_id', $memberIdentifier);
            })
            ->first();

        if (!$membership) {
            return response()->json(['message' => 'Member not found in organization.'], 404);
        }

        $principal = $membership->principal;

        try {
            $this->organizationService->removeMember($org, $principal);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Member removed successfully.']);
    }

    /**
     * Transfer designated ownership of the Organization to another active member.
     */
    public function transferOwnership(Request $request): JsonResponse
    {
        $this->organizationContext->resolveFromRequest($request);
        $org = $this->organizationContext->getOrganization();

        if (!$org) {
            return response()->json(['message' => 'No organization found.'], 404);
        }

        $actor = $this->getAuthenticatedActor();
        if (!$actor || !$this->organizationContext->isOwner()) {
            return response()->json(['message' => 'Only the designated Organization owner can transfer ownership.'], 403);
        }

        $validated = $request->validate([
            'new_owner_user_id'               => 'nullable|integer|exists:users,id',
            'new_owner_specialist_account_id' => 'nullable|uuid|exists:specialist_accounts,id',
        ]);

        $newOwner = null;
        if (!empty($validated['new_owner_user_id'])) {
            $newOwner = User::find($validated['new_owner_user_id']);
        } elseif (!empty($validated['new_owner_specialist_account_id'])) {
            $newOwner = SpecialistAccount::find($validated['new_owner_specialist_account_id']);
        }

        if (!$newOwner) {
            return response()->json(['message' => 'New owner must be specified (new_owner_user_id or new_owner_specialist_account_id).'], 422);
        }

        try {
            $this->organizationService->transferOwnership($org, $actor, $newOwner);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'      => 'Organization ownership transferred successfully.',
            'organization' => $org->fresh(['memberships']),
        ]);
    }
}

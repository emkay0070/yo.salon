<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Provider;
use App\Models\SpecialistAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * OrganizationContext resolves the Organization context for the current request.
 *
 * It operates strictly on server-side resolution:
 *  1. Request attributes set by ResolveOrganizationContext middleware
 *  2. Provider resolved by middleware -> Provider's Organization
 *  3. Authenticated User's or SpecialistAccount's active organization
 *
 * It never blindly accepts unauthenticated client-supplied organization IDs.
 */
class OrganizationContext
{
    private ?Organization $organization = null;
    private ?OrganizationMembership $membership = null;
    private ?string $error = null;

    /**
     * Resolve Organization context from HTTP request.
     */
    public function resolveFromRequest(Request $request): self
    {
        $this->organization = null;
        $this->membership = null;
        $this->error = null;

        // Check attributes injected by ResolveOrganizationContext middleware
        if ($request->attributes->has('organization')) {
            $this->organization = $request->attributes->get('organization');
            $this->membership = $request->attributes->get('organization_membership');
            return $this;
        }

        // Check provider attribute
        $provider = $request->attributes->get('provider');
        if (!$provider && $request->attributes->has('provider_id')) {
            $provider = Provider::find($request->attributes->get('provider_id'));
        }

        if ($provider && $provider->organization) {
            $this->organization = $provider->organization;
        }

        $principal = Auth::guard('sanctum')->user()
            ?? Auth::guard('specialist')->user()
            ?? Auth::user();

        if (!$this->organization && $principal) {
            if ($principal instanceof User) {
                $this->organization = $principal->currentOrganization() ?? $principal->ownedOrganizations()->first();
            } elseif ($principal instanceof SpecialistAccount) {
                $this->organization = $principal->currentOrganization() ?? $principal->ownedOrganizations()->first();
            }
        }

        if ($this->organization && $principal) {
            $query = OrganizationMembership::where('organization_id', $this->organization->id)
                ->where('status', OrganizationMembership::STATUS_ACTIVE);

            if ($principal instanceof User) {
                $query->where('user_id', $principal->id);
            } elseif ($principal instanceof SpecialistAccount) {
                $query->where('specialist_account_id', $principal->id);
            }

            $this->membership = $query->first();
        }

        if (!$this->organization) {
            $this->error = 'No organization resolved in current context.';
        }

        return $this;
    }

    /**
     * Resolve for a specific Organization instance.
     */
    public function resolveForOrganization(Organization $organization, ?OrganizationMembership $membership = null): self
    {
        $this->organization = $organization;
        $this->membership = $membership;
        $this->error = null;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function getMembership(): ?OrganizationMembership
    {
        return $this->membership;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function isOwner(): bool
    {
        return $this->membership?->isOwner() ?? false;
    }

    public function isAdmin(): bool
    {
        return $this->membership?->isAdmin() ?? false;
    }

    public function isMember(): bool
    {
        return $this->membership?->isActive() ?? false;
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organization?->id,
            'name'            => $this->organization?->name,
            'slug'            => $this->organization?->slug,
            'type'            => $this->organization?->type,
            'status'          => $this->organization?->status,
            'role'            => $this->membership?->role,
            'is_owner'        => $this->isOwner(),
            'is_admin'        => $this->isAdmin(),
            'error'           => $this->error,
        ];
    }
}

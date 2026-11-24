<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Provider;
use App\Models\SpecialistAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Support\RlsContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * OrganizationService handles the lifecycle, membership, and association of
 * Organizations as the commercial and membership owner in TYBUEL.
 *
 * Distinct from ProviderUser operational relationships.
 * Supports dual-principal membership and ownership (User and SpecialistAccount).
 */
class OrganizationService
{
    /**
     * Create a business Organization for a business owner User without requiring a Provider yet.
     */
    public function createForBusinessOwner(User $user, array $data = []): Organization
    {
        return DB::transaction(function () use ($user, $data) {
            $name = $data['name'] ?? 'New Organization';
            $slug = $this->generateUniqueSlug($name);

            $org = $this->createOrganizationUnderRls([
                'name'                        => $name,
                'slug'                        => $slug,
                'type'                        => Organization::TYPE_BUSINESS,
                'status'                      => Organization::STATUS_ACTIVE,
                'email'                       => $data['email'] ?? $user->email,
                'phone'                       => $data['phone'] ?? $user->phone,
                'description'                 => $data['description'] ?? null,
                'metadata'                    => $data['metadata'] ?? null,
                'owner_user_id'               => $user->id,
                'owner_specialist_account_id' => null,
            ]);

            // Establish org_owner membership for the creator
            OrganizationMembership::firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'user_id'         => $user->id,
                ],
                [
                    'specialist_account_id' => null,
                    'role'                  => OrganizationMembership::ROLE_OWNER,
                    'status'                => OrganizationMembership::STATUS_ACTIVE,
                    'accepted_at'           => now(),
                ]
            );

            return $org;
        });
    }

    /**
     * Create a personal Organization for an independent specialist principal without requiring a Provider yet.
     */
    public function createPersonalForSpecialistPrincipal(SpecialistAccount|User $principal, array $data = []): Organization
    {
        return DB::transaction(function () use ($principal, $data) {
            $isSpecialist = $principal instanceof SpecialistAccount;

            $name = $data['name'] ?? 'Independent Specialist';
            $slug = $this->generateUniqueSlug($name . '-personal');

            $org = $this->createOrganizationUnderRls([
                'name'                        => $name,
                'slug'                        => $slug,
                'type'                        => Organization::TYPE_PERSONAL,
                'status'                      => Organization::STATUS_ACTIVE,
                'email'                       => $data['email'] ?? $principal->email,
                'phone'                       => $data['phone'] ?? $principal->phone,
                'description'                 => $data['description'] ?? null,
                'owner_user_id'               => $isSpecialist ? null : $principal->id,
                'owner_specialist_account_id' => $isSpecialist ? $principal->id : null,
            ]);

            OrganizationMembership::firstOrCreate(
                [
                    'organization_id'       => $org->id,
                    'user_id'               => $isSpecialist ? null : $principal->id,
                    'specialist_account_id' => $isSpecialist ? $principal->id : null,
                ],
                [
                    'role'        => OrganizationMembership::ROLE_OWNER,
                    'status'      => OrganizationMembership::STATUS_ACTIVE,
                    'accepted_at' => now(),
                ]
            );

            return $org;
        });
    }

    /**
     * Create a business Organization during business-owner onboarding.
     * Associates the Provider and grants the User an org_owner membership.
     */
    public function createForBusiness(User $user, Provider $provider, array $data = []): Organization
    {
        $data['name'] = $data['name'] ?? $provider->display_name ?? 'New Organization';
        $data['email'] = $data['email'] ?? $provider->email;
        $data['phone'] = $data['phone'] ?? $provider->phone;
        $data['description'] = $data['description'] ?? $provider->description;

        $org = $this->createForBusinessOwner($user, $data);

        $provider->update(['organization_id' => $org->id]);

        if ($provider->subscription) {
            $provider->subscription->update(['organization_id' => $org->id]);
        }

        return $org;
    }

    /**
     * Create a lightweight personal Organization for an independent specialist.
     * Guarantees every independent Provider exists within the Organization hierarchy.
     * Supports both SpecialistAccount and User principals.
     */
    public function createPersonalForSpecialist(SpecialistAccount|User $principal, Provider $provider, array $data = []): Organization
    {
        if ($provider->organization_id) {
            $existing = Organization::find($provider->organization_id);
            if ($existing) {
                if (!$existing->hasMember($principal)) {
                    $this->addMember($existing, $principal, OrganizationMembership::ROLE_OWNER);
                }
                return $existing;
            }
        }

        $data['name'] = $data['name'] ?? $provider->display_name ?? 'Independent Specialist';
        $data['email'] = $data['email'] ?? $provider->email;
        $data['phone'] = $data['phone'] ?? $provider->phone;
        $data['description'] = $data['description'] ?? $provider->description;

        $org = $this->createPersonalForSpecialistPrincipal($principal, $data);

        $provider->update(['organization_id' => $org->id]);

        if ($provider->subscription) {
            $provider->subscription->update(['organization_id' => $org->id]);
        }

        return $org;
    }

    /**
     * Add a principal (User or SpecialistAccount) to an Organization with a specific governance role.
     * Does NOT alter ProviderUser (operational assignment).
     */
    public function addMember(Organization $organization, User|SpecialistAccount $principal, string $role = OrganizationMembership::ROLE_MEMBER): OrganizationMembership
    {
        $match = [
            'organization_id' => $organization->id,
            'user_id'         => $principal instanceof User ? $principal->id : null,
            'specialist_account_id' => $principal instanceof SpecialistAccount ? $principal->id : null,
        ];

        return OrganizationMembership::updateOrCreate(
            $match,
            [
                'role'        => $role,
                'status'      => OrganizationMembership::STATUS_ACTIVE,
                'accepted_at' => now(),
            ]
        );
    }

    /**
     * Add a specialist member to an Organization.
     */
    public function addSpecialistMember(Organization $organization, SpecialistAccount $account, string $role = OrganizationMembership::ROLE_MEMBER): OrganizationMembership
    {
        return $this->addMember($organization, $account, $role);
    }

    /**
     * Invite a principal (User or SpecialistAccount) to an Organization.
     */
    public function inviteMember(Organization $organization, User|SpecialistAccount $principal, string $role = OrganizationMembership::ROLE_MEMBER): OrganizationMembership
    {
        $match = [
            'organization_id' => $organization->id,
            'user_id'         => $principal instanceof User ? $principal->id : null,
            'specialist_account_id' => $principal instanceof SpecialistAccount ? $principal->id : null,
        ];

        return OrganizationMembership::firstOrCreate(
            $match,
            [
                'role'       => $role,
                'status'     => OrganizationMembership::STATUS_INVITED,
                'invited_at' => now(),
            ]
        );
    }

    /**
     * Remove a member from an Organization.
     * Prevents removing the designated owner principal.
     */
    public function removeMember(Organization $organization, User|SpecialistAccount $principal): bool
    {
        if ($principal instanceof User && $organization->owner_user_id === $principal->id) {
            throw new InvalidArgumentException('Cannot remove the designated Organization owner.');
        }

        if ($principal instanceof SpecialistAccount && $organization->owner_specialist_account_id === $principal->id) {
            throw new InvalidArgumentException('Cannot remove the designated Organization owner.');
        }

        $query = OrganizationMembership::where('organization_id', $organization->id);

        if ($principal instanceof User) {
            $query->where('user_id', $principal->id);
        } else {
            $query->where('specialist_account_id', $principal->id);
        }

        $membership = $query->first();

        if (!$membership) {
            return false;
        }

        return (bool) $membership->delete();
    }

    /**
     * Transfer designated ownership of an Organization to another active member.
     *
     * Invariants:
     * - Must run within a database transaction with a row lock on Organization.
     * - $currentOwner must match the current designated owner.
     * - $newOwner must already be an active member of the Organization.
     * - Atomically updates designated owner column(s).
     * - Promotes $newOwner to org_owner.
     * - Demotes $currentOwner to org_admin.
     */
    public function transferOwnership(
        Organization $organization,
        User|SpecialistAccount $currentOwner,
        User|SpecialistAccount $newOwner
    ): void {
        DB::transaction(function () use ($organization, $currentOwner, $newOwner) {
            // Lock organization row
            $org = Organization::where('id', $organization->id)->lockForUpdate()->firstOrFail();

            // Verify current owner matches designated owner
            if ($currentOwner instanceof User) {
                if ($org->owner_user_id !== $currentOwner->id) {
                    throw new InvalidArgumentException('Current owner verification failed.');
                }
            } else {
                if ($org->owner_specialist_account_id !== $currentOwner->id) {
                    throw new InvalidArgumentException('Current owner verification failed.');
                }
            }

            // Verify new owner is an active member
            $newOwnerQuery = $org->memberships()->where('status', OrganizationMembership::STATUS_ACTIVE);
            if ($newOwner instanceof User) {
                $newOwnerQuery->where('user_id', $newOwner->id);
            } else {
                $newOwnerQuery->where('specialist_account_id', $newOwner->id);
            }
            $newMembership = $newOwnerQuery->first();

            if (!$newMembership) {
                throw new InvalidArgumentException('New owner must be an active member of the Organization.');
            }

            // Find current owner's membership
            $currOwnerQuery = $org->memberships();
            if ($currentOwner instanceof User) {
                $currOwnerQuery->where('user_id', $currentOwner->id);
            } else {
                $currOwnerQuery->where('specialist_account_id', $currentOwner->id);
            }
            $currMembership = $currOwnerQuery->first();

            // Update designated owner atomically
            if ($newOwner instanceof User) {
                $org->update([
                    'owner_user_id'               => $newOwner->id,
                    'owner_specialist_account_id' => null,
                ]);
            } else {
                $org->update([
                    'owner_user_id'               => null,
                    'owner_specialist_account_id' => $newOwner->id,
                ]);
            }

            // Promote new owner membership
            $newMembership->update(['role' => OrganizationMembership::ROLE_OWNER]);

            // Demote former owner membership
            $currMembership?->update(['role' => OrganizationMembership::ROLE_ADMIN]);
        });
    }

    /**
     * Associate a Provider to an Organization.
     */
    public function attachProvider(Organization $organization, Provider $provider): void
    {
        $provider->update(['organization_id' => $organization->id]);

        if ($provider->subscription && !$provider->subscription->organization_id) {
            $provider->subscription->update(['organization_id' => $organization->id]);
        }
    }

    /**
     * Find the Organization for a Provider.
     */
    public function findForProvider(Provider $provider): ?Organization
    {
        return $provider->organization;
    }

    /**
     * Find the Organization for a User (via current Provider or direct ownership).
     */
    public function findForUser(User $user): ?Organization
    {
        return $user->currentOrganization() ?? $user->ownedOrganizations()->first();
    }

    /**
     * Find the Organization for a SpecialistAccount.
     */
    public function findForSpecialist(SpecialistAccount $account): ?Organization
    {
        return $account->currentOrganization() ?? $account->ownedOrganizations()->first();
    }

    /**
     * Find the Organization for either principal type.
     */
    public function findForPrincipal(User|SpecialistAccount $principal): ?Organization
    {
        if ($principal instanceof User) {
            return $this->findForUser($principal);
        }

        return $this->findForSpecialist($principal);
    }

    /**
     * Check if a principal is an active member of an Organization.
     */
    public function isMember(Organization $organization, User|SpecialistAccount $principal): bool
    {
        return $organization->hasMember($principal);
    }

    /**
     * Check if a principal has admin or owner authority in an Organization.
     */
    public function isAdmin(Organization $organization, User|SpecialistAccount $principal): bool
    {
        return $organization->hasAdmin($principal);
    }

    /**
     * Check if a principal is the owner of an Organization.
     */
    public function isOwner(Organization $organization, User|SpecialistAccount $principal): bool
    {
        return $organization->hasOwner($principal);
    }

    /**
     * Persist a new Organization while satisfying the organizations RLS policy.
     *
     * The `organizations` table is protected by a row-level security policy whose
     * WITH CHECK clause requires `id = current_setting('app.current_organization_id')`.
     * When bootstrapping the principal's first Organization there is no Organization
     * context yet, so the INSERT would be rejected. We therefore pre-generate the
     * UUID and publish it as the transaction-local context before inserting.
     *
     * This keeps RLS fully enforced (no policy is relaxed or bypassed) and also
     * establishes the context required by every subsequent tenant write in the same
     * request/transaction (memberships, providers, locations, services, ...).
     * The setting is transaction-local (`is_local = true`) and reverts automatically
     * when the surrounding transaction ends.
     */
    private function createOrganizationUnderRls(array $attributes): Organization
    {
        $id = (string) Str::uuid();

        DB::connection()->statement(
            "SELECT set_config('" . RlsContext::SESSION_VAR . "', ?, true)",
            [$id]
        );

        $organization = new Organization($attributes);
        $organization->id = $id;
        $organization->save();

        return $organization;
    }

    /**
     * Generate a unique slug for an Organization.
     */
    protected function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        if (empty($slug)) {
            $slug = 'org-' . time();
        }

        $original = $slug;
        $counter = 1;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $counter++;
        }

        return $slug;
    }
}

<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SpecialistAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TenantBootstrap
 *
 * Resolves which tenant an authenticated principal belongs to *before* an RLS
 * context exists on the runtime connection.
 *
 * Why this exists
 * ---------------
 * `organization_memberships` and `provider_users` are protected by Row-Level
 * Security. Without `app.current_organization_id` those rows are invisible, so
 * a request cannot discover its own tenant through the runtime role
 * (`tybuel_app`, NOBYPASSRLS). Tenant discovery is therefore an explicitly
 * privileged, pre-tenant operation and is executed on the administration
 * connection (BYPASSRLS).
 *
 * This is the ONLY place tenant discovery may bypass RLS. Every tenant-scoped
 * read performed afterwards must still run inside RlsContext::run($orgId).
 */
class TenantBootstrap
{
    /**
     * Administration connection (BYPASSRLS).
     */
    public const ADMIN_CONNECTION = 'pgsql_tybuel_admin';

    /**
     * Connection used for tenant discovery.
     *
     * Tests run against the default connection, whose role already bypasses RLS
     * (postgres) and whose schema is the test database — not the live one.
     */
    public static function connectionName(): string
    {
        return app()->environment('testing')
            ? DB::getDefaultConnection()
            : self::ADMIN_CONNECTION;
    }

    /**
     * Active memberships for the principal, loaded on the bootstrap connection.
     *
     * The returned models have their connection reset so that any relation
     * query performed later resolves through the request's RLS-scoped default
     * connection instead of silently reusing the privileged one.
     *
     * @return Collection<int, OrganizationMembership>
     */
    public static function activeMemberships(User|SpecialistAccount $principal): Collection
    {
        $query = OrganizationMembership::on(self::connectionName())
            ->where('status', OrganizationMembership::STATUS_ACTIVE);

        if ($principal instanceof User) {
            $query->where('user_id', $principal->id);
        } else {
            $query->where('specialist_account_id', $principal->id);
        }

        return $query->get()->each(fn (OrganizationMembership $membership) => $membership->setConnection(null));
    }

    /**
     * Load an Organization by id on the bootstrap connection.
     */
    public static function organization(string $organizationId): ?Organization
    {
        $organization = Organization::on(self::connectionName())->find($organizationId);

        return $organization?->setConnection(null);
    }

    /**
     * Resolve the single Organization the principal is bound to.
     *
     * Returns null when the principal has no active membership (onboarding) or
     * belongs to more than one Organization without a preferred id — in both
     * cases no single tenant can be selected implicitly.
     */
    public static function resolveOrganizationId(
        User|SpecialistAccount $principal,
        ?string $preferredOrganizationId = null
    ): ?string {
        $memberships = self::activeMemberships($principal);

        if ($preferredOrganizationId !== null && $preferredOrganizationId !== '') {
            return $memberships->firstWhere('organization_id', $preferredOrganizationId)?->organization_id;
        }

        return $memberships->count() === 1
            ? $memberships->first()->organization_id
            : null;
    }
}

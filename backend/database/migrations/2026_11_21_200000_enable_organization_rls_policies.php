<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enable Organization-Centred Row-Level Security (RLS)
 *
 * Security boundary: Organization (commercial/governance owner)
 * Operational boundary: Provider (owns Locations, Bookings, Services)
 *
 * Architecture:
 *   Platform → Organization → Provider → Location
 *                                      → Booking
 *                                      → Service
 *                                      → Customer (via pivot)
 *                                      → Specialist
 *
 * RLS enforcement path for indirect tables:
 *   booking.provider_id → provider.organization_id = current_org_id
 *
 * Session variable: app.current_organization_id (UUID string, session-scoped)
 * Set by: SetOrganizationDatabaseContext middleware before every controller.
 * Cleared by: SetOrganizationDatabaseContext after the response is returned.
 * PgBouncer safety: middleware wraps all queries in a BEGIN/COMMIT transaction.
 *
 * Public access: Narrowly scoped FOR SELECT policies on providers, locations,
 * services, specialists, and specialist assignments allow unauthenticated
 * booking discovery without exposing private data. FOR INSERT policies on
 * bookings and customer_providers allow unauthenticated guest bookings
 * without permitting read or update access to existing tenant records.
 * Public policies match ONLY when the session variable is empty/absent.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * IMPORTANT: Connection requirements for this migration
 * ─────────────────────────────────────────────────────────────────────────────
 * Run with the ADMIN connection (postgres superuser):
 *   php artisan migrate --database=pgsql_tybuel_admin
 *
 * The migration must run as postgres because:
 *   1. ALTER TABLE ... ENABLE ROW LEVEL SECURITY requires table ownership or superuser.
 *   2. FORCE ROW LEVEL SECURITY ensures RLS is unconditionally applied.
 *   3. The runtime role `tybuel_app` has NOBYPASSRLS and NO ownership of protected
 *      tables, ensuring it cannot alter tables or circumvent policies.
 *
 * After this migration, the application MUST connect as tybuel_app (pgsql_tybuel).
 * The postgres superuser bypasses RLS by default — NEVER use it for app traffic.
 * ─────────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    /**
     * Explicitly use the admin (superuser) connection for DDL.
     * Never run DDL as tybuel_app — it lacks the necessary privileges and must not own tables.
     */
    protected $connection = 'pgsql_tybuel_admin';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The session variable expression used in all USING / WITH CHECK clauses.
        // current_setting(..., true): the second arg (missing_ok=true) prevents an
        // error if the variable was never set — returns empty string instead.
        // NULLIF(..., ''): converts empty string to NULL.
        // ::uuid: cast so comparison to UUID columns works without implicit conversion.
        // Result: NULL when no context → no tenant-scoped rows match → data protected.
        $orgIdExpr = "NULLIF(current_setting('app.current_organization_id', true), '')::uuid";

        // ================================================================
        // 1. ORGANIZATIONS TABLE
        //    Direct: id IS the organization boundary.
        // ================================================================
        $this->enableRls('organizations');

        $this->exec("
            CREATE OR REPLACE POLICY org_tenant_isolation ON organizations
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (id = {$orgIdExpr})
            WITH CHECK (id = {$orgIdExpr})
        ");

        // ================================================================
        // 2. ORGANIZATION MEMBERSHIPS TABLE
        //    Direct: organization_id column.
        // ================================================================
        $this->enableRls('organization_memberships');

        $this->exec("
            CREATE OR REPLACE POLICY org_membership_tenant_isolation ON organization_memberships
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (organization_id = {$orgIdExpr})
            WITH CHECK (organization_id = {$orgIdExpr})
        ");

        // ================================================================
        // 3. PROVIDERS TABLE
        //    Direct: organization_id column.
        //    Multi-Provider Orgs: all Providers with same organization_id visible.
        //
        //    Policies:
        //    a) Tenant-scoped ALL — requires valid org context.
        //    b) Public SELECT — active providers only (booking discovery).
        // ================================================================
        $this->enableRls('providers');

        $this->exec("
            CREATE OR REPLACE POLICY provider_tenant_isolation ON providers
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (organization_id = {$orgIdExpr})
            WITH CHECK (organization_id = {$orgIdExpr})
        ");

        $this->exec("
            CREATE OR REPLACE POLICY provider_public_discovery ON providers
            AS PERMISSIVE FOR SELECT
            TO PUBLIC
            USING (
                {$orgIdExpr} IS NULL
                AND active = true
            )
        ");

        // ================================================================
        // 4. SUBSCRIPTIONS TABLE
        //    Direct: organization_id column.
        // ================================================================
        $this->enableRls('subscriptions');

        $this->exec("
            CREATE OR REPLACE POLICY subscription_tenant_isolation ON subscriptions
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (organization_id = {$orgIdExpr})
            WITH CHECK (organization_id = {$orgIdExpr})
        ");

        // ================================================================
        // 5. LOCATIONS TABLE
        //    Indirect: provider_id → providers.organization_id
        //
        //    Policies:
        //    a) Tenant-scoped ALL — requires valid org context.
        //    b) Public SELECT — active locations for active providers only.
        // ================================================================
        $this->enableRls('locations');

        $this->exec("
            CREATE OR REPLACE POLICY location_tenant_isolation ON locations
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
            WITH CHECK (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
        ");

        $this->exec("
            CREATE OR REPLACE POLICY location_public_discovery ON locations
            AS PERMISSIVE FOR SELECT
            TO PUBLIC
            USING (
                {$orgIdExpr} IS NULL
                AND provider_id IN (SELECT id FROM providers WHERE active = true)
            )
        ");

        // ================================================================
        // 6. BOOKINGS TABLE
        //    Indirect: provider_id → providers.organization_id
        //
        //    Policies:
        //    a) Tenant-scoped ALL — private to the organization.
        //    b) Public INSERT — guest bookings for active providers only.
        //       No public SELECT policy — guest cannot read any bookings.
        // ================================================================
        $this->enableRls('bookings');

        $this->exec("
            CREATE OR REPLACE POLICY booking_tenant_isolation ON bookings
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
            WITH CHECK (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
        ");

        $this->exec("
            CREATE OR REPLACE POLICY booking_guest_insert ON bookings
            AS PERMISSIVE FOR INSERT
            TO PUBLIC
            WITH CHECK (
                {$orgIdExpr} IS NULL
                AND provider_id IN (SELECT id FROM providers WHERE active = true)
            )
        ");

        // ================================================================
        // 7. SERVICES TABLE
        //    Indirect: provider_id → providers.organization_id
        //
        //    Policies:
        //    a) Tenant-scoped ALL — normal authenticated management.
        //    b) Public SELECT — active services on active providers only.
        // ================================================================
        $this->enableRls('services');

        $this->exec("
            CREATE OR REPLACE POLICY service_tenant_isolation ON services
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
            WITH CHECK (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
        ");

        $this->exec("
            CREATE OR REPLACE POLICY service_public_discovery ON services
            AS PERMISSIVE FOR SELECT
            TO PUBLIC
            USING (
                {$orgIdExpr} IS NULL
                AND active = true
                AND provider_id IN (SELECT id FROM providers WHERE active = true)
            )
        ");

        // ================================================================
        // 8. CUSTOMER_PROVIDERS PIVOT TABLE
        //    Indirect: provider_id → providers.organization_id
        //
        //    Policies:
        //    a) Tenant-scoped ALL — scoped customer-provider associations.
        //    b) Public INSERT — allows guest booking flow to link customer to provider.
        // ================================================================
        $this->enableRls('customer_providers');

        $this->exec("
            CREATE OR REPLACE POLICY customer_provider_tenant_isolation ON customer_providers
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
            WITH CHECK (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
        ");

        $this->exec("
            CREATE OR REPLACE POLICY customer_provider_guest_insert ON customer_providers
            AS PERMISSIVE FOR INSERT
            TO PUBLIC
            WITH CHECK (
                {$orgIdExpr} IS NULL
                AND provider_id IN (SELECT id FROM providers WHERE active = true)
            )
        ");

        // ================================================================
        // 9. SPECIALISTS TABLE
        //    Indirect: provider_id → providers.organization_id
        //
        //    Policies:
        //    a) Tenant-scoped ALL — full tenant management.
        //    b) Public SELECT — active specialists on active providers (for booking).
        // ================================================================
        $this->enableRls('specialists');

        $this->exec("
            CREATE OR REPLACE POLICY specialist_tenant_isolation ON specialists
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
            WITH CHECK (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
        ");

        $this->exec("
            CREATE OR REPLACE POLICY specialist_public_discovery ON specialists
            AS PERMISSIVE FOR SELECT
            TO PUBLIC
            USING (
                {$orgIdExpr} IS NULL
                AND provider_id IN (SELECT id FROM providers WHERE active = true)
            )
        ");

        // ================================================================
        // 10. SPECIALIST ASSIGNMENTS TABLE
        //     Double-indirect: location_id → locations.provider_id → providers.organization_id
        //
        //    Policies:
        //    a) Tenant-scoped ALL — full tenant management.
        //    b) Public SELECT — active assignments on active provider locations (for booking).
        // ================================================================
        $this->enableRls('specialist_assignments');

        $this->exec("
            CREATE OR REPLACE POLICY specialist_assignment_tenant_isolation ON specialist_assignments
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (
                location_id IN (
                    SELECT l.id FROM locations l
                    JOIN providers p ON p.id = l.provider_id
                    WHERE p.organization_id = {$orgIdExpr}
                )
            )
            WITH CHECK (
                location_id IN (
                    SELECT l.id FROM locations l
                    JOIN providers p ON p.id = l.provider_id
                    WHERE p.organization_id = {$orgIdExpr}
                )
            )
        ");

        $this->exec("
            CREATE OR REPLACE POLICY specialist_assignment_public_discovery ON specialist_assignments
            AS PERMISSIVE FOR SELECT
            TO PUBLIC
            USING (
                {$orgIdExpr} IS NULL
                AND location_id IN (
                    SELECT l.id FROM locations l
                    JOIN providers p ON p.id = l.provider_id
                    WHERE p.active = true
                )
            )
        ");

        // ================================================================
        // 11. PROVIDER_USERS TABLE
        //     Indirect: provider_id → providers.organization_id
        //     Staff/user assignments are strictly internal to the tenant.
        //     No public policy.
        // ================================================================
        $this->enableRls('provider_users');

        $this->exec("
            CREATE OR REPLACE POLICY provider_user_tenant_isolation ON provider_users
            AS PERMISSIVE FOR ALL
            TO PUBLIC
            USING (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
            WITH CHECK (
                provider_id IN (
                    SELECT id FROM providers
                    WHERE organization_id = {$orgIdExpr}
                )
            )
        ");
    }

    /**
     * Reverse the migrations.
     * Drops all RLS policies and disables RLS on all protected tables.
     */
    public function down(): void
    {
        $tables = [
            'organizations'            => ['org_tenant_isolation'],
            'organization_memberships' => ['org_membership_tenant_isolation'],
            'providers'                => ['provider_tenant_isolation', 'provider_public_discovery'],
            'subscriptions'            => ['subscription_tenant_isolation'],
            'locations'                => ['location_tenant_isolation', 'location_public_discovery'],
            'bookings'                 => ['booking_tenant_isolation', 'booking_guest_insert'],
            'services'                 => ['service_tenant_isolation', 'service_public_discovery'],
            'customer_providers'       => ['customer_provider_tenant_isolation', 'customer_provider_guest_insert'],
            'specialists'              => ['specialist_tenant_isolation', 'specialist_public_discovery'],
            'specialist_assignments'   => ['specialist_assignment_tenant_isolation', 'specialist_assignment_public_discovery'],
            'provider_users'           => ['provider_user_tenant_isolation'],
        ];

        foreach ($tables as $table => $policies) {
            foreach ($policies as $policy) {
                DB::connection($this->connection)->statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
            }
            DB::connection($this->connection)->statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::connection($this->connection)->statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Enable and force RLS on a table.
     * FORCE ROW LEVEL SECURITY ensures even the table owner (postgres) cannot
     * bypass policies when executing queries on that table.
     */
    private function enableRls(string $table): void
    {
        $this->exec("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        $this->exec("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
    }

    /**
     * Execute a raw SQL statement on the admin connection.
     *
     * Transparently handles "CREATE OR REPLACE POLICY" for PostgreSQL < 17
     * by running DROP POLICY IF EXISTS … followed by CREATE POLICY …
     */
    private function exec(string $sql): void
    {
        // PostgreSQL 16 does not support CREATE OR REPLACE POLICY (added in PG17).
        // Emulate it: extract the policy name and table, drop if exists, then create.
        $normalised = preg_replace('/\s+/', ' ', trim($sql));
        if (preg_match('/^CREATE OR REPLACE POLICY (\w+) ON (\w+)\b/i', $normalised, $m)) {
            DB::connection($this->connection)->statement(
                "DROP POLICY IF EXISTS {$m[1]} ON {$m[2]}"
            );
            $sql = preg_replace('/CREATE OR REPLACE POLICY/i', 'CREATE POLICY', $sql, 1);
        }

        DB::connection($this->connection)->statement($sql);
    }
};

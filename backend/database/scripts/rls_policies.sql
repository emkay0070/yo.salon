-- =============================================================================
-- TYBUEL — Organization-Centred RLS Policies (Standalone SQL)
-- Target: E:\Apps\PostgreSQL 16 (Port 5433)
-- =============================================================================
-- This script is the exact SQL equivalent of the Laravel migration
-- 2026_11_21_200000_enable_organization_rls_policies.php.
--
-- Run AFTER:
--   1. tybuel_db_setup.sql (role + privileges)
--   2. php artisan migrate --database=pgsql_tybuel_admin (all table migrations)
--
-- Usage:
--   & "E:\Apps\PostgreSQL 16\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5433 -d tybuel -f database/scripts/rls_policies.sql
-- =============================================================================

-- Helper expression:
-- NULLIF(current_setting('app.current_organization_id', true), '')::uuid
-- Evaluates to NULL when context is absent or empty.

-- =============================================================================
-- 1. ORGANIZATIONS
-- =============================================================================
ALTER TABLE organizations ENABLE ROW LEVEL SECURITY;
ALTER TABLE organizations FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS org_tenant_isolation ON organizations;
CREATE OR REPLACE POLICY org_tenant_isolation ON organizations
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid)
    WITH CHECK (id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);

-- =============================================================================
-- 2. ORGANIZATION MEMBERSHIPS
-- =============================================================================
ALTER TABLE organization_memberships ENABLE ROW LEVEL SECURITY;
ALTER TABLE organization_memberships FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS org_membership_tenant_isolation ON organization_memberships;
CREATE OR REPLACE POLICY org_membership_tenant_isolation ON organization_memberships
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid)
    WITH CHECK (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);

-- =============================================================================
-- 3. PROVIDERS
-- =============================================================================
ALTER TABLE providers ENABLE ROW LEVEL SECURITY;
ALTER TABLE providers FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS provider_tenant_isolation ON providers;
CREATE OR REPLACE POLICY provider_tenant_isolation ON providers
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid)
    WITH CHECK (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);

DROP POLICY IF EXISTS provider_public_discovery ON providers;
CREATE OR REPLACE POLICY provider_public_discovery ON providers
    AS PERMISSIVE FOR SELECT
    TO PUBLIC
    USING (
        NULLIF(current_setting('app.current_organization_id', true), '')::uuid IS NULL
        AND active = true
    );

-- =============================================================================
-- 4. SUBSCRIPTIONS
-- =============================================================================
ALTER TABLE subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE subscriptions FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS subscription_tenant_isolation ON subscriptions;
CREATE OR REPLACE POLICY subscription_tenant_isolation ON subscriptions
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid)
    WITH CHECK (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);

-- =============================================================================
-- 5. LOCATIONS
-- =============================================================================
ALTER TABLE locations ENABLE ROW LEVEL SECURITY;
ALTER TABLE locations FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS location_tenant_isolation ON locations;
CREATE OR REPLACE POLICY location_tenant_isolation ON locations
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    )
    WITH CHECK (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    );

DROP POLICY IF EXISTS location_public_discovery ON locations;
CREATE OR REPLACE POLICY location_public_discovery ON locations
    AS PERMISSIVE FOR SELECT
    TO PUBLIC
    USING (
        NULLIF(current_setting('app.current_organization_id', true), '')::uuid IS NULL
        AND provider_id IN (SELECT id FROM providers WHERE active = true)
    );

-- =============================================================================
-- 6. BOOKINGS
-- =============================================================================
ALTER TABLE bookings ENABLE ROW LEVEL SECURITY;
ALTER TABLE bookings FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS booking_tenant_isolation ON bookings;
CREATE OR REPLACE POLICY booking_tenant_isolation ON bookings
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    )
    WITH CHECK (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    );

DROP POLICY IF EXISTS booking_guest_insert ON bookings;
CREATE OR REPLACE POLICY booking_guest_insert ON bookings
    AS PERMISSIVE FOR INSERT
    TO PUBLIC
    WITH CHECK (
        NULLIF(current_setting('app.current_organization_id', true), '')::uuid IS NULL
        AND provider_id IN (SELECT id FROM providers WHERE active = true)
    );

-- =============================================================================
-- 7. SERVICES
-- =============================================================================
ALTER TABLE services ENABLE ROW LEVEL SECURITY;
ALTER TABLE services FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS service_tenant_isolation ON services;
CREATE OR REPLACE POLICY service_tenant_isolation ON services
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    )
    WITH CHECK (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    );

DROP POLICY IF EXISTS service_public_discovery ON services;
CREATE OR REPLACE POLICY service_public_discovery ON services
    AS PERMISSIVE FOR SELECT
    TO PUBLIC
    USING (
        NULLIF(current_setting('app.current_organization_id', true), '')::uuid IS NULL
        AND active = true
        AND provider_id IN (SELECT id FROM providers WHERE active = true)
    );

-- =============================================================================
-- 8. CUSTOMER_PROVIDERS PIVOT
-- =============================================================================
ALTER TABLE customer_providers ENABLE ROW LEVEL SECURITY;
ALTER TABLE customer_providers FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS customer_provider_tenant_isolation ON customer_providers;
CREATE OR REPLACE POLICY customer_provider_tenant_isolation ON customer_providers
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    )
    WITH CHECK (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    );

DROP POLICY IF EXISTS customer_provider_guest_insert ON customer_providers;
CREATE OR REPLACE POLICY customer_provider_guest_insert ON customer_providers
    AS PERMISSIVE FOR INSERT
    TO PUBLIC
    WITH CHECK (
        NULLIF(current_setting('app.current_organization_id', true), '')::uuid IS NULL
        AND provider_id IN (SELECT id FROM providers WHERE active = true)
    );

-- =============================================================================
-- 9. SPECIALISTS
-- =============================================================================
ALTER TABLE specialists ENABLE ROW LEVEL SECURITY;
ALTER TABLE specialists FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS specialist_tenant_isolation ON specialists;
CREATE OR REPLACE POLICY specialist_tenant_isolation ON specialists
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    )
    WITH CHECK (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    );

DROP POLICY IF EXISTS specialist_public_discovery ON specialists;
CREATE OR REPLACE POLICY specialist_public_discovery ON specialists
    AS PERMISSIVE FOR SELECT
    TO PUBLIC
    USING (
        NULLIF(current_setting('app.current_organization_id', true), '')::uuid IS NULL
        AND provider_id IN (SELECT id FROM providers WHERE active = true)
    );

-- =============================================================================
-- 10. SPECIALIST ASSIGNMENTS
-- =============================================================================
ALTER TABLE specialist_assignments ENABLE ROW LEVEL SECURITY;
ALTER TABLE specialist_assignments FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS specialist_assignment_tenant_isolation ON specialist_assignments;
CREATE OR REPLACE POLICY specialist_assignment_tenant_isolation ON specialist_assignments
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (
        location_id IN (
            SELECT l.id FROM locations l
            JOIN providers p ON p.id = l.provider_id
            WHERE p.organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    )
    WITH CHECK (
        location_id IN (
            SELECT l.id FROM locations l
            JOIN providers p ON p.id = l.provider_id
            WHERE p.organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    );

DROP POLICY IF EXISTS specialist_assignment_public_discovery ON specialist_assignments;
CREATE OR REPLACE POLICY specialist_assignment_public_discovery ON specialist_assignments
    AS PERMISSIVE FOR SELECT
    TO PUBLIC
    USING (
        NULLIF(current_setting('app.current_organization_id', true), '')::uuid IS NULL
        AND location_id IN (
            SELECT l.id FROM locations l
            JOIN providers p ON p.id = l.provider_id
            WHERE p.active = true
        )
    );

-- =============================================================================
-- 11. PROVIDER_USERS
-- =============================================================================
ALTER TABLE provider_users ENABLE ROW LEVEL SECURITY;
ALTER TABLE provider_users FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS provider_user_tenant_isolation ON provider_users;
CREATE OR REPLACE POLICY provider_user_tenant_isolation ON provider_users
    AS PERMISSIVE FOR ALL
    TO PUBLIC
    USING (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    )
    WITH CHECK (
        provider_id IN (
            SELECT id FROM providers
            WHERE organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid
        )
    );

-- =============================================================================
-- VERIFICATION QUERIES
-- =============================================================================

SELECT
    schemaname,
    tablename,
    rowsecurity      AS rls_enabled,
    forcerowsecurity AS rls_forced
FROM pg_tables
WHERE schemaname = 'public'
  AND tablename IN (
      'organizations', 'organization_memberships', 'providers', 'subscriptions',
      'locations', 'bookings', 'services', 'customer_providers',
      'specialists', 'specialist_assignments', 'provider_users'
  )
ORDER BY tablename;

SELECT
    tablename,
    policyname,
    permissive,
    roles,
    cmd,
    qual,
    with_check
FROM pg_policies
WHERE schemaname = 'public'
ORDER BY tablename, policyname;

SELECT 'RLS policies applied successfully.' AS status;

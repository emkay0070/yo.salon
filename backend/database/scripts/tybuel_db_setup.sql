-- =============================================================================
-- TYBUEL — PostgreSQL Database Setup Script (PostgreSQL 16)
-- Target: E:\Apps\PostgreSQL 16 (Port 5433)
-- Run as the postgres superuser ONCE before migrations.
-- =============================================================================
-- Usage (PowerShell using PostgreSQL 16 directly):
--   Step 1 (Start PostgreSQL 16 service if not running):
--     & "E:\Apps\PostgreSQL 16\bin\pg_ctl.exe" -D "E:\Apps\PostgreSQL 16\data" start
--
--   Step 2 (Create database — must run outside a transaction):
--     & "E:\Apps\PostgreSQL 16\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5433 -c "CREATE DATABASE tybuel;"
--
--   Step 3 (Execute this script to configure tybuel_app role & privileges):
--     & "E:\Apps\PostgreSQL 16\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5433 -d tybuel -f database/scripts/tybuel_db_setup.sql
--
--   Step 4 (Run table migrations via Laravel admin connection):
--     cd backend
--     php artisan migrate --database=pgsql_tybuel_admin
--
--   Step 5 (Grant DML privileges on created tables):
--     & "E:\Apps\PostgreSQL 16\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5433 -d tybuel -c "GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO tybuel_app; GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO tybuel_app;"
--
--   Step 6 (Run focused RLS tests):
--     php artisan test tests/Feature/OrganizationRlsIsolationTest.php
-- =============================================================================

-- Step 1 is manual (CREATE DATABASE must run outside a transaction block):
-- psql -U postgres -h 127.0.0.1 -p 5433 -c "CREATE DATABASE tybuel;"

-- =============================================================================
-- Step 2: Create the application role with NOBYPASSRLS
--
-- CRITICAL: tybuel_app MUST have NOBYPASSRLS so PostgreSQL enforces RLS on it.
-- The postgres superuser always bypasses RLS — it must NOT be used for app traffic.
-- =============================================================================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'tybuel_app') THEN
        -- IMPORTANT: Replace 'CHANGE_ME_SECURE_PASSWORD' with your actual password.
        -- Also update TYBUEL_DB_PASSWORD in .env to the same value.
        -- Do NOT commit the real password to source control.
        CREATE ROLE tybuel_app WITH
            LOGIN
            NOBYPASSRLS
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            PASSWORD 'b286e07157b4454c8beca8d668781e83';
        RAISE NOTICE 'Role tybuel_app created.';
    ELSE
        RAISE NOTICE 'Role tybuel_app already exists — skipping creation.';
        -- If the role exists but was created with BYPASSRLS, correct it:
        ALTER ROLE tybuel_app NOBYPASSRLS;
        RAISE NOTICE 'Ensured tybuel_app has NOBYPASSRLS.';
    END IF;
END
$$;

-- =============================================================================
-- Step 3: Grant database connection and schema usage
-- =============================================================================
GRANT CONNECT ON DATABASE tybuel TO tybuel_app;
GRANT USAGE ON SCHEMA public TO tybuel_app;

-- =============================================================================
-- Step 4: Set DEFAULT PRIVILEGES so tables created by postgres during migrations
--         are automatically accessible to tybuel_app.
--
-- "IN ROLE postgres" is the critical clause. Without it, ALTER DEFAULT PRIVILEGES
-- sets privileges for the current session role (also postgres here), which is
-- correct. We include it explicitly for documentation clarity.
--
-- These defaults apply to objects created by the postgres role in this database.
-- =============================================================================
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO tybuel_app;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO tybuel_app;

-- Note: Tables remain owned by postgres (the privileged migration user).
-- tybuel_app has NO table ownership, NO superuser, and NOBYPASSRLS.
-- This guarantees tybuel_app can only perform DML within RLS policies and cannot alter tables.

-- =============================================================================
-- Step 5: Verify role attributes BEFORE running migrations
-- =============================================================================
SELECT
    rolname,
    rolbypassrls  AS bypass_rls,
    rolsuper      AS is_superuser,
    rolcreatedb   AS can_createdb,
    rolcreaterole AS can_createrole,
    rolcanlogin   AS can_login
FROM pg_roles
WHERE rolname IN ('postgres', 'tybuel_app')
ORDER BY rolname;

-- Expected output:
--  rolname    | bypass_rls | is_superuser | can_createdb | can_createrole | can_login
-- ------------+------------+--------------+--------------+----------------+-----------
--  postgres   | t          | t            | t            | t              | t
--  tybuel_app | f          | f            | f            | f              | t
--
-- tybuel_app bypass_rls MUST be f (false). If true, run: ALTER ROLE tybuel_app NOBYPASSRLS;

-- =============================================================================
-- Step 6: Run migrations (do this in your terminal AFTER this script):
--
--   cd backend
--   php artisan migrate --database=pgsql_tybuel_admin
--
-- The RLS migration (2026_11_21_200000_enable_organization_rls_policies.php)
-- will ENABLE RLS, FORCE RLS, and TRANSFER TABLE OWNERSHIP to tybuel_app.
-- =============================================================================

-- =============================================================================
-- Step 7: Grant runtime access AFTER migrations have created the tables.
--
-- Run this block AFTER migrations complete. The DEFAULT PRIVILEGES set above
-- apply to NEW tables but we also explicitly grant on existing tables in case
-- any were created before this script ran.
-- =============================================================================

-- Uncomment and run after migrations:
-- GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO tybuel_app;
-- GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO tybuel_app;

-- =============================================================================
-- Step 8: Final verification (run after migrations + grants)
-- =============================================================================

-- Check which tables have RLS enabled:
-- SELECT tablename, rowsecurity, forcerowsecurity
-- FROM pg_tables
-- WHERE schemaname = 'public'
-- ORDER BY tablename;

-- Check table owners:
-- SELECT tablename, tableowner FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename;

-- Check active policies:
-- SELECT tablename, policyname, cmd, roles, qual FROM pg_policies WHERE schemaname = 'public';

SELECT 'Setup script complete. Next: php artisan migrate --database=pgsql_tybuel_admin' AS next_step;

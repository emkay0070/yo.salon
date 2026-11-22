-- Phase 3A primary Location preflight (READ ONLY)
--
-- Run against a target database before applying:
--   2026_11_18_000000_enforce_one_primary_location_per_provider.php
--
-- Example:
--   psql "$DATABASE_URL" -f database/scripts/phase3a_primary_location_preflight.sql
--
-- This script never changes data. Review every row it returns before deploying
-- the uniqueness migration.

BEGIN TRANSACTION READ ONLY;

-- 1. Providers with more than one primary Location.
--    The migration will refuse to create the unique index while any of these exist.
SELECT
    p.id AS provider_id,
    p.display_name,
    p.slug AS provider_slug,
    COUNT(l.id) AS primary_location_count,
    STRING_AGG(l.id::text, ', ' ORDER BY l.created_at, l.id) AS primary_location_ids
FROM providers p
JOIN locations l
    ON l.provider_id = p.id
    AND l.is_primary = true
GROUP BY p.id, p.display_name, p.slug
HAVING COUNT(l.id) > 1
ORDER BY p.id;

-- 2. Providers that have Locations but no primary Location.
--    These do not block the migration, but they are inconsistent with the
--    intended invariant and should be reviewed.
SELECT
    p.id AS provider_id,
    p.display_name,
    p.slug AS provider_slug,
    COUNT(l.id) AS location_count,
    STRING_AGG(l.id::text, ', ' ORDER BY l.created_at, l.id) AS location_ids
FROM providers p
JOIN locations l
    ON l.provider_id = p.id
GROUP BY p.id, p.display_name, p.slug
HAVING COUNT(l.id) FILTER (WHERE l.is_primary) = 0
ORDER BY p.id;

-- 3. Providers with zero Locations.
--    These are explicitly valid and must not be forced to create a Location.
SELECT
    p.id AS provider_id,
    p.display_name,
    p.slug AS provider_slug,
    p.created_at
FROM providers p
WHERE NOT EXISTS (
    SELECT 1
    FROM locations l
    WHERE l.provider_id = p.id
)
ORDER BY p.id;

COMMIT;

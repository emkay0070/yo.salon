# TYBUEL — PostgreSQL Row-Level Security (RLS) Readiness Audit & Prerequisites

## 1. Executive Summary

Row-Level Security (RLS) can only provide strong security guarantees when database roles, session contexts, and foreign-key hierarchies are strictly prepared. This document outlines the architectural readiness status following the completion of **Organization Identity & Commercial Hardening**.

---

## 2. Infrastructure & Role Prerequisites

### 2.1 Database Role Separation
- **Current State:** The application connects as superuser `postgres`. PostgreSQL superusers and table owners bypass all RLS policies by default (`BYPASSRLS`).
- **Required Action Before Enabling RLS:**
  1. Create a dedicated application role:
     ```sql
     CREATE ROLE tybuel_app WITH LOGIN PASSWORD 'strong_password' NOBYPASSRLS NOSUPERUSER;
     GRANT CONNECT ON DATABASE yo_salon TO tybuel_app;
     GRANT USAGE ON SCHEMA public TO tybuel_app;
     GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO tybuel_app;
     GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO tybuel_app;
     ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO tybuel_app;
     ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO tybuel_app;
     ```
  2. Configure `DB_USERNAME=tybuel_app` in production environment configs.

---

## 3. Session Context Propagation (`app.current_organization_id`)

### 3.1 Transaction-Scoped Context
RLS policies in PostgreSQL evaluate session configuration settings. In web requests and queue jobs, the context must be set per transaction:

```sql
SELECT set_config('app.current_organization_id', '01a1f8a3-xxxx-xxxx-xxxx-xxxxxxxxxxxx', true);
```
*(Passing `true` as the third parameter scopes the setting strictly to the current transaction, preventing connection-pool leakage in PgBouncer).*

### 3.2 Web Request Middleware
The `ResolveOrganizationContext` middleware resolves and validates the active Organization for the authenticated principal. In the upcoming RLS phase, a dedicated database context layer will invoke `set_config` before controller execution.

### 3.3 Queue Worker Context Propagation
Every queued job operating on organization- or provider-scoped resources must carry `organization_id`:
```php
class ProcessOrderJob implements ShouldQueue
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $providerId,
        public readonly array $data
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            DB::statement("SELECT set_config('app.current_organization_id', ?, true)", [$this->organizationId]);
            // Execute domain logic under RLS enforcement...
        });
    }
}
```

---

## 4. Schema & Data Model Readiness

| Table | Status | FK Constraint | Deletion Rule |
| :--- | :--- | :--- | :--- |
| `organizations` | **Hardened** | Designated owner check (`chk_organization_designated_owner`) | Protected |
| `organization_memberships` | **Hardened** | Dual-principal check (`chk_membership_principal`) | `ON DELETE CASCADE` |
| `providers` | **Hardened** | `organization_id NOT NULL REFERENCES organizations(id)` | `ON DELETE RESTRICT` |
| `subscriptions` | **Hardened** | `organization_id NOT NULL REFERENCES organizations(id)` | `ON DELETE RESTRICT` |

---

## 5. RLS Policy Implementation Strategy (Next Workstream)

When RLS is authorized, policies will follow this canonical pattern:

```sql
ALTER TABLE providers ENABLE ROW LEVEL SECURITY;
ALTER TABLE providers FORCE ROW LEVEL SECURITY;

CREATE POLICY organization_isolation_policy ON providers
    FOR ALL
    TO tybuel_app
    USING (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid)
    WITH CHECK (organization_id = NULLIF(current_setting('app.current_organization_id', true), '')::uuid);
```

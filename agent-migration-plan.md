# Specialist Migration UI Implementation

This plan outlines the steps to build an explicit, visible, and controlled migration experience for salons still using legacy `Staff` records, moving them to the new canonical `Specialist` architecture.

## User Review Required

> [!IMPORTANT]
> **Data Handling:** During migration, should we permanently delete the legacy `Staff` records, or simply mark them as inactive/migrated (e.g., adding a `migrated_to_specialist_id` column for audit trails)? Marking them inactive is safer but leaves legacy data in the database.

> [!WARNING]
> **Booking Disruption:** While a salon is in the "migration required" state, their legacy staff cannot be booked by customers via the new Availability Engine. The migration UI banner must clearly communicate this urgency to the salon owner.

## Open Questions

1. **Routing:** Should the migration UI live at `/{slug}/settings/specialists/migration` or a more prominent `/{slug}/migration`? (Plan currently assumes `/{slug}/settings/specialists/migration`).
2. **Schedules:** When converting a legacy `Staff` member to a `Specialist`, should we attempt to migrate their legacy schedules (if any exist), or just create the new `Specialist` and require the salon owner to re-configure their schedules in the new system?

## Proposed Changes

---

### Backend API: Migration Endpoints

We need to add endpoints to detect the migration state and perform the actual data migration.

#### [NEW] `backend/app/Http/Controllers/Api/V1/MigrationController.php`
- `status(Request $request, $salonId)`: Returns the count of legacy `Staff`, count of new `Specialist` assignments, and a `migration_required` boolean flag.
- `execute(Request $request, $salonId)`: Accepts a list of staff IDs. For each:
    - Creates a `Specialist` record on the `Provider`.
    - Creates a `SpecialistAssignment` linking the specialist to the `Salon`.
    - Deactivates the legacy `Staff` record.

#### [MODIFY] `backend/routes/api.php`
- Add the new migration routes under the business portal middleware group:
  - `GET /salons/{salon}/migration-status`
  - `POST /salons/{salon}/migrate-staff`

---

### Frontend: Dashboard Banner & Warning

We need to surface the migration state prominently so salon owners take action.

#### [MODIFY] `frontend/src/app/[slug]/(business)/dashboard/page.tsx`
- Integrate a query to fetch the migration status.
- Add a prominent warning banner at the top of the dashboard if `migration_required` is true.
- Banner text: *"⚠️ Specialist setup needs attention. Your salon is still using an older staff setup. To use the latest booking features, complete your Specialist migration."*
- Add a "Review Migration →" button linking to the Migration Center.

#### [MODIFY] `frontend/src/app/[slug]/(business)/staff/page.tsx`
- Add a similar warning banner to the Staff management page to catch users attempting to manage legacy staff directly.

---

### Frontend: Migration Center UI

The dedicated interface where the salon owner reviews and executes the migration.

#### [NEW] `frontend/src/app/[slug]/(business)/settings/specialists/migration/page.tsx`
- A clean, focused UI explaining the architectural upgrade.
- Displays a list of all unmigrated team members (e.g., "⚠ Immo — needs migration").
- Provides a one-click "Migrate Team to Specialists" action.
- Displays a success state upon completion, guiding the user back to their dashboard.

#### [MODIFY] `frontend/src/lib/api-client.ts`
- Add `getMigrationStatus(salonId: string)`
- Add `executeStaffMigration(salonId: string, staffIds: string[])`

## Verification Plan

### Automated Tests
- N/A (Focusing on manual verification for UI flows)

### Manual Verification
1. Access the business dashboard for a salon known to have legacy `Staff` records (e.g., the one causing the 422 errors earlier).
2. Verify the warning banner appears on the Dashboard and Staff pages.
3. Click "Review Migration" and verify the Migration Center lists the correct legacy staff members.
4. Execute the migration via the UI.
5. Verify the backend successfully created `Specialist` and `SpecialistAssignment` records, and deactivated the legacy `Staff` records.
6. Verify the warning banner disappears from the dashboard.
7. Attempt a public booking flow and verify the newly migrated specialist is available via the new Availability Engine.

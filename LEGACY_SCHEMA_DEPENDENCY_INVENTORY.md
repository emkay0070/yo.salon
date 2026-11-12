# Legacy Schema Dependency Inventory

**Generated**: 2026-09-27  
**Phase**: Wave 6.2D - Legacy Schema Cleanup  
**Status**: READY FOR REVIEW

---

## Executive Summary

The `salons` table has been **successfully dropped** from the database. All foreign key references to `salons` have been removed from the active schema. However, **legacy code references** remain in the application layer that must be cleaned up to match the canonical Provider → Location architecture.

**Critical Finding**: The database schema is already canonical. The cleanup required is **code-only**, not schema changes.

---

## 1. SALONS TABLE STATUS

| Attribute | Value |
|-----------|-------|
| **Table Exists** | NO |
| **Row Count** | 0 (table does not exist) |
| **Foreign Keys Referencing It** | 0 (all dropped) |
| **Active Runtime Queries** | 0 (none found) |
| **Migration Status** | Dropped via prior migrations |

**Conclusion**: The `salons` table is completely removed from the database. No schema cleanup required.

---

## 2. ACTIVE LEGACY COLUMNS IN DATABASE

### Summary

**ZERO** active `salon_id` columns exist in the current database schema.

All tables have been migrated to use canonical columns:
- `provider_id` for business ownership
- `location_id` for physical operating context

### Database Schema Audit Results

| Table | Has `salon_id`? | Has `customer_salon_id`? | Canonical Columns |
|-------|----------------|--------------------------|-------------------|
| `customer_packages` | NO | NO | `provider_id` ✓ |
| `customer_preferences` | NO | NO | None (customer-scoped) ✓ |
| `customer_favorite_services` | NO | NO | None (customer-scoped) ✓ |
| `bookings` | NO | NO | `provider_id`, `location_id` ✓ |
| `customer_specialist` | NO | NO | `provider_id` ✓ |
| `customer_favorites` | NO | NO | `provider_id` ✓ |
| `customer_activities` | NO | NO | `provider_id` ✓ |
| `customer_timelines` | NO | NO | `provider_id` ✓ |
| `customer_collections` | NO | NO | `provider_id` ✓ |
| `media` | NO | NO | `provider_id`, `location_id` ✓ |
| `reviews` | NO | NO | `provider_id` ✓ |

**Conclusion**: Database schema is fully canonical. No column drops required.

---

## 3. LEGACY CODE REFERENCES

### 3.1 Model Fillable Arrays (Code Mismatch)

These models declare `salon_id` in fillable arrays, but the database does not have these columns:

| Model | File | Legacy Fillable Fields | DB Reality | Action Required |
|-------|------|----------------------|------------|-----------------|
| `CustomerPackage` | `app/Models/CustomerPackage.php` | `salon_id` | Has `provider_id` | Remove `salon_id` from fillable, update code references |
| `CustomerPreference` | `app/Models/CustomerPreference.php` | `salon_id`, `customer_salon_id` | Has neither | Remove both from fillable, update code references |
| `CustomerFavoriteService` | `app/Models/CustomerFavoriteService.php` | `salon_id` | Has neither | Remove from fillable, update code references |
| `Booking` | `app/Models/Booking.php` | `customer_salon_id` | Has neither | Remove from fillable, update code references |

### 3.2 Model Relationship Methods (Orphaned References)

These models define relationships to `CustomerSalon`, which does not exist:

| Model | Method | Target | Action Required |
|-------|--------|--------|-----------------|
| `CustomerPreference` | `customerSalon()` | `CustomerSalon::class` | Remove method (table does not exist) |
| `Booking` | `customerSalon()` | `CustomerSalon::class` | Remove method (table does not exist) |

### 3.3 Service Layer Legacy References

| Service | File | Legacy Usage | Action Required |
|---------|------|--------------|-----------------|
| `ServicePackageService` | `app/Services/ServicePackageService.php` | Uses `salon_id` parameter, writes to `salon_id` column | Change to `provider_id`, update queries |
| `PortalAuthService` | `app/Services/PortalAuthService.php` | Accepts `salon_id` in register data, treats as Location ID | Change to `location_id`, update validation |
| `CustomerPortalService` | `app/Services/CustomerPortalService.php` | Queries `CustomerPreference` by `salon_id` | Remove provider scoping (preferences are customer-scoped) |
| `CustomerBookingService` | `app/Services/CustomerBookingService.php` | Uses `salon_id` as Location ID | Change to `location_id` |
| `CustomerAvailabilityService` | `app/Services/CustomerAvailabilityService.php` | Has deprecated `salon_id`-based shim | Remove shim, use canonical parameters |
| `BookingRulesValidator` | `app/Services/BookingRulesValidator.php` | References `salon_id` | Change to `location_id` |
| `AvailabilityEngine` | `app/Services/AvailabilityEngine.php` | Creates booking with `salon_id` | Change to `provider_id`/`location_id` |
| `CustomerSpecialistService` | `app/Services/CustomerSpecialistService.php` | Logs `new_salon_id` | Change to `provider_id` |

### 3.4 Import References

| File | Import | Action Required |
|------|--------|-----------------|
| `app/Services/OfferService.php` | `use App\Models\CustomerSalon;` | Remove import (model does not exist) |

---

## 4. CUSTOMER-SALON DATA AUDIT

### 4.1 `customer_salon_id` Column

| Attribute | Value |
|-----------|-------|
| **Exists in Database** | NO |
| **Exists in Migrations** | NO |
| **Exists in Code** | YES (model fillable only) |
| **Data Present** | N/A (column does not exist) |

**Conclusion**: This column was never created or was already dropped. Code references are orphaned.

### 4.2 `CustomerSalon` Table/Model

| Attribute | Value |
|-----------|-------|
| **Table Exists** | NO |
| **Model Exists** | NO (no file found) |
| **Referenced in Code** | YES (relationship methods, imports) |
| **Data Present** | N/A (table does not exist) |

**Conclusion**: The `CustomerSalon` entity does not exist. All references are orphaned code artifacts.

### 4.3 Canonical Customer → Provider Relationship

The canonical relationship is already implemented:

```text
Customer ↔ customer_providers ↔ Provider
```

| Table | Status |
|-------|--------|
| `customer_providers` | Migration pending: `2026_09_22_000011_create_customer_providers_pivot_table` |

**Note**: The `customer_providers` pivot table migration is pending. This is the correct canonical structure.

---

## 5. SPECIALIST RELATIONSHIPS AUDIT

### 5.1 `salon_specialist` Pivot Table

| Attribute | Value |
|-----------|-------|
| **Table Exists** | NO |
| **Migration References** | NONE (already cleaned) |
| **Code References** | NONE (already cleaned) |

**Conclusion**: Specialist relationships are fully canonical via `specialist_assignments`.

### 5.2 Canonical Specialist Relationship

```text
Provider → SpecialistAssignment → Specialist
    ├─ provider_id (required)
    └─ location_id (nullable)
```

**Status**: Fully implemented and canonical.

---

## 6. PROVIDER VS LOCATION SEMANTICS AUDIT

### Code Reference Semantics

| Code Location | Current Usage | Intended Meaning | Canonical Replacement |
|---------------|---------------|-----------------|---------------------|
| `ServicePackageService::purchasePackage($customerId, $salonId, $packageId)` | `salon_id` parameter | Provider ownership | `provider_id` |
| `PortalAuthService::register(['salon_id' => ...])` | `salon_id` parameter | Location context | `location_id` |
| `CustomerBookingService` | `salon_id` from request data | Location context | `location_id` |
| `AvailabilityEngine` | `salon_id` in booking creation | Mixed semantics | Split into `provider_id` + `location_id` |

**Pattern**: Code uses `salon_id` as a catch-all parameter. Semantics must be disambiguated:
- Business ownership → `provider_id`
- Physical location → `location_id`

---

## 7. FOREIGN KEYS AND INDEXES

### Active Foreign Keys (Legacy)

| Table | Column | References | Status |
|-------|--------|-----------|--------|
| NONE | NONE | `salons` | All dropped |

### Active Indexes (Legacy)

| Table | Column | Status |
|-------|--------|--------|
| NONE | `salon_id` | All dropped |

**Conclusion**: No foreign keys or indexes referencing `salons` remain in the database.

---

## 8. MIGRATION HISTORY (Legacy Cleanup Already Completed)

The following migrations have already dropped legacy schema:

| Migration | Date | Action |
|-----------|------|--------|
| `2026_07_31_191126__drop_salon_id_from_specialist_assignments_table.php` | 2026-07-31 | Dropped `salon_id` from specialist_assignments |
| `2026_07_31_191128__drop_salon_id_from_staff_table.php` | 2026-07-31 | Dropped `salon_id` from staff |
| `2026_07_31_191130__drop_salon_id_from_professional_assessments.php` | 2026-07-31 | Dropped `salon_id` from professional_assessments |
| `2026_07_31_191131__drop_salon_id_from_specialist_notes.php` | 2026-07-31 | Dropped `salon_id` from specialist_notes |
| `2026_07_31_191132__drop_salon_id_from_specialist_career_events.php` | 2026-07-31 | Dropped `salon_id` from specialist_career_events |
| `2026_09_22_000000_remove_salon_id_add_location_id_to_media_table.php` | 2026-09-22 | Dropped `salon_id` from media, added `location_id` |
| `2026_09_22_132809_add_status_to_reviews_and_remove_salon_id.php` | 2026-09-22 | Dropped `salon_id` from reviews |

**Conclusion**: Schema cleanup migrations are complete. No new destructive migrations needed.

---

## 9. PROPOSED CLEANUP ACTIONS

### Group A: Model Layer Cleanup (Code-Only)

| Priority | Item | Action | Risk |
|----------|------|--------|------|
| HIGH | `CustomerPackage` fillable | Remove `salon_id`, add `provider_id` | Low |
| HIGH | `CustomerPreference` fillable | Remove `salon_id`, `customer_salon_id` | Low |
| HIGH | `CustomerFavoriteService` fillable | Remove `salon_id` | Low |
| HIGH | `Booking` fillable | Remove `customer_salon_id` | Low |
| HIGH | `CustomerPreference` model | Remove `customerSalon()` method | Low |
| HIGH | `Booking` model | Remove `customerSalon()` method | Low |
| HIGH | `OfferService` | Remove `CustomerSalon` import | Low |

### Group B: Service Layer Cleanup (Code-Only)

| Priority | Service | Action | Risk |
|----------|---------|--------|------|
| HIGH | `ServicePackageService` | Change `salon_id` → `provider_id` parameter | Medium (API contract change) |
| HIGH | `PortalAuthService` | Change `salon_id` → `location_id` parameter | Medium (API contract change) |
| HIGH | `CustomerPortalService` | Remove `salon_id` scoping from preferences | Low |
| HIGH | `CustomerBookingService` | Change `salon_id` → `location_id` | Medium (API contract change) |
| HIGH | `CustomerAvailabilityService` | Remove deprecated `salon_id` shim | Low |
| HIGH | `BookingRulesValidator` | Change `salon_id` → `location_id` | Medium (API contract change) |
| HIGH | `AvailabilityEngine` | Change `salon_id` → `provider_id`/`location_id` | Medium |
| HIGH | `CustomerSpecialistService` | Change `new_salon_id` → `provider_id` in logs | Low |

### Group C: Pending Migration (Schema)

| Priority | Migration | Action | Risk |
|----------|-----------|--------|------|
| MEDIUM | `2026_09_22_000011_create_customer_providers_pivot_table.php` | Run migration to complete canonical Customer → Provider relationship | Low |

### Group D: No Schema Cleanup Required

| Item | Reason |
|------|--------|
| Drop `salons` table | Already dropped |
| Drop `salon_id` columns | Already dropped from all tables |
| Drop foreign keys to `salons` | Already dropped |
| Drop indexes on `salon_id` | Already dropped |
| Drop `salon_specialist` table | Never existed in this codebase |

---

## 10. RISK ASSESSMENT

### High Risk Items

**NONE** - All schema cleanup is already complete.

### Medium Risk Items

- **API Contract Changes**: Services accepting `salon_id` parameters will need API updates
- **Frontend Compatibility**: Frontend may still send `salon_id` in requests
- **Test Failures**: Tests using legacy parameters may fail

### Low Risk Items

- Model fillable array updates
- Removing orphaned relationship methods
- Removing unused imports
- Log message updates

---

## 11. ITEMS THAT MUST BE RETAINED

### Historical Migrations

All historical migrations in `database/migrations/` must be retained:
- Migration history integrity
- Rollback capability
- Documentation of architectural evolution

### Documentation

Documentation files explaining historical architecture must be retained:
- `ARCHITECTURE.md`
- `PROVIDER_TYPE_ARCHITECTURE_AUDIT.md`
- Wave completion reports

### Test Fixtures

Test fixtures that intentionally use legacy names for historical testing may be retained if still required.

---

## 12. STOP CONDITIONS MET

**NONE** - No stop conditions triggered. The cleanup path is clear:
- Database schema is already canonical
- Only code cleanup required
- No ambiguous data mappings
- No mixed semantics in database
- No unknown runtime dependencies

---

## 13. NEXT STEPS (Awaiting Approval)

### Phase 1: Model Layer Cleanup
1. Update model fillable arrays
2. Remove orphaned relationship methods
3. Remove unused imports
4. Run tests

### Phase 2: Service Layer Cleanup
1. Update service method signatures
2. Update internal queries
3. Update API controllers
4. Update frontend to match new API contracts
5. Run tests

### Phase 3: Final Migration
1. Run pending `customer_providers` migration
2. Verify canonical relationships
3. Run full test suite

---

## 14. SUMMARY

| Category | Count | Status |
|----------|-------|--------|
| Active `salon_id` columns in database | 0 | ✓ Clean |
| Active `customer_salon_id` columns in database | 0 | ✓ Clean |
| Foreign keys to `salons` | 0 | ✓ Clean |
| Indexes on `salon_id` | 0 | ✓ Clean |
| `salons` table existence | NO | ✓ Dropped |
| `salon_specialist` table existence | NO | ✓ Never existed |
| Orphaned model fillable fields | 4 | ⚠️ Code cleanup needed |
| Orphaned relationship methods | 2 | ⚠️ Code cleanup needed |
| Service layer legacy references | 8 | ⚠️ Code cleanup needed |
| Pending canonical migrations | 1 | ⚠️ Needs execution |

**Overall Assessment**: Database schema is fully canonical. Cleanup is code-only.

---

**END OF INVENTORY**

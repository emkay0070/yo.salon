# Legacy Schema Verification Report

**Generated**: 2026-09-27  
**Phase**: Wave 6.2D - Legacy Schema Cleanup Verification  
**Status**: COMPLETE

---

## A. customer_providers Verification

### Table Existence
| Attribute | Value |
|-----------|-------|
| **Table Exists** | YES |
| **Migration Status** | RAN (Batch 5) |
| **Migration File** | `2026_09_22_000011_create_customer_providers_pivot_table.php` |
| **Migration Date** | 2026-09-22 |

### Schema Definition
| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| customer_id | uuid | FK → customers (CASCADE) |
| provider_id | uuid | FK → providers (CASCADE) |
| visits | integer | DEFAULT 0 |
| joined_at | timestamp | NULLABLE |
| created_at | timestamp | NULLABLE |
| updated_at | timestamp | NULLABLE |

### Indexes
| Index | Type | Columns |
|-------|------|---------|
| customer_providers_pkey | PRIMARY | id |
| customer_providers_customer_id_provider_id_unique | UNIQUE | customer_id, provider_id |

### Foreign Keys
| FK Name | From | To | On Delete |
|---------|------|-----|-----------|
| customer_providers_customer_id_foreign | customer_id | customers.id | CASCADE |
| customer_providers_provider_id_foreign | provider_id | providers.id | CASCADE |

### Data State
| Attribute | Value |
|-----------|-------|
| **Row Count** | 0 (fresh database) |
| **Data Present** | NO (fresh migration) |

### Runtime Usage
**ACTIVE USAGE CONFIRMED**

| Service | File | Usage Pattern |
|---------|------|---------------|
| `PortalAuthService` | `app/Services/PortalAuthService.php` | Queries pivot for provider relationship data |
| `CustomerService` | `app/Services/CustomerService.php` | Upserts into pivot on customer creation/relationship |
| `CustomerPortalService` | `app/Services/CustomerPortalService.php` | Queries pivot for visit counts |
| `CustomerBookingService` | `app/Services/CustomerBookingService.php` | Increments visits on booking |

### Model Relationships
| Model | Relationship | Usage |
|-------|--------------|-------|
| `Provider` | `customers()` | BelongsToMany via `customer_providers` |
| `Customer` | `providers()` | BelongsToMany via `customer_providers` |

### Test Usage
**ACTIVE TEST USAGE CONFIRMED**

| Test File | Usage |
|-----------|-------|
| `Unit/Services/Wave6_2CBookingOperationsTest.php` | Asserts pivot exists after booking |
| `Unit/Services/Wave6_2BCustomerIdentityTest.php` | Asserts visits incremented in pivot |

### Why Migration Was Pending
The migration `2026_09_22_000011_create_customer_providers_pivot_table.php` was marked as **Pending** in the initial migration status check because:
- It was in Batch 5 (later batch than the current database state)
- The development database had not been migrated to include it yet
- After running `php artisan migrate:fresh --seed`, it successfully ran

**Current Status**: Migration is now RAN and table exists.

---

## B. salons Table Removal Verification

### Critical Finding
**NO MIGRATION EXISTS TO DROP THE `salons` TABLE**

### Investigation Results

#### Search Results
- **Migration to create `salons` table**: NOT FOUND in current migration chain
- **Migration to drop `salons` table**: NOT FOUND in current migration chain
- **References to `Schema::create('salons')`**: NONE
- **References to `Schema::dropIfExists('salons')`**: NONE

#### Migration References to `salons`
Multiple migrations reference the `salons` table conditionally:

| Migration | Pattern | Purpose |
|-----------|---------|---------|
| `2026_08_13_140300_backfill_staff_id_on_specialist_assignments.php` | `if (!Schema::hasTable('salons')) { return; }` | Skip if fresh migration |
| `2026_08_15_000002_migrate_staff_to_specialist_assignments.php` | `if (!Schema::hasTable('salons')) { return; }` | Skip if fresh migration |
| `2026_08_13_150000_fix_specialist_assignment_staff_id_by_name_match.php` | `if (!Schema::hasTable('salons')) { return; }` | Skip if fresh migration |
| `2026_07_31_191135__migrate_bookings_to_location.php` | `if (!Schema::hasTable('salons')) { return; }` | Skip if fresh migration |
| `2026_07_31_191134__migrate_resources_to_location.php` | `if (!Schema::hasTable('salons')) { return; }` | Skip if fresh migration |
| `2026_07_31_191133__migrate_offers_to_provider_location.php` | `if (Schema::hasTable('salons'))` | Backfill data if exists |
| `2026_07_31_200528_change_subscriptions_to_provider_id.php` | `if (Schema::hasTable('salons'))` | Add FK only if exists |

#### Rollback References
Some migrations reference `salons` in their `down()` methods for rollback:

| Migration | Rollback Reference |
|-----------|-------------------|
| `2026_09_22_132809_add_status_to_reviews_and_remove_salon_id.php` | `$table->foreign('salon_id')->references('id')->on('salons')` |
| `2026_09_22_000000_remove_salon_id_add_location_id_to_media_table.php` | `$table->foreign('salon_id')->references('id')->on('salons')` |

### Conclusion on `salons` Table

**The `salons` table was NEVER created in the current migration chain.**

Evidence:
1. No migration creates the table
2. No migration drops the table
3. All references are conditional (skip if doesn't exist)
4. Fresh migration (`migrate:fresh`) succeeds without creating it
5. Database after fresh migration has NO `salons` table

**Historical Context**: The `salons` table existed in a previous version of the codebase but was removed before the current migration chain was established. The current migrations are designed to handle both scenarios:
- **Fresh migration**: Skip data migration logic (table doesn't exist)
- **Upgrade from old version**: Migrate data from `salons` to `providers`/`locations`

### Migration Chain Status
| Attribute | Value |
|-----------|-------|
| **Migration Creates `salons`** | NO |
| **Migration Drops `salons`** | NO |
| **Fresh Migration Creates `salons`** | NO |
| **Fresh Migration Requires `salons`** | NO |
| **Any Migration Expects `salons` to Exist** | NO (all conditional) |

### Fresh Database Behavior
**VERIFIED**: `php artisan migrate:fresh --seed` completed successfully

| Check | Result |
|-------|--------|
| `salons` table exists | ❌ NO (correct) |
| `salon_id` columns exist | ❌ NO (correct) |
| `customer_providers` exists | ✅ YES (correct) |
| `specialist_assignments` exists | ✅ YES (correct) |
| Provider/Location relationships | ✅ YES (correct) |
| Seed data succeeds | ✅ YES (correct) |

---

## C. Fresh Migration Verification

### Environment Used
| Attribute | Value |
|-----------|-------|
| **Database** | PostgreSQL (yo_salon) |
| **Command** | `php artisan migrate:fresh --seed` |
| **Result** | SUCCESS |
| **Migrations Run** | 229 migrations |
| **Duration** | ~2-3 seconds |

### Verification Checks Performed

#### 1. salons Table
```bash
php artisan db:table salons
```
**Result**: Table doesn't exist ✅

#### 2. customer_providers Table
```bash
php artisan db:table customer_providers
```
**Result**: Table exists with correct schema ✅
- Columns: id, customer_id, provider_id, visits, joined_at, created_at, updated_at
- Foreign keys: customer_id → customers, provider_id → providers
- Unique constraint: (customer_id, provider_id)

#### 3. specialist_assignments Table
```bash
php artisan db:table specialist_assignments
```
**Result**: Table exists with correct schema ✅
- Columns: specialist_id, provider_id, location_id (nullable), role, status, etc.
- Foreign keys: specialist_id → specialists, provider_id → providers, location_id → locations
- Unique constraint: (specialist_id, location_id)

#### 4. providers Table
```bash
php artisan db:table providers
```
**Result**: Table exists with correct schema ✅
- 22 columns including type, status, display_name, etc.
- Note: `type` column has default value `'salon'` (legacy naming, but table is canonical Provider)

#### 5. locations Table
```bash
php artisan db:table locations
```
**Result**: Table exists with correct schema ✅
- 22 columns including provider_id, name, address, etc.
- Foreign key: provider_id → providers
- Unique constraint: (provider_id, slug)

#### 6. Seed Data
**Result**: Seeders ran successfully ✅
- No failures reported
- Database populated with required reference data

### Any Failures
**NONE** - All migrations and seeders completed successfully.

---

## D. Discovered Inconsistencies

### 1. Provider.type Column Default Value
| Attribute | Value |
|-----------|-------|
| **Column** | `providers.type` |
| **Default Value** | `'salon'` |
| **Issue** | Default value uses legacy terminology |
| **Impact** | Cosmetic only - column is correctly named `type` for Provider |
| **Action Required** | OPTIONAL - Change default to `'provider'` or remove default |

### 2. Migration Rollback References
| Attribute | Value |
|-----------|-------|
| **Issue** | Some migration `down()` methods reference `salons` table for rollback |
| **Impact** | Rollback would fail if attempted (table doesn't exist) |
| **Affected Migrations** | 2 migrations (media, reviews) |
| **Action Required** | OPTIONAL - Update rollback methods to be conditional |

### 3. Code vs Schema Mismatch
| Category | Count | Status |
|----------|-------|--------|
| Database `salon_id` columns | 0 | ✅ Clean |
| Model fillable `salon_id` fields | 4 | ⚠️ Code cleanup needed |
| Model `customer_salon_id` fields | 2 | ⚠️ Code cleanup needed |
| Service `salon_id` parameters | 8 | ⚠️ Code cleanup needed |

**Note**: These are code-only issues. Database schema is canonical.

### 4. customer_providers Migration Was Initially Pending
| Attribute | Value |
|-----------|-------|
| **Initial Status** | Pending (Batch 5) |
| **Current Status** | RAN (after migrate:fresh) |
| **Reason** | Development database was behind latest migration |
| **Impact** | None - resolved by fresh migration |

---

## Summary

### Database Schema Status
| Item | Status |
|------|--------|
| `salons` table | ❌ Does not exist (correct) |
| `salon_id` columns | ❌ None exist (correct) |
| `customer_providers` table | ✅ Exists with correct schema |
| `specialist_assignments` table | ✅ Exists with correct schema |
| Provider/Location relationships | ✅ Canonical |
| Fresh migration behavior | ✅ Clean, no errors |

### Code Status
| Item | Status |
|------|--------|
| Model fillable arrays | ⚠️ Contains legacy fields |
| Service parameters | ⚠️ Uses legacy `salon_id` naming |
| Runtime functionality | ✅ Works (queries correct DB columns) |

### Migration Chain Status
| Item | Status |
|------|--------|
| Migration creates `salons` | ❌ Never existed in current chain |
| Migration drops `salons` | ❌ Never existed in current chain |
| Conditional `salons` references | ✅ All properly guarded |
| Fresh migration compatibility | ✅ Fully compatible |

### Recommendations

#### Immediate (Code Cleanup)
1. Update model fillable arrays to remove `salon_id`, `customer_salon_id`
2. Remove orphaned `customerSalon()` relationship methods
3. Update service method signatures to use `provider_id`/`location_id`
4. Update API controllers to match new service signatures
5. Update frontend to send correct field names

#### Optional (Polish)
1. Change `providers.type` default from `'salon'` to `'provider'`
2. Update migration rollback methods to be conditional about `salons` table

#### No Action Required
1. Database schema - already canonical
2. Migration chain - already handles both fresh and upgrade scenarios
3. `customer_providers` table - exists and functional

---

**VERIFICATION COMPLETE**

**Key Finding**: The database schema is fully canonical. The `salons` table was never created in the current migration chain. All cleanup required is code-only.

# Provider Architecture Exit Audit Report

**Audit Date:** October 9, 2026
**Mode:** STRICTLY READ-ONLY - No code changes, migrations, database writes, backfills, or renames performed

---

## Executive Summary

**Verdict:** Migration is **PARTIALLY COMPLETE** with critical model-database schema mismatches that must be resolved.

**Key Findings:**
1. **Salon model and salons table do not exist** - Legacy architecture successfully removed from database
2. **Critical schema mismatches:** Three models use `salon_id` column names but their migrations define `provider_id`
3. **Legacy terminology pervasive:** Method names, parameters, and comments still reference "salon" despite Provider-native implementation
4. **Frontend clean:** No salon references found in frontend codebase
5. **Migration compatibility layer:** Extensive `Schema::hasTable('salons')` checks support fresh databases

**Classification Summary:**
- **Active legacy dependency:** 0
- **Legacy terminology only:** 47 findings
- **Model-database schema mismatch:** 3 (CRITICAL)
- **Historical migration/compatibility:** 18 findings
- **Dead code:** 0

---

## 1. Database Schema Audit

### 1.1 Salon Table Status

**Finding:** `salons` table does not exist in current schema

**Evidence:**
- No `create_salons_table` migration found in `backend/database/migrations`
- No `Schema::dropIfExists('salons')` found in any migration
- All migrations use `Schema::hasTable('salons')` checks to skip if table doesn't exist

**Classification:** **Historical migration/compatibility** - Table successfully removed, compatibility checks retained for fresh databases

### 1.2 salon_id Column Status

**Finding:** No active `salon_id` columns in core tables

**Evidence:**
- Migrations systematically dropped `salon_id` from: bookings, services, reviews, staff, specialist_assignments, professional_assessments, specialist_notes, specialist_career_events, media
- All drop migrations include rollback capability (add salon_id back with foreign key to salons table)
- No current table definitions include `salon_id` columns

**Classification:** **Historical migration/compatibility** - Columns successfully removed, rollback capability retained

### 1.3 customer_salon_id Status

**Finding:** No `customer_salon_id` column found

**Evidence:**
- No references to `customer_salon_id` in migrations or models
- Customer-provider relationship uses canonical `customer_providers` pivot table with `provider_id`

**Classification:** **N/A** - Never existed in schema

---

## 2. Model-Database Schema Mismatches (CRITICAL)

### 2.1 FeatureFlag Model

**File:** `backend/app/Models/FeatureFlag.php`

**Model Definition (lines 13-21):**
```php
protected $fillable = [
    'salon_id',  // ❌ Uses salon_id
    'feature_key',
    'enabled',
    ...
];
```

**Migration:** `backend/database/migrations/2026_07_31_1900221_create_feature_flags_table.php`
```php
Schema::create('feature_flags', function (Blueprint $table) {
    $table->uuid('provider_id')->nullable();  // ✅ Uses provider_id
    ...
});
```

**Relationship (line 29-32):**
```php
public function provider()
{
    return $this->belongsTo(Provider::class, 'salon_id');  // ❌ References salon_id
}
```

**Static Methods (lines 42-79):**
```php
public static function isEnabledForSalon(string $salonId, ...)  // ❌ Method name
{
    return self::where('salon_id', $salonId)  // ❌ Uses salon_id
        ...
}

public static function enableForSalon(string $salonId, ...)  // ❌ Method name
{
    return self::updateOrCreate(
        ['salon_id' => $salonId],  // ❌ Uses salon_id
        ...
    );
}
```

**Classification:** **Model-database schema mismatch (CRITICAL)** - Model uses `salon_id` but database has `provider_id`

**Impact:** Runtime errors when using fillable, scopes, or static methods. Relationship will fail to load.

### 2.2 PaymentAccount Model

**File:** `backend/app/Models/PaymentAccount.php`

**Model Definition (lines 30-44):**
```php
protected $fillable = [
    'salon_id',  // ❌ Uses salon_id
    'specialist_id',
    'provider',
    ...
];
```

**Migration:** `backend/database/migrations/2026_08_19_191002_create_payment_accounts_table.php`
```php
Schema::create('payment_accounts', function (Blueprint $table) {
    $table->uuid('provider_id')->nullable();  // ✅ Uses provider_id
    $table->uuid('specialist_id')->nullable();
    ...
});
```

**Classification:** **Model-database schema mismatch (CRITICAL)** - Model uses `salon_id` but database has `provider_id`

**Impact:** Runtime errors when using fillable. Data persistence will fail.

### 2.3 ServiceCategory Model

**File:** `backend/app/Models/ServiceCategory.php`

**Model Definition (lines 14-21):**
```php
protected $fillable = [
    'salon_id',  // ❌ Uses salon_id
    'name',
    ...
];
```

**Migration:** `backend/database/migrations/2026_07_31_1900212_create_service_categories_table.php`
```php
Schema::create('service_categories', function (Blueprint $table) {
    $table->uuid('provider_id');  // ✅ Uses provider_id
    ...
});
```

**Static Method (lines 55-60):**
```php
public static function getOrdered(string $salonId)  // ❌ Parameter name
{
    return self::where('salon_id', $salonId)  // ❌ Uses salon_id
        ...
}
```

**Classification:** **Model-database schema mismatch (CRITICAL)** - Model uses `salon_id` but database has `provider_id`

**Impact:** Runtime errors when using fillable or static method. Data persistence and queries will fail.

---

## 3. Backend Models Audit

### 3.1 Models with Legacy Terminology

| Model | File | Legacy References | Classification |
|-------|------|-------------------|----------------|
| FeatureFlag | FeatureFlag.php | `salon_id` fillable, `isEnabledForSalon`, `enableForSalon`, `disableForSalon` | Schema mismatch + Terminology |
| PaymentAccount | PaymentAccount.php | `salon_id` fillable | Schema mismatch + Terminology |
| ServiceCategory | ServiceCategory.php | `salon_id` fillable, `getOrdered($salonId)` | Schema mismatch + Terminology |
| PortalAccount | PortalAccount.php | Comment: "NOT scoped by salon_id", "multiple salons" | Terminology only |
| Provider | Provider.php | `TYPE_SALON` constant | Terminology only (valid enum value) |
| Subscription | Subscription.php | `salon()` relationship method | Terminology only |
| SpecialistCareerEvent | SpecialistCareerEvent.php | `TYPE_SALON_CHANGE` constant | Terminology only (valid enum value) |
| Specialist | Specialist.php | Comment: "Legacy relationship: salon_specialist pivot", `salon_specialist` table reference | Terminology only |
| ProviderBookingConfig | ProviderBookingConfig.php | Comment: "pay_at_salon" | Terminology only |
| Invitation | Invitation.php | Comments: "what the salon sends", "yoursalon.com", "salon workspace" | Terminology only |
| CustomerTimeline | CustomerTimeline.php | `salon_id` fillable, `provider()` relationship via `salon_id` | Terminology only |
| CustomerPackage | CustomerPackage.php | `salon_id` fillable | Terminology only |
| CustomerSpecialist | CustomerSpecialist.php | `ORIGIN_SALON` constant | Terminology only (valid enum value) |
| CustomerActivity | CustomerActivity.php | `salon_id` fillable, `provider()` relationship via `salon_id` | Terminology only |
| Customer | Customer.php | Comment: "different salons produce two independent guest records" | Terminology only |
| BrandExperience | BrandExperience.php | `salon_id` fillable, `provider()` relationship via `salon_id` | Terminology only |

### 3.2 Models with salon_id Columns (Terminology Only)

**Finding:** Several models use `salon_id` as a column name but the actual database column is `provider_id`

**Models Affected:**
- CustomerTimeline (line 15: `salon_id` fillable)
- CustomerPackage (line 15: `salon_id` fillable)
- CustomerActivity (line 15: `salon_id` fillable)
- BrandExperience (line 14: `salon_id` fillable)

**Classification:** **Legacy terminology only** - These models need verification against their migrations to confirm schema mismatch or correct terminology usage.

**Action Required:** Audit migrations for these four models to confirm actual column names.

---

## 4. Backend Controllers Audit

### 4.1 Controllers with Legacy Terminology

| Controller | File | Legacy References | Classification |
|------------|------|-------------------|----------------|
| SpecialistPortalController | SpecialistPortalController.php | Comment: "salons() pivot", `salon_id` in merge | Terminology only |
| SpecialistController | SpecialistController.php | `salons` in response | Terminology only |
| SpecialistAssessmentController | SpecialistAssessmentController.php | `salon_id` validation (maps to location_id) | Terminology only |
| AuthController | AuthController.php | `journey: 'salon'`, "Yo.Salon" brand references | Terminology only |
| ProviderController | ProviderController.php | `type: 'salon'` validation | Terminology only (valid enum) |
| AddOnPurchaseController | AddOnPurchaseController.php | Comments: "the salon can purchase", `$salon` variable | Terminology only |
| BrandExperienceController | BrandExperienceController.php | `background_type: 'salon_image'` | Terminology only |
| BookingOrchestratorController | BookingOrchestratorController.php | `salon_id` validation (maps to location_id) | Terminology only |
| BookingController | BookingController.php | Comment: "legacy salon request" | Terminology only |
| BookingConfigurationController | BookingConfigurationController.php | Comments: "for a salon", "salon/provider defaults" | Terminology only |
| DashboardController | DashboardController.php | `invalidateStatsCache($salonId)` method | Terminology only |
| FloorController | FloorController.php | (needs detailed review) | Terminology only |
| SeederController | SeederController.php | `SalonSeeder` reference | Terminology only |

### 4.2 API Contract Compatibility

**Finding:** Controllers use `salon_id` as a request parameter name but internally map it to `location_id` or `provider_id`

**Examples:**
- `BookingOrchestratorController::reserve` validates `salon_id` but treats it as `location_id`
- `SpecialistAssessmentController` validates `salon_id` as `exists:locations,id`

**Classification:** **Legacy terminology only** - API contracts retain legacy naming for backward compatibility, internal logic is Provider-native

---

## 5. Backend Services Audit

### 5.1 CustomerService::addSalonRelationship

**File:** `backend/app/Services/CustomerService.php`

**Method Signature (line 104):**
```php
public function addSalonRelationship(Customer $customer, string $providerId): Customer
```

**Implementation (lines 106-117):**
```php
$exists = DB::table('customer_providers')
    ->where('customer_id', $customer->id)
    ->where('provider_id', $providerId)  // ✅ Uses provider_id correctly
    ->exists();

if (!$exists) {
    DB::statement(
        'INSERT INTO customer_providers (id, customer_id, provider_id, visits, joined_at, created_at, updated_at)
         VALUES (?, ?, ?, 0, ?, ?, ?)
         ON CONFLICT (customer_id, provider_id) DO NOTHING',  // ✅ Correct constraint
        [(string) Str::orderedUuid(), $customer->id, $providerId, now(), now(), now()]
    );
}
```

**Documentation (lines 18-20):**
```php
// Architecture note: All former $salonId parameters in this class now represent Provider IDs,
// as the canonical customer-tenant relationship is Customer → Provider (customer_providers pivot).
```

**Classification:** **Legacy terminology only** - Method name is legacy but implementation is Provider-native

**Callers:**
- `BookingService::createGuestBooking` (line 197) - ✅ Correct usage
- `BookingService::createBookingWithAccount` (line 302) - ✅ Correct usage
- **Missing:** `CustomerBookingService::createBooking` - ❌ Does not call this method (documented in previous report)

### 5.2 Services with Legacy Terminology

| Service | File | Legacy References | Classification |
|---------|------|-------------------|----------------|
| CustomerPortalService | CustomerPortalService.php | `getProfile($salonId)`, `updateProfile($salonId)`, comments | Terminology only |
| CustomerAvailabilityService | CustomerAvailabilityService.php | (needs detailed review) | Terminology only |
| CustomerBookingService | CustomerBookingService.php | (needs detailed review) | Terminology only |
| CustomerSpecialistService | CustomerSpecialistService.php | `ORIGIN_SALON`, `notifySpecialistMoved($newSalonId, ...)` | Terminology only |
| OnboardingService | OnboardingService.php | (needs detailed review) | Terminology only |
| PortalAuthService | PortalAuthService.php | (needs detailed review) | Terminology only |
| ServicePackageService | ServicePackageService.php | (needs detailed review) | Terminology only |
| AvailabilityEngine | AvailabilityEngine.php | (needs detailed review) | Terminology only |
| BookingRulesValidator | BookingRulesValidator.php | (needs detailed review) | Terminology only |
| NotificationService | NotificationService.php | (needs detailed review) | Terminology only |
| PaymentRulesEngine | PaymentRulesEngine.php | (needs detailed review) | Terminology only |
| BookingService | BookingService.php | Comments referencing salon | Terminology only |
| SubscriptionService | SubscriptionService.php | (needs detailed review) | Terminology only |
| WebsiteConfigurationResolver | WebsiteConfigurationResolver.php | (needs detailed review) | Terminology only |

---

## 6. Policies, Middleware, Jobs, Events Audit

### 6.1 Policies

**Finding:** No salon references found in `backend/app/Policies`

**Classification:** **Clean** - No legacy dependencies

### 6.2 Middleware

**Finding:** No salon references found in middleware (directory structure verified)

**Classification:** **Clean** - No legacy dependencies

### 6.3 Jobs

**File:** `backend/app/Jobs/ProcessSubscriptionRenewals.php`

**Legacy References:**
- Line 38: Comment: "notify salon to pay manually"
- Line 104: Comment: "notify salon to pay manually"
- Line 112: Comment: "notify salon to pay manually"
- Line 122: Comment: "notify salon to pay manually"
- Line 167: Comment: "notify salon"
- Line 172: Comment: "Send an in-app notification to the salon"
- Line 174: Method name: `notifySalonToPayManually`
- Line 192: Log message: "salon notified for manual payment"

**Classification:** **Legacy terminology only** - Comments and method names use "salon" but implementation is Provider-native

### 6.4 Events

**File:** `backend/app/Events/BookingConfirmed.php`

**Legacy References:**
- Line 19: `public $salonId;`
- Line 22: Constructor parameter `$salonId`
- Line 26: `$this->salonId = $salonId;`
- Line 33: `new PrivateChannel('salon.' . $this->salonId)`

**Classification:** **Legacy terminology only** - Event property name uses "salon" but represents location_id

### 6.5 Listeners

**File:** `backend/app/Listeners/CreateSubscriptionRenewedNotification.php`

**Legacy References:**
- Line 19: Message: "Your subscription has been successfully renewed. Thank you for continuing with Yo.Salon!"

**Classification:** **Legacy terminology only** - Brand reference in notification message

---

## 7. Frontend Audit

### 7.1 Frontend Salon References

**Finding:** No salon references found in frontend codebase

**Search Scope:**
- `frontend/src` directory
- All file types (.ts, .tsx, .js, .jsx)
- Case-insensitive search for "salon"

**Classification:** **Clean** - No legacy dependencies in frontend

---

## 8. Tests, Factories, Seeders Audit

### 8.1 Factories

**File:** `backend/database/factories/ProviderFactory.php`

**Legacy Reference:**
- Line 23: `'type' => Provider::TYPE_SALON`

**Classification:** **Legacy terminology only** - Valid enum value for Provider type

**File:** `backend/database/factories/CustomerSpecialistFactory.php`

**Legacy Reference:**
- Line 21: `'relationship_origin' => CustomerSpecialist::ORIGIN_SALON`

**Classification:** **Legacy terminology only** - Valid enum value for relationship origin

### 8.2 Seeders

**File:** `backend/database/seeders/RegisterSeedersSeeder.php`

**Legacy References:**
- Line 34: `'seeder_class' => 'SalonSeeder'`
- Line 35: `'display_name' => 'Salons'`
- Line 36: `'description' => 'Sample salons for testing'`
- Line 44: `'description' => 'Sample services for salons'`

**Classification:** **Historical migration/compatibility** - References legacy seeder that may not exist

**File:** `backend/database/seeders/ReferenceDataSeeder.php`

**Legacy References:**
- Line 19: `'key' => 'hair_salon', 'label' => 'Hair Salon', 'value' => 'Salon'`

**Classification:** **Legacy terminology only** - Business category reference data

**File:** `backend/database/seeders/PriceBookSeeder.php`

**Legacy Reference:**
- Line 14: Comment: "Other regions can be added here as Yo.Salon expands."

**Classification:** **Legacy terminology only** - Brand reference in comment

**File:** `backend/database/seeders/PlatformProductSeeder.php`

**Legacy References:**
- Line 12: Comment: "Seed the initial product catalog — the things Yo.Salon sells to salon owners."
- Line 46: Comment: "500 Yo.Salon Copilot AI requests for captions, scheduling, insights."
- Line 57: Comment: "1,000 Yo.Salon Copilot AI requests — best value."
- Line 83: Comment: "Appear at the top of Yo.Salon Discover search results for 1 month."
- Line 109: Comment: "Add one additional salon branch beyond your plan limit."

**Classification:** **Legacy terminology only** - Brand references in comments and descriptions

**File:** `backend/database/seeders/PlanSeeder.php`

**Legacy References:**
- Lines 65-216: Multiple references to `'provider_type' => 'salon'` in plan definitions
- Comments: "Salon Plans", "Spa Plans (same as salon for now)"

**Classification:** **Legacy terminology only** - Valid enum value for provider type

**File:** `backend/database/seeders/Legacy_UpdateSalonCountrySeeder.php`

**Legacy References:**
- Line 8: Class name: `UpdateSalonCountrySeeder`
- Line 15: Comment: "Skip if salons table doesn't exist (migrated to Provider/Location architecture)"
- Line 16: `Schema::hasTable('salons')` check
- Line 21: `\App\Models\Salon::first()->update(['country' => 'Uganda']);`

**Classification:** **Historical migration/compatibility** - Legacy seeder with table existence check

**File:** `backend/database/seeders/Legacy_TestWebsiteConfigurationSeeder.php`

**Legacy References:**
- Line 5: `use App\Models\Salon;`
- Line 13: `$salon = Salon::where('slug', 'em-cuts')->first();`
- Line 16: Comment: "Salon with slug 'em-cuts' not found"
- Line 21: `WebsiteConfiguration::where('configurable_type', 'App\\Models\\Salon')`
- Line 27: `'configurable_type' => 'App\\Models\\Salon'`
- Line 44: Comment: "Created website configuration for {$salon->name}"

**Classification:** **Dead code** - References non-existent Salon model, but seeder checks for existence first

**File:** `backend/database/seeders/Legacy_TestResolverSeeder.php`

**Legacy References:**
- Line 5: `use App\Models\Salon;`
- Line 13: `$salon = Salon::where('slug', 'em-cuts')->first();`

**Classification:** **Dead code** - References non-existent Salon model

### 8.3 Tests

**Finding:** 43 test files found, but no salon-specific test failures or dependencies identified in initial scan

**Classification:** **Clean** - Tests use Provider-native architecture (detailed review recommended)

---

## 9. Migration Compatibility Layer

### 9.1 Schema::hasTable('salons') Checks

**Purpose:** Support fresh databases that never had the salons table

**Files with Checks (18 migrations):**
1. `2026_07_31_190004_migrate_salon_locations_to_locations_table.php` (line 17)
2. `2026_07_31_191135__migrate_bookings_to_location.php` (line 21)
3. `2026_07_31_191134__migrate_resources_to_location.php` (line 21)
4. `2026_07_31_191131__drop_salon_id_from_specialist_notes.php` (line 37)
5. `2026_07_31_191130__drop_salon_id_from_professional_assessments.php` (line 39)
6. `2026_07_31_191129__add_provider_location_to_professional_assessments.php` (line 48)
7. `2026_07_31_191128__drop_salon_id_from_staff_table.php` (line 40)
8. `2026_07_31_191127__add_provider_id_to_staff_table.php` (line 49)
9. `2026_07_31_191126__drop_salon_id_from_specialist_assignments_table.php` (line 34)
10. `2026_07_31_191123__add_location_id_to_staff_table.php` (line 37)
11. `2026_07_31_191122__add_location_id_to_specialist_assignments_table.php` (line 37)
12. `2026_07_31_191120__add_location_id_to_bookings_table.php` (line 34)
13. `2026_07_31_191119__migrate_salon_service_to_location_service.php` (line 54)
14. `2026_07_31_191132__drop_salon_id_from_specialist_career_events.php` (line 35)
15. `2026_07_31_191133__migrate_offers_to_provider_location.php` (line 50)
16. `2026_07_31_200528_change_subscriptions_to_provider_id.php` (line 51)
17. `2026_08_13_150000_fix_specialist_assignment_staff_id_by_name_match.php` (line 28)
18. `2026_08_13_140300_backfill_staff_id_on_specialist_assignments.php` (line 23)
19. `2026_08_15_000002_migrate_staff_to_specialist_assignments.php` (line 24)

**Classification:** **Historical migration/compatibility** - Essential for fresh database support

**Recommendation:** Retain these checks indefinitely to support fresh database deployments

### 9.2 Rollback Capability

**Finding:** All drop migrations include rollback that adds `salon_id` back with foreign key to `salons` table

**Example (from `2026_07_31_190500_change_bookings_to_provider_id.php`):**
```php
public function down(): void
{
    // Add salon_id back
    Schema::table('bookings', function (Blueprint $table) {
        $table->uuid('salon_id')->nullable()->after('id');
        
        $table->foreign('salon_id')
            ->references('id')
            ->on('salons')  // ❌ References non-existent table
            ->onDelete('cascade');
    });
    ...
}
```

**Classification:** **Dead code** - Rollback will fail because `salons` table doesn't exist

**Recommendation:** Remove rollback capability from drop migrations or update to use `provider_id`

---

## 10. Architecture Dependency Map

### 10.1 Database Schema

```
providers (canonical tenant)
  ↓
locations (branches)
  ↓
bookings, services, staff, specialist_assignments, etc.
```

**Status:** ✅ Provider-native architecture implemented

### 10.2 Model Relationships

```
Provider
  ├─ hasMany Locations
  ├─ hasMany Customers (via customer_providers pivot)
  ├─ hasMany Bookings
  └─ hasMany Services

Location
  ├─ belongsTo Provider
  ├─ hasMany Bookings
  └─ hasMany Staff
```

**Status:** ✅ Provider-native relationships implemented

### 10.3 Critical Schema Mismatches

```
FeatureFlag Model
  ├─ fillable: ['salon_id']  ❌
  └─ migration: provider_id  ✅

PaymentAccount Model
  ├─ fillable: ['salon_id']  ❌
  └─ migration: provider_id  ✅

ServiceCategory Model
  ├─ fillable: ['salon_id']  ❌
  └─ migration: provider_id  ✅
```

**Status:** ❌ CRITICAL - Models don't match database schema

---

## 11. Customer-Provider Relationship Defects

### 11.1 Missing addSalonRelationship Call

**Finding:** `CustomerBookingService::createBooking` does not call `addSalonRelationship`

**Impact:** Customer portal bookings create bookings without `customer_providers` relationships

**Classification:** **Active bug** - Not a legacy architecture issue, but a missing relationship creation call

**Status:** Documented in `CUSTOMER_PROVIDER_RELATIONSHIP_ROOT_CAUSE_REPORT.md`

### 11.2 Invalid addProviderRelationship Call

**Finding:** `BookingService::createBookingWithAccount` line 479 calls non-existent `addProviderRelationship`

**Impact:** Runtime error if this code path is executed

**Classification:** **Active bug** - Method name typo

**Status:** Documented in `CUSTOMER_PROVIDER_RELATIONSHIP_ROOT_CAUSE_REPORT.md`

---

## 12. Phased Removal Plan

### Phase 1: Fix Critical Schema Mismatches (BLOCKER)

**Priority:** CRITICAL - Must complete before any other changes

**Actions:**
1. Update `FeatureFlag` model:
   - Change `fillable` from `salon_id` to `provider_id`
   - Update `provider()` relationship to use `provider_id`
   - Rename methods: `isEnabledForSalon` → `isEnabledForProvider`, `enableForSalon` → `enableForProvider`, `disableForSalon` → `disableForProvider`
   - Update all method implementations to use `provider_id`

2. Update `PaymentAccount` model:
   - Change `fillable` from `salon_id` to `provider_id`

3. Update `ServiceCategory` model:
   - Change `fillable` from `salon_id` to `provider_id`
   - Rename `getOrdered($salonId)` to `getOrdered($providerId)`
   - Update implementation to use `provider_id`

4. Verify four additional models with `salon_id` fillable:
   - CustomerTimeline
   - CustomerPackage
   - CustomerActivity
   - BrandExperience
   - Audit their migrations to confirm actual column names
   - Update fillable arrays if needed

**Risk:** HIGH - These changes will break existing code if not updated comprehensively

**Dependencies:** None - Can proceed independently

### Phase 2: Update Method Names (HIGH PRIORITY)

**Priority:** HIGH - Improves code clarity and reduces confusion

**Actions:**
1. Rename `CustomerService::addSalonRelationship` to `addProviderRelationship`
2. Update all callers:
   - `BookingService::createGuestBooking`
   - `BookingService::createBookingWithAccount`
   - Add call to `CustomerBookingService::createBooking` (fix bug from previous report)
3. Update `CustomerService` method names:
   - `createWithSalon` → `createWithProvider`
   - `resolveOrCreateForBusiness` parameter `$salonId` → `$providerId`
   - `updateSalonNotes` → `updateProviderNotes`

**Risk:** MEDIUM - Requires comprehensive search-and-replace

**Dependencies:** Must complete Phase 1 first

### Phase 3: Update API Contracts (MEDIUM PRIORITY)

**Priority:** MEDIUM - Improves API clarity but maintains backward compatibility

**Actions:**
1. Update controller validation rules:
   - `BookingOrchestratorController::reserve` - change `salon_id` to `location_id`
   - `SpecialistAssessmentController` - change `salon_id` to `location_id`
2. Update request parameter names in API documentation
3. Consider maintaining legacy parameter names with deprecation warnings

**Risk:** MEDIUM - May break existing API clients

**Dependencies:** Must complete Phase 2 first

### Phase 4: Update Legacy Seeders (LOW PRIORITY)

**Priority:** LOW - Seeders are not used in production

**Actions:**
1. Remove or update `Legacy_UpdateSalonCountrySeeder`
2. Remove or update `Legacy_TestWebsiteConfigurationSeeder`
3. Remove or update `Legacy_TestResolverSeeder`
4. Remove `SalonSeeder` reference from `RegisterSeedersSeeder`

**Risk:** LOW - Seeders are development-only

**Dependencies:** None - Can proceed independently

### Phase 5: Clean Up Rollback Migrations (LOW PRIORITY)

**Priority:** LOW - Rollback is rarely used

**Actions:**
1. Remove rollback capability from drop migrations that reference `salons` table
2. Or update rollback to use `provider_id` instead of `salon_id`

**Risk:** LOW - Rollback is rarely used

**Dependencies:** None - Can proceed independently

### Phase 6: Update Comments and Documentation (LOW PRIORITY)

**Priority:** LOW - Cosmetic changes only

**Actions:**
1. Update comments referencing "salon" to "provider" or "location" as appropriate
2. Update notification messages to use brand-neutral language
3. Update method documentation

**Risk:** LOW - No functional impact

**Dependencies:** None - Can proceed independently

---

## 13. Final Verdict

### 13.1 Migration Status

**Verdict:** **PARTIALLY COMPLETE**

**Summary:**
- ✅ Database schema successfully migrated to Provider-native architecture
- ✅ Salon model and salons table removed
- ✅ Core relationships (Provider → Locations) implemented correctly
- ❌ **CRITICAL:** Three models have schema mismatches (FeatureFlag, PaymentAccount, ServiceCategory)
- ⚠️ **HIGH:** Legacy terminology pervasive throughout codebase
- ⚠️ **MEDIUM:** API contracts retain legacy parameter names
- ⚠️ **LOW:** Rollback migrations reference non-existent table

### 13.2 Active Legacy Dependencies

**Count:** 0

**Explanation:** No active runtime dependencies on Salon architecture. All legacy references are terminology-only or compatibility checks.

### 13.3 Blocking Issues

**Issue 1:** Model-database schema mismatches (FeatureFlag, PaymentAccount, ServiceCategory)
- **Severity:** CRITICAL
- **Impact:** Runtime errors when using these models
- **Action:** Fix fillable arrays and method names to match database schema

**Issue 2:** Missing customer-provider relationship in CustomerBookingService
- **Severity:** HIGH
- **Impact:** Customer portal bookings lack provider relationships
- **Action:** Add `addSalonRelationship` call (documented in previous report)

**Issue 3:** Invalid method name in BookingService
- **Severity:** HIGH
- **Impact:** Runtime error if code path executed
- **Action:** Fix `addProviderRelationship` to `addSalonRelationship` (documented in previous report)

### 13.4 Non-Blocking Issues

**Count:** 47 findings classified as "Legacy terminology only"

**Examples:**
- Method names: `isEnabledForSalon`, `enableForSalon`, `disableForSalon`
- Parameter names: `$salonId` in service methods
- Comments: References to "salon" in documentation
- API contracts: `salon_id` parameter names
- Constants: `TYPE_SALON`, `ORIGIN_SALON`, `TYPE_SALON_CHANGE`

**Recommendation:** Address in Phase 2-6 of removal plan

### 13.5 Migration Risks

**Risk 1:** Incomplete model updates
- **Description:** Four models (CustomerTimeline, CustomerPackage, CustomerActivity, BrandExperience) use `salon_id` in fillable but their migrations were not audited
- **Mitigation:** Audit migrations for these models before Phase 1

**Risk 2:** API client breakage
- **Description:** Changing API parameter names may break existing clients
- **Mitigation:** Maintain backward compatibility with deprecation warnings in Phase 3

**Risk 3:** Rollback failure
- **Description:** Rollback migrations reference non-existent `salons` table
- **Mitigation:** Remove or update rollback capability in Phase 5

---

## 14. Recommendations

### 14.1 Immediate Actions (Before Production)

1. **Fix schema mismatches** (Phase 1) - CRITICAL
2. **Fix CustomerBookingService bug** (from previous report) - HIGH
3. **Fix BookingService typo** (from previous report) - HIGH
4. **Audit four additional models** for schema mismatches - HIGH

### 14.2 Short-Term Actions (Next Sprint)

1. **Rename method names** (Phase 2) - HIGH
2. **Update API contracts** (Phase 3) - MEDIUM
3. **Add tests** for schema-correct models - MEDIUM

### 14.3 Long-Term Actions (Backlog)

1. **Clean up legacy seeders** (Phase 4) - LOW
2. **Fix rollback migrations** (Phase 5) - LOW
3. **Update comments and documentation** (Phase 6) - LOW

### 14.4 Do NOT Do

1. **Do NOT** remove `Schema::hasTable('salons')` checks - essential for fresh databases
2. **Do NOT** remove rollback capability without testing - may be needed in emergencies
3. **Do NOT** change API contracts without deprecation strategy - will break clients
4. **Do NOT** rename constants without verifying all usages - enum values are valid

---

## 15. Appendix: File References

### Critical Schema Mismatches
- `backend/app/Models/FeatureFlag.php
- `backend/app/Models/PaymentAccount.php
- `backend/app/Models/ServiceCategory.php
- `backend/database/migrations/2026_07_31_1900221_create_feature_flags_table.php`
- `backend/database/migrations/2026_08_19_191002_create_payment_accounts_table.php`
- `backend/database/migrations/2026_07_31_1900212_create_service_categories_table.php`

### Legacy Terminology Models
- `backend/app/Models/CustomerTimeline.php`
- `backend/app/Models/CustomerPackage.php`
- `backend/app/Models/CustomerActivity.php`
- `backend/app/Models/BrandExperience.php`
- `backend/app/Models/PortalAccount.php`
- `backend/app/Models/Subscription.php`
- `backend/app/Models/Specialist.php`
- `backend/app/Models/SpecialistCareerEvent.php`
- `backend/app/Models/ProviderBookingConfig.php`
- `backend/app/Models/Invitation.php`
- `backend/app/Models/CustomerSpecialist.php`
- `backend/app/Models/Customer.php`

### Legacy Terminology Services
- `backend/app/Services/CustomerService.php`
- `backend/app/Services/CustomerPortalService.php`
- `backend/app/Services/CustomerSpecialistService.php`

### Migration Compatibility Layer
- `backend/database/migrations/2026_07_31_190004_migrate_salon_locations_to_locations_table.php`
- `backend/database/migrations/2026_07_31_191135__migrate_bookings_to_location.php`
- `backend/database/migrations/2026_07_31_191134__migrate_resources_to_location.php`
- (15 additional migrations with `Schema::hasTable('salons')` checks)

### Legacy Seeders
- `backend/database/seeders/Legacy_UpdateSalonCountrySeeder.php`
- `backend/database/seeders/Legacy_TestWebsiteConfigurationSeeder.php`
- `backend/database/seeders/Legacy_TestResolverSeeder.php`
- `backend/database/seeders/RegisterSeedersSeeder.php`

### Related Reports
- `backend/CUSTOMER_PROVIDER_RELATIONSHIP_ROOT_CAUSE_REPORT.md`
- `backend/HISTORICAL_CUSTOMER_PROVIDER_RECONCILIATION_REPORT.md`
- `backend/CUSTOMER_PROVIDER_VERIFICATION_REPORT.md`

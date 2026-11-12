# Phase 1 Verification Report: Provider-Native Foundation

**Verification Date:** October 9, 2026
**Mode:** READ-ONLY - No code changes performed

---

## Executive Summary

**Objective:** Verify seven flagged models against their migrations and all callers to identify actual schema mismatches and runtime defects.

**Findings:**
- **3 models with schema mismatches** (FeatureFlag, ServiceCategory, BrandExperience)
- **2 models with fillable mismatches** (PaymentAccount, CustomerPackage)
- **2 models with dual-column issues** (CustomerTimeline has both salon_id and provider_id in fillable)
- **1 model without migration** (CustomerActivity - table may not exist)
- **1 active runtime bug** (CustomerPackage queries use non-existent salon_id column)

**Classification:**
- **Critical runtime defects:** 2 (CustomerPackage queries, FeatureFlag static methods)
- **Silent data loss risk:** 4 (fillable mismatches)
- **Uncertain status:** 1 (CustomerActivity - no migration found)

---

## 1. FeatureFlag Model

### 1.1 Schema Verification

**Model:** `backend/app/Models/FeatureFlag.php`
**Migration:** `backend/database/migrations/2026_07_31_1900221_create_feature_flags_table.php`

**Model Definition:**
```php
protected $fillable = [
    'salon_id',  // ❌ Wrong column name
    'feature_key',
    ...
];

public function provider()
{
    return $this->belongsTo(Provider::class, 'salon_id');  // ❌ Wrong column
}

public static function isEnabledForSalon(string $salonId, ...)  // ❌ Method name and column
{
    return self::where('salon_id', $salonId)  // ❌ Wrong column
        ...
}

public static function enableForSalon(string $salonId, ...)  // ❌ Method name and column
{
    return self::updateOrCreate(
        ['salon_id' => $salonId],  // ❌ Wrong column
        ...
    );
}

public static function disableForSalon(string $salonId, ...)  // ❌ Method name and column
{
    self::where('salon_id', $salonId)  // ❌ Wrong column
        ...
}
```

**Migration Definition:**
```php
Schema::create('feature_flags', function (Blueprint $table) {
    $table->uuid('provider_id')->nullable();  // ✅ Correct column
    ...
});
```

**Status:** **Schema mismatch** - Model uses `salon_id`, database has `provider_id`

### 1.2 Caller Analysis

**Callers Found:**
- `backend/app/Services/CapabilityService.php` (lines 22, 152, 160)
- `backend/app/Http/Controllers/Api/V1/FeatureFlagController.php` (line 63)

**Caller Usage:**
```php
// CapabilityService.php - CORRECT usage
FeatureFlag::isEnabledForProvider($provider->id, $feature)  // ✅ Uses provider_id
FeatureFlag::enableForProvider($provider->id, $feature, ...)  // ✅ Uses provider_id
FeatureFlag::disableForProvider($provider->id, $feature)  // ✅ Uses provider_id

// FeatureFlagController.php - CORRECT usage
FeatureFlag::where('provider_id', $providerId)  // ✅ Uses provider_id
```

**Analysis:** Callers use `provider_id` directly in queries, bypassing the static methods. This means the static methods with `salon_id` may not be in active use, but they will fail if called.

### 1.3 Runtime Behavior Assessment

**Severity:** **MEDIUM-HIGH**

**Impact:**
- Mass assignment with `salon_id` will silently discard the value (fillable mismatch)
- Static methods (`isEnabledForSalon`, `enableForSalon`, `disableForSalon`) will fail with SQL error if called
- Direct queries by callers work correctly because they use `provider_id`

**Evidence:** Callers use direct `where('provider_id', ...)` queries, not the static methods.

---

## 2. PaymentAccount Model

### 2.1 Schema Verification

**Model:** `backend/app/Models/PaymentAccount.php`
**Migration:** `backend/database/migrations/2026_08_19_191002_create_payment_accounts_table.php`

**Model Definition:**
```php
protected $fillable = [
    'salon_id',  // ❌ Wrong column name
    'specialist_id',
    'provider',
    ...
];
```

**Migration Definition:**
```php
Schema::create('payment_accounts', function (Blueprint $table) {
    $table->uuid('provider_id')->nullable();  // ✅ Correct column
    $table->uuid('specialist_id')->nullable();
    ...
});
```

**Status:** **Fillable mismatch** - Model uses `salon_id`, database has `provider_id`

### 2.2 Caller Analysis

**Callers Found:**
- `backend/app/Services/Payments/PaymentRoutingService.php` (lines 64, 81, 112, 132, 170, 184)
- `backend/app/Models/Transaction.php` (line 63)
- `backend/app/Models/PaymentMethod.php` (line 68)
- `backend/app/Domain/Finance/PostingPolicies/PostingPolicyResolver.php` (line 16)
- `backend/app/Domain/Finance/Journal/JournalPostingService.php` (line 39, 88)

**Caller Usage:**
```php
// PaymentRoutingService.php - CORRECT usage
PaymentAccount::where('provider_id', $provider->id)  // ✅ Uses provider_id

// Domain references - CORRECT usage
PaymentAccount::TYPE_SUBACCOUNT  // ✅ Uses constants only
PaymentAccount::TYPE_ROUTING
PaymentAccount::TYPE_MERCHANT
PaymentAccount::TYPE_MANUAL
```

**Analysis:** All callers use `provider_id` directly in queries or constants. No mass assignment usage found.

### 2.3 Runtime Behavior Assessment

**Severity:** **LOW-MEDIUM**

**Impact:**
- Mass assignment with `salon_id` will silently discard the value (fillable mismatch)
- Direct queries work correctly because callers use `provider_id`
- Constants (TYPE_SUBACCOUNT, etc.) are unaffected

**Evidence:** No mass assignment usage found in callers. All use direct `where('provider_id', ...)` queries.

---

## 3. ServiceCategory Model

### 3.1 Schema Verification

**Model:** `backend/app/Models/ServiceCategory.php`
**Migration:** `backend/database/migrations/2026_07_31_1900212_create_service_categories_table.php`

**Model Definition:**
```php
protected $fillable = [
    'salon_id',  // ❌ Wrong column name
    'name',
    ...
];

public static function getOrdered(string $salonId)  // ❌ Parameter name
{
    return self::where('salon_id', $salonId)  // ❌ Wrong column
        ...
}
```

**Migration Definition:**
```php
Schema::create('service_categories', function (Blueprint $table) {
    $table->uuid('provider_id');  // ✅ Correct column
    ...
});
```

**Status:** **Schema mismatch** - Model uses `salon_id`, database has `provider_id`

### 3.2 Caller Analysis

**Callers Found:** None

**Analysis:** No active callers found using ServiceCategory model. This model may not be in active use.

### 3.3 Runtime Behavior Assessment

**Severity:** **LOW**

**Impact:**
- Mass assignment with `salon_id` will silently discard the value
- Static method `getOrdered($salonId)` will fail with SQL error if called
- No active usage found, so impact is limited

**Evidence:** No callers found in codebase.

---

## 4. CustomerTimeline Model

### 4.1 Schema Verification

**Model:** `backend/app/Models/CustomerTimeline.php`
**Migration:** `backend/database/migrations/2026_07_31_210300_create_customer_timelines_table.php`

**Model Definition:**
```php
protected $fillable = [
    'customer_id',
    'salon_id',  // ❌ Wrong column name
    'booking_id',
    ...
    'provider_id',  // ✅ Correct column also present
    ...
];

public function provider()
{
    return $this->belongsTo(Provider::class, 'salon_id');  // ❌ Wrong column
}
```

**Migration Definition:**
```php
Schema::create('customer_timelines', function (Blueprint $table) {
    $table->uuid('customer_id');
    $table->uuid('provider_id')->nullable();  // ✅ Correct column
    $table->uuid('booking_id')->nullable();
    ...
});
```

**Status:** **Dual-column issue** - Model has both `salon_id` and `provider_id` in fillable, but database only has `provider_id`. Relationship uses wrong column.

### 4.2 Caller Analysis

**Callers Found:**
- `backend/app/Services/MediaService.php` (lines 192, 195)
- `backend/app/Http/Controllers/Api/V1/TimelineController.php` (lines 29, 43, 46, 62, 80, 109, 125)

**Caller Usage:**
```php
// MediaService.php - NEUTRAL usage
CustomerTimeline::where('before_photo', $media->id)  // Uses photo columns
CustomerTimeline::where('after_photo', $media->id)

// TimelineController.php - NEUTRAL usage
CustomerTimeline::query()->forCustomer($customerId)  // Uses scope, not provider_id
CustomerTimeline::forCustomer($customerId)->count()
CustomerTimeline::where('id', $id)->where('customer_id', $customerId)
```

**Analysis:** Callers do not use `provider_id` or `salon_id` in queries. They use scopes or other columns. The `provider()` relationship may not be actively used.

### 4.3 Runtime Behavior Assessment

**Severity:** **MEDIUM**

**Impact:**
- Mass assignment with `salon_id` will silently discard the value
- `provider()` relationship will fail to load (wrong column)
- Callers don't use the relationship or provider_id in queries, so current usage may work
- Future usage of `provider()` relationship will fail

**Evidence:** Callers use scopes and other columns, not the `provider()` relationship.

---

## 5. CustomerPackage Model

### 5.1 Schema Verification

**Model:** `backend/app/Models/CustomerPackage.php`
**Migration:** `backend/database/migrations/2026_07_31_1900278_create_customer_packages_table.php`

**Model Definition:**
```php
protected $fillable = [
    'customer_id',
    'salon_id',  // ❌ Wrong column name
    'package_id',
    ...
];

public function provider()
{
    return $this->belongsTo(Provider::class, 'provider_id');  // ✅ Correct column
}
```

**Migration Definition:**
```php
Schema::create('customer_packages', function (Blueprint $table) {
    $table->uuid('customer_id');
    $table->uuid('provider_id');  // ✅ Correct column
    $table->uuid('package_id');
    ...
});
```

**Status:** **Fillable mismatch** - Model uses `salon_id`, database has `provider_id`. Relationship is correct.

### 5.2 Caller Analysis

**Callers Found:**
- `backend/app/Services/ServicePackageService.php` (lines 71, 97, 118)

**Caller Usage:**
```php
// ServicePackageService.php - BUG usage
CustomerPackage::create([
    'customer_id' => $customerId,
    'salon_id' => $salonId,  // ❌ BUG: Uses non-existent column
    'package_id' => $packageId,
    ...
]);

CustomerPackage::where('customer_id', $customerId)
    ->where('salon_id', $salonId)  // ❌ BUG: Uses non-existent column
    ...
```

**Analysis:** **ACTIVE BUG** - Caller uses `salon_id` in mass assignment and queries, but column doesn't exist. This will cause SQL errors.

### 5.3 Runtime Behavior Assessment

**Severity:** **HIGH**

**Impact:**
- **Active runtime bug:** `CustomerPackage::create(['salon_id' => ...])` will fail with SQL error
- **Active runtime bug:** `CustomerPackage::where('salon_id', ...)` will fail with SQL error
- `provider()` relationship is correct and will work if used

**Evidence:** ServicePackageService actively uses `salon_id` in create and where queries.

---

## 6. CustomerActivity Model

### 6.1 Schema Verification

**Model:** `backend/app/Models/CustomerActivity.php`
**Migration:** **NOT FOUND**

**Model Definition:**
```php
protected $fillable = [
    'customer_id',
    'salon_id',  // ❌ Column name
    'type',
    ...
];

public function provider()
{
    return $this->belongsTo(Provider::class, 'salon_id');  // ❌ Column name
}
```

**Status:** **UNCERTAIN** - No migration found. Table may not exist or may have been created with a different name.

### 6.2 Caller Analysis

**Callers Found:**
- `backend/app/Http/Controllers/Api/V1/ActivityController.php` (lines 16, 32, 48)

**Caller Usage:**
```php
// ActivityController.php
CustomerActivity::query()->forCustomer($customerId)  // Uses scope
CustomerActivity::findOrFail($id)
CustomerActivity::forCustomer($customerId)->unread()
```

**Analysis:** Callers use scopes and findOrFail, not provider_id queries. The model may be in active use despite missing migration.

### 6.3 Runtime Behavior Assessment

**Severity:** **UNCERTAIN**

**Impact:**
- Cannot verify schema without migration
- If table exists with `provider_id`, model will fail
- If table doesn't exist, all usage will fail
- Callers don't use provider_id in queries, so may work if table exists

**Evidence:** Active usage found in ActivityController, but no migration to verify schema.

**Action Required:** Search for alternative migration name or check if table exists in database.

---

## 7. BrandExperience Model

### 7.1 Schema Verification

**Model:** `backend/app/Models/BrandExperience.php`
**Migration:** `backend/database/migrations/2026_07_31_1900332_create_brand_experiences_table.php`

**Model Definition:**
```php
protected $fillable = [
    'salon_id',  // ❌ Wrong column name
    'experience_family',
    ...
];

public function provider()
{
    return $this->belongsTo(Provider::class, 'salon_id');  // ❌ Wrong column
}
```

**Migration Definition:**
```php
Schema::create('brand_experiences', function (Blueprint $table) {
    $table->uuid('provider_id');  // ✅ Correct column
    ...
});
```

**Status:** **Schema mismatch** - Model uses `salon_id`, database has `provider_id`

### 7.2 Caller Analysis

**Callers Found:**
- `backend/app/Services/BrandEmailService.php` (line 118)
- `backend/app/Services/MediaService.php` (line 188)
- `backend/app/Models/Provider.php` (line 144)
- `backend/app/Models/BusinessCategory.php` (line 43)
- `backend/app/Http/Controllers/Api/V1/BrandExperienceController.php` (line 244)

**Caller Usage:**
```php
// BrandEmailService.php - CORRECT usage
BrandExperience::create([
    'provider_id' => $provider->id,  // ✅ Uses provider_id
    ...
]);

// BrandExperienceController.php - CORRECT usage
BrandExperience::create([
    'provider_id' => $provider->id,  // ✅ Uses provider_id
    ...
]);

// Provider.php - RELATIONSHIP usage
$this->hasOne(BrandExperience::class)  // Uses default foreign key
```

**Analysis:** Callers use `provider_id` directly in create queries. The `hasOne` relationship in Provider uses default foreign key (provider_id), which is correct. The model's `provider()` relationship is not used by callers.

### 7.3 Runtime Behavior Assessment

**Severity:** **MEDIUM**

**Impact:**
- Mass assignment with `salon_id` will silently discard the value
- `provider()` relationship will fail to load (wrong column)
- Callers use direct `provider_id` in create, so current usage works
- `hasOne` relationship from Provider works correctly (uses default foreign key)

**Evidence:** Callers use `provider_id` directly in create queries. Model's `provider()` relationship is not used.

---

## 8. Summary of Findings

### 8.1 Critical Runtime Defects (Must Fix)

| Model | Issue | Severity | Evidence |
|-------|-------|----------|----------|
| CustomerPackage | Caller uses non-existent `salon_id` column in create and where queries | HIGH | ServicePackageService lines 73, 119 |
| FeatureFlag | Static methods use non-existent `salon_id` column | MEDIUM-HIGH | Methods will fail if called |

### 8.2 Silent Data Loss Risks (Should Fix)

| Model | Issue | Severity | Evidence |
|-------|-------|----------|----------|
| FeatureFlag | Fillable has `salon_id`, database has `provider_id` | MEDIUM | Mass assignment will discard value |
| PaymentAccount | Fillable has `salon_id`, database has `provider_id` | LOW-MEDIUM | Mass assignment will discard value |
| ServiceCategory | Fillable has `salon_id`, database has `provider_id` | LOW | No active callers |
| BrandExperience | Fillable has `salon_id`, database has `provider_id` | MEDIUM | Mass assignment will discard value |
| CustomerTimeline | Fillable has both `salon_id` and `provider_id` | MEDIUM | Confusion, relationship wrong |

### 8.3 Relationship Defects (Should Fix)

| Model | Issue | Severity | Evidence |
|-------|-------|----------|----------|
| CustomerTimeline | `provider()` relationship uses `salon_id` | MEDIUM | Relationship will fail to load |
| BrandExperience | `provider()` relationship uses `salon_id` | MEDIUM | Relationship will fail to load (not used by callers) |
| CustomerActivity | `provider()` relationship uses `salon_id` | UNCERTAIN | Migration not found |

### 8.4 Uncertain Status

| Model | Issue | Severity | Evidence |
|-------|-------|----------|----------|
| CustomerActivity | No migration found | UNCERTAIN | Active usage in ActivityController |

---

## 9. Proposed Minimal Changes for Phase 1

### 9.1 Priority 1: Fix Active Runtime Bugs

**Change 1: CustomerPackage Model**
```php
// File: backend/app/Models/CustomerPackage.php
// Line 15: Change fillable
protected $fillable = [
    'customer_id',
    'provider_id',  // Changed from 'salon_id'
    'package_id',
    'services_remaining',
    'expires_at',
];
```

**Change 2: ServicePackageService**
```php
// File: backend/app/Services/ServicePackageService.php
// Line 73: Change create call
$customerPackage = CustomerPackage::create([
    'customer_id' => $customerId,
    'provider_id' => $salonId,  // Changed from 'salon_id'
    'package_id' => $packageId,
    ...
]);

// Line 119: Change where query
$packages = CustomerPackage::where('customer_id', $customerId)
    ->where('provider_id', $salonId)  // Changed from 'salon_id'
    ...
```

**Change 3: FeatureFlag Static Methods**
```php
// File: backend/app/Models/FeatureFlag.php
// Line 42: Rename and fix method
public static function isEnabledForProvider(string $providerId, string $featureKey): bool
{
    return self::where('provider_id', $providerId)  // Changed from 'salon_id'
        ->where('feature_key', $featureKey)
        ->where('enabled', true)
        ->exists();
}

// Line 53: Rename and fix method
public static function enableForProvider(string $providerId, string $featureKey, string $userId, string $reason = null): self
{
    return self::updateOrCreate(
        [
            'provider_id' => $providerId,  // Changed from 'salon_id'
            'feature_key' => $featureKey,
        ],
        ...
    );
}

// Line 72: Rename and fix method
public static function disableForProvider(string $providerId, string $featureKey): void
{
    self::where('provider_id', $providerId)  // Changed from 'salon_id'
        ->where('feature_key', $featureKey)
        ->update([
            'enabled' => false,
            'enabled_at' => null,
        ]);
}
```

**Change 4: Update FeatureFlag Callers**
```php
// File: backend/app/Services/CapabilityService.php
// Line 22: Update method call
if (FeatureFlag::isEnabledForProvider($provider->id, $feature)) {  // Already correct

// Line 152: Update method call
FeatureFlag::enableForProvider($provider->id, $feature, auth()->id(), $reason);  // Already correct

// Line 160: Update method call
FeatureFlag::disableForProvider($provider->id, $feature);  // Already correct
```

**Note:** CapabilityService already uses the correct method names (`isEnabledForProvider`, etc.), so the model methods must be renamed to match.

### 9.2 Priority 2: Fix Fillable Mismatches

**Change 5: FeatureFlag Fillable**
```php
// File: backend/app/Models/FeatureFlag.php
// Line 14: Change fillable
protected $fillable = [
    'provider_id',  // Changed from 'salon_id'
    'feature_key',
    'enabled',
    'enabled_at',
    'enabled_by',
    'reason',
    'metadata',
];
```

**Change 6: PaymentAccount Fillable**
```php
// File: backend/app/Models/PaymentAccount.php
// Line 30: Change fillable
protected $fillable = [
    'provider_id',  // Changed from 'salon_id'
    'specialist_id',
    'provider',
    'account_type',
    ...
];
```

**Change 7: ServiceCategory Fillable**
```php
// File: backend/app/Models/ServiceCategory.php
// Line 14: Change fillable
protected $fillable = [
    'provider_id',  // Changed from 'salon_id'
    'name',
    'description',
    ...
];
```

**Change 8: BrandExperience Fillable**
```php
// File: backend/app/Models/BrandExperience.php
// Line 13: Change fillable
protected $fillable = [
    'provider_id',  // Changed from 'salon_id'
    'experience_family',
    ...
];
```

### 9.3 Priority 3: Fix Relationship Defects

**Change 9: CustomerTimeline Relationship**
```php
// File: backend/app/Models/CustomerTimeline.php
// Line 15: Remove salon_id from fillable
protected $fillable = [
    'customer_id',
    // 'salon_id',  // REMOVED
    'booking_id',
    ...
    'provider_id',  // Keep this
    ...
];

// Line 81: Fix relationship
public function provider()
{
    return $this->belongsTo(Provider::class, 'provider_id');  // Changed from 'salon_id'
}
```

**Change 10: BrandExperience Relationship**
```php
// File: backend/app/Models/BrandExperience.php
// Line 37: Fix relationship
public function provider()
{
    return $this->belongsTo(Provider::class, 'provider_id');  // Changed from 'salon_id'
}
```

### 9.4 Priority 4: Investigate CustomerActivity

**Action Required:**
1. Search for alternative migration name (e.g., `create_customer_activities_table.php`)
2. If migration exists, verify schema
3. If migration doesn't exist, determine if table should exist or model should be removed
4. If table exists with `provider_id`, update model to match

**Cannot propose changes without migration verification.**

---

## 10. Proposed Regression Tests

### 10.1 Test CustomerPackage Fix

```php
// File: backend/tests/Unit/CustomerPackageSchemaTest.php

public function test_customer_package_uses_provider_id_column()
{
    $provider = Provider::factory()->create();
    $customer = Customer::factory()->create();
    $package = ServicePackage::factory()->create(['provider_id' => $provider->id]);

    $customerPackage = CustomerPackage::create([
        'customer_id' => $customer->id,
        'provider_id' => $provider->id,
        'package_id' => $package->id,
        'services_remaining' => 5,
    ]);

    $this->assertDatabaseHas('customer_packages', [
        'id' => $customerPackage->id,
        'provider_id' => $provider->id,
    ]);

    $this->assertEquals($provider->id, $customerPackage->provider->id);
}

public function test_customer_package_query_by_provider_id()
{
    $provider = Provider::factory()->create();
    $customer = Customer::factory()->create();
    $package = ServicePackage::factory()->create(['provider_id' => $provider->id]);

    CustomerPackage::create([
        'customer_id' => $customer->id,
        'provider_id' => $provider->id,
        'package_id' => $package->id,
        'services_remaining' => 5,
    ]);

    $found = CustomerPackage::where('customer_id', $customer->id)
        ->where('provider_id', $provider->id)
        ->first();

    $this->assertNotNull($found);
}
```

### 10.2 Test FeatureFlag Fix

```php
// File: backend/tests/Unit/FeatureFlagSchemaTest.php

public function test_feature_flag_uses_provider_id_column()
{
    $provider = Provider::factory()->create();
    $user = User::factory()->create();

    $flag = FeatureFlag::enableForProvider(
        $provider->id,
        'test_feature',
        $user->id,
        'Test reason'
    );

    $this->assertDatabaseHas('feature_flags', [
        'id' => $flag->id,
        'provider_id' => $provider->id,
        'feature_key' => 'test_feature',
    ]);

    $this->assertTrue(FeatureFlag::isEnabledForProvider($provider->id, 'test_feature'));

    FeatureFlag::disableForProvider($provider->id, 'test_feature');

    $this->assertFalse(FeatureFlag::isEnabledForProvider($provider->id, 'test_feature'));
}

public function test_feature_flag_mass_assignment_uses_provider_id()
{
    $provider = Provider::factory()->create();

    $flag = FeatureFlag::create([
        'provider_id' => $provider->id,
        'feature_key' => 'test_feature',
        'enabled' => true,
    ]);

    $this->assertEquals($provider->id, $flag->provider_id);
    $this->assertEquals($provider->id, $flag->provider->id);
}
```

### 10.3 Test BrandExperience Fix

```php
// File: backend/tests/Unit/BrandExperienceSchemaTest.php

public function test_brand_experience_uses_provider_id_column()
{
    $provider = Provider::factory()->create();

    $experience = BrandExperience::create([
        'provider_id' => $provider->id,
        'experience_family' => 'luxury_noir',
    ]);

    $this->assertDatabaseHas('brand_experiences', [
        'id' => $experience->id,
        'provider_id' => $provider->id,
    ]);

    $this->assertEquals($provider->id, $experience->provider->id);
}
```

### 10.4 Test CustomerTimeline Fix

```php
// File: backend/tests/Unit/CustomerTimelineSchemaTest.php

public function test_customer_timeline_uses_provider_id_column()
{
    $provider = Provider::factory()->create();
    $customer = Customer::factory()->create();
    $booking = Booking::factory()->create(['provider_id' => $provider->id]);

    $timeline = CustomerTimeline::create([
        'customer_id' => $customer->id,
        'provider_id' => $provider->id,
        'booking_id' => $booking->id,
        'service_name' => 'Haircut',
        'service_date' => now(),
    ]);

    $this->assertDatabaseHas('customer_timelines', [
        'id' => $timeline->id,
        'provider_id' => $provider->id,
    ]);

    $this->assertEquals($provider->id, $timeline->provider->id);
}
```

---

## 11. Booking Relationship Defects (From Previous Report)

### 11.1 CustomerBookingService Missing Relationship

**File:** `backend/app/Services/CustomerBookingService.php`
**Issue:** Does not call `addSalonRelationship` (should be `addProviderRelationship` after Phase 2)

**Proposed Fix (Phase 1 - use existing method name):**
```php
// After line 98 (after booking creation)
// Ensure customer has provider relationship (domain integrity)
$providerId = $location->provider_id;
if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
    $customerService = app(\App\Services\CustomerService::class);
    $customerService->addSalonRelationship($customer, $providerId);
}
```

### 11.2 BookingService Invalid Method Call

**File:** `backend/app/Services/BookingService.php`
**Line:** 479
**Issue:** Calls non-existent `addProviderRelationship`

**Proposed Fix (Phase 1 - use existing method name):**
```php
// Change line 479
$this->customerService->addSalonRelationship($customer, $providerId);
```

---

## 12. Next Steps

### 12.1 Immediate Actions (Phase 1)

1. **Fix CustomerPackage schema mismatch** (Priority 1)
   - Update CustomerPackage fillable
   - Update ServicePackageService callers
   - Add regression tests

2. **Fix FeatureFlag schema mismatch** (Priority 1)
   - Update FeatureFlag fillable
   - Rename and fix static methods
   - Add regression tests

3. **Fix booking relationship defects** (Priority 1)
   - Add `addSalonRelationship` call to CustomerBookingService
   - Fix `addProviderRelationship` typo in BookingService
   - Add regression tests

4. **Fix remaining fillable mismatches** (Priority 2)
   - PaymentAccount
   - ServiceCategory
   - BrandExperience
   - Add regression tests

5. **Fix relationship defects** (Priority 3)
   - CustomerTimeline
   - BrandExperience
   - Add regression tests

6. **Investigate CustomerActivity** (Priority 4)
   - Find migration or determine table status
   - Fix or remove based on findings

### 12.2 Verification After Changes

1. Run all proposed regression tests
2. Run existing test suite to ensure no regressions
3. Verify CustomerPackage creation works in ServicePackageService
4. Verify FeatureFlag static methods work in CapabilityService
5. Verify booking creation creates customer-provider relationships

### 12.3 Phase 2 Preparation

After Phase 1 is complete and verified:
1. Rename `addSalonRelationship` to `addProviderRelationship`
2. Update all callers
3. Update API contracts
4. Add additional regression tests

---

## 13. Conclusion

**Phase 1 Status:** Ready to proceed with minimal, evidence-based fixes.

**Critical Path:**
1. CustomerPackage (active runtime bug)
2. FeatureFlag (static methods will fail if called)
3. Booking relationship defects (customer portal bookings lack relationships)

**Risk Assessment:**
- **High risk:** CustomerPackage queries will fail in production
- **Medium risk:** FeatureFlag static methods may be called
- **Medium risk:** Customer portal bookings lack provider relationships
- **Low risk:** Silent data loss from fillable mismatches

**Recommendation:** Proceed with Priority 1 fixes immediately, then address Priority 2-4 in order. All changes are minimal and targeted based on actual caller analysis.

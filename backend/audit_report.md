# CUSTOMER-PROVIDER DATA INTEGRITY AUDIT REPORT

**Date:** 2026-10-09
**Mode:** READ-ONLY INVESTIGATION
**Scope:** Cross-system booking and customer consistency

---

## EXECUTIVE SUMMARY

**Critical Finding:** All 29 customer records in the database have ZERO customer-provider relationships. The `customer_providers` pivot table is completely empty despite 11 bookings existing for Emo Spa.

**Root Cause:** The `customer_providers` pivot table was created on 2026-09-22, but the booking creation code that should populate it was either:
1. Not called during the booking creation period (2026-10-05 to 2026-10-08)
2. Silently failing without error logging
3. Bypassed by an alternative code path

**Impact:**
- Owner's Clients page returns no customers (relies on `customer_providers` relationship)
- Dashboard KPIs may be affected if they filter by customer-provider relationships
- Specialist Portal Clients may also be affected
- Data integrity violation: customers exist but have no tenant association

---

## 1. SCHEMA AUDIT

### Table Creation Timeline
- `customers` table: 2026-07-14
- `bookings` table: 2026-07-14
- `customer_providers` pivot table: 2026-09-22

**Gap:** The canonical customer-provider relationship table was added **2+ months after** customers and bookings were initially created.

### Schema Structure
**customer_providers pivot table:**
```sql
- id (UUID, primary)
- customer_id (UUID, foreign key → customers)
- provider_id (UUID, foreign key → providers)
- visits (integer, default 0)
- joined_at (timestamp, nullable)
- created_at, updated_at
- Unique constraint: (customer_id, provider_id)
- Index: provider_id
```

**customers table:**
```sql
- id (UUID, primary)
- name (string)
- phone (string, indexed)
- email (string, nullable)
- notes (text, nullable)
- visits (integer, default 0) - DEPRECATED, should use pivot
- is_guest (boolean, added 2026-10-05)
- guest_token (string, added 2026-10-05)
- guest_provider_id (UUID, added 2026-10-5)
```

**bookings table:**
```sql
- id (UUID, primary)
- provider_id (UUID, foreign key)
- location_id (UUID, foreign key, nullable)
- customer_id (UUID, foreign key, nullable)
- staff_id (UUID, nullable)
- specialist_id (UUID, nullable)
- service_id (UUID)
- date (date)
- time (string)
- status (string)
```

---

## 2. RECORD COUNTS

### Current State
- **Total customers:** 29
- **Total bookings:** 11
- **Total providers:** 4
- **Total customer-provider relationships:** 0 (CRITICAL)

### Emo Spa Specific
- **Emo Spa bookings:** 11 (100% of all bookings)
- **Unique customers in Emo Spa bookings:** 11
- **Emo Spa customers with provider relationship:** 0 (CRITICAL)

### Customer Distribution
- **Customers with bookings:** 11
- **Customers with bookings but NO provider relationship:** 11 (100% - CRITICAL)
- **Guest customers:** 16
- **Guest customers with provider relationship:** 0

### Provider Distribution
All 11 bookings belong to a single provider:
- **Provider:** Emo Spa (01a0e836-39fa-700e-991d-797669bb5a32)
- **Bookings:** 11

### Location Distribution
- **Unknown location (null):** 7 bookings
- **Emo Spa location (ba489e65-5e63-44b8-99d3-cfaa1bc11ec4):** 4 bookings

---

## 3. TIMELINE ANALYSIS

### Booking Creation Dates (2026-10-05 to 2026-10-08)
```
2026-10-05 05:13:23 - Booking 01a10a7b-1fcc-709f-928a-082a4e10333b
2026-10-05 05:25:32 - Booking 01a10a86-3e24-7031-bb56-06f64679b6e2
2026-10-05 07:15:55 - Booking 01a10aeb-4f1d-7183-9ce7-122e43a38b92
2026-10-06 16:52:50 - Booking 01a11221-d6fb-7351-b1c3-23151b977816
2026-10-07 00:13:56 - Booking 01a113b5-ae38-738b-9fbb-283893f873cd
2026-10-08 19:24:38 - Booking 01a11cf9-8a58-718a-b6ad-f7ca91fcdadb
2026-10-08 19:26:39 - Booking 01a11cfb-6538-73ab-9b08-b26a95d35bab
2026-10-08 19:29:13 - Booking 01a11cfd-bba9-727a-9323-381042a8935d
2026-10-08 19:31:17 - Booking 01a11cff-a32d-708a-8789-ce2e5aa79c41
2026-10-08 20:10:39 - Booking 01a11d23-aaf2-7220-b91d-b3b317513438
2026-10-08 20:42:49 - Booking 01a11d41-1fd6-7290-adf7-fee77a7fd0cb
```

### Customer Creation Dates (2026-10-01 to 2026-10-08)
```
2026-10-01 08:20:57 - Customer mimo
2026-10-01 10:18:05 - Customer mariam
2026-10-01 16:02:56 - Customer mimo (duplicate)
2026-10-05 02:44:33 - Walk-in Guest
2026-10-05 03:02:36 - Walk-in Guest
2026-10-05 03:03:44 - Walk-in Guest
... (multiple test customers on 2026-10-05)
2026-10-05 05:25:32 - Customer booky
2026-10-05 05:49:58 - Customer proter
2026-10-05 07:15:55 - Customer jk
2026-10-06 16:52:49 - Customer kkkk
2026-10-07 00:13:56 - Customer Mark Neyo
2026-10-08 19:26:39 - Customer koma
2026-10-08 19:29:13 - Customer noma
2026-10-08 19:31:17 - Customer noma (duplicate)
2026-10-08 20:10:39 - Customer jm
2026-10-08 20:42:49 - Customer se
```

**Critical Observation:** All bookings were created **AFTER** the `customer_providers` migration (2026-09-22). This is not a legacy data issue - the code that should create these relationships is not working for new bookings.

---

## 4. CUSTOMER CREATION PATHS TRACED

### Path 1: Guest Bookings (BookingService::createGuestBooking)
**File:** `backend/app/Services/BookingService.php` (lines 153-261)

**Flow:**
1. Resolve or create customer (lines 170-190)
   - If `customer_id` provided: fetch existing
   - If `customer_phone` provided: find-or-create by phone
   - If no identity: create guest via `Customer::createGuest()`
2. **Ensure provider relationship** (lines 193-195)
   ```php
   if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
       $this->customerService->addSalonRelationship($customer, $providerId);
   }
   ```
3. Create booking (lines 201-213)
4. Create booking services and resource allocations (lines 216-252)
5. Track customer-specialist relationship (lines 255-257)

**Status:** Code exists and should create provider relationship.

### Path 2: Bookings with Portal Account (BookingService::createBookingWithAccount)
**File:** `backend/app/Services/BookingService.php` (lines 271-422)

**Flow:**
1. Resolve or create customer (lines 285-295)
   ```php
   $customer = Customer::firstOrCreate(
       ['phone' => $data['customer_phone']],
       ['name' => $data['customer_name'], 'email' => $data['customer_email'] ?? null]
   );
   ```
2. **Ensure provider relationship** (lines 298-300)
   ```php
   if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
       $this->customerService->addSalonRelationship($customer, $providerId);
   }
   ```
3. Create booking with payment snapshot (lines 331-354)
4. Create booking services and resource allocations (lines 356-389)

**Status:** Code exists and should create provider relationship.

### Path 3: CustomerService::addSalonRelationship
**File:** `backend/app/Services/CustomerService.php` (lines 104-121)

**Implementation:**
```php
public function addSalonRelationship(Customer $customer, string $providerId): Customer
{
    $exists = DB::table('customer_providers')
        ->where('customer_id', $customer->id)
        ->where('provider_id', $providerId)
        ->exists();

    if (!$exists) {
        DB::statement(
            'INSERT INTO customer_providers (id, customer_id, provider_id, visits, joined_at, created_at, updated_at)
             VALUES (?, ?, ?, 0, ?, ?, ?)
             ON CONFLICT (customer_id, provider_id) DO NOTHING',
            [(string) Str::orderedUuid(), $customer->id, $providerId, now(), now(), now()]
        );
    }

    return $customer->fresh();
}
```

**Status:** Implementation uses raw SQL with PostgreSQL-specific `ON CONFLICT` clause. This should work correctly on PostgreSQL.

### Path 4: CustomerResolver::resolveOrCreateForProvider
**File:** `backend/app/Services/CustomerResolver.php` (lines 91-154)

**Implementation:**
```php
if (!$hasProviderRelationship) {
    $customer->providers()->attach($providerId, [
        'id' => (string) Str::uuid(),
        'visits' => 0,
        'joined_at' => now(),
    ]);
}
```

**Status:** Uses Eloquent `attach()` method. This should work correctly.

### Path 5: PortalAuthService
**File:** `backend/app/Services/PortalAuthService.php` (lines 94-99)

**Implementation:**
```php
if (!$hasProviderRelationship) {
    $customer->providers()->attach($providerId, [
        'id' => (string) Str::uuid(),
        'visits' => 0,
        'joined_at' => now(),
    ]);
}
```

**Status:** Uses Eloquent `attach()` method. This should work correctly.

### Path 6: InvitationController
**File:** `backend/app/Http/Controllers/Api/V1/InvitationController.php` (lines 196-202)

**Implementation:**
```php
if (!$hasProviderRelationship) {
    $customer->providers()->attach($invitation->provider_id, [
        'id'        => (string) Str::uuid(),
        'visits'    => 0,
        'joined_at' => now(),
    ]);
}
```

**Status:** Uses Eloquent `attach()` method. This should work correctly.

---

## 5. CLIENTS RETRIEVAL PATHS COMPARISON

### Owner API (CustomerController::index)
**File:** `backend/app/Http/Controllers/Api/V1/CustomerController.php` (lines 12-44)

**Implementation:**
```php
public function index(Request $request): JsonResponse
{
    $providerId = $request->attributes->get('provider_id');
    $location Id = $request->attributes->get('location_id');
    $locationScope = $request->attributes->get('location_scope', 'all');

    if (!$providerId) {
        return response()->json(['message' => 'No provider context'], 403);
    }

    // Canonical: customer_providers relationship
    $query = Customer::query()
        ->whereHas('providers', function ($q) use ($providerId) {
            $q->where('providers.id', $providerId);
        });

    // Optional: filter by location if location_scope is 'specific'
    if ($locationScope === 'specific' && $locationId) {
        $query->whereHas('bookings', function ($q) use ($locationId) {
            $q->where('location_id', $locationId);
        });
    }

    $customers = $query->with('bookings')->get();
    return response()->json($customers);
}
```

**Authorization:** Requires `provider.context` and `location.context` middleware
**Relationship Source:** `customer_providers` pivot table (canonical)
**Location Filtering:** Optional, via bookings relationship

### Specialist Portal API (SpecialistPortalController::getCustomers)
**File:** `backend/app/Http/Controllers/SpecialistPortalController.php`

**Implementation:**
```php
public function getCustomers(Request $request): JsonResponse
{
    $account = auth('specialist')->user();
    $specialist = $account->specialist;
    $providerSlug = $request->query('provider_slug');

    $assignment = SpecialistAssignment::where('specialist_id', $specialist->id)
        ->where('status', 'active')
        ->whereHas('provider', fn($q) => $q->where('slug', $providerSlug))
        ->first();

    $customers = $specialist->customers()
        ->wherePivot('provider_id', $assignment->provider_id)
        ->withPivot(...)
        ->orderBy('pivot_last_interaction_at', 'desc')
        ->get();

    return response()->json(['customers' => $customers, ...]);
}
```

**Authorization:** Specialist auth guard + specialist assignment validation
**Relationship Source:** `customer_specialist` pivot table (specialist-specific)
**Location Filtering:** Via `location_slug` query parameter

**Intentional Differences:**
- Owner API uses `customer_providers` (canonical customer-provider relationship)
- Specialist API uses `customer_specialist` (specialist-specific relationship)
- This is correct: owners see all provider customers, specialists see their assigned customers

---

## 6. ROOT CAUSE ANALYSIS

### Hypothesis 1: Code Path Not Called
**Evidence:** The booking creation code (`BookingService::createGuestBooking` and `createBookingWithAccount`) includes calls to `CustomerService::addSalonRelationship()`. However, the audit shows 0 relationships created.

**Possible Causes:**
1. Bookings were created through a different code path that bypasses `BookingService`
2. The `addSalonRelationship()` call is within a transaction that was rolled back
3. The method is throwing an exception that's being silently caught

### Hypothesis 2: Database Issue
**Evidence:** `CustomerService::addSalonRelationship()` uses PostgreSQL-specific `ON CONFLICT` syntax. If the database is not PostgreSQL, this would fail.

**Investigation Needed:** Check database driver and configuration.

### Hypothesis 3: Alternative Booking Creation Path
**Evidence:** There may be a walk-in or test booking path that creates bookings directly without going through `BookingService`.

**Investigation Needed:** Search for direct `Booking::create()` calls outside of `BookingService`.

### Hypothesis 4: Migration Not Run
**Evidence:** The `customer_providers` table was created on 2026-09-22. If the migration was not run on the production database, the table wouldn't exist.

**Investigation Needed:** Verify the table exists and has the correct schema.

---

## 7. DASHBOARD KPIs AUDIT

### PulseController (Dashboard KPIs)
**File:** `backend/app/Http/Controllers/Api/V1/PulseController.php`

**Implementation:**
```php
public function index(Request $request)
{
    $providerId = $request->attributes->get('provider_id');
    $locationId = $request->attributes->get('location_id');

    // Fetch Today's Bookings
    $bookings = Booking::with(['customer', 'service', 'staff'])
        ->where('location_id', $locationId)
        ->whereDate('date', $today)
        ->orderBy('time', 'asc')
        ->get();
}
```

**Relationship Source:** Direct query on `bookings` table (does NOT use `customer_providers`)
**Impact:** KPIs should work correctly even without `customer_providers` relationships

**Status:** Dashboard KPIs are NOT affected by the missing `customer_providers` relationships.

---

## 8. SAFE REMEDIATION PLAN

### Historical Data Backfill

#### Rule 1: Infer from Bookings
For customers with bookings, infer provider relationship from the booking's `provider_id`.

**Affected Records:** 11 customers with bookings

**Proposed Logic:**
```php
foreach ($booking as $booking) {
    $customer = $booking->customer;
    $providerId = $booking->provider_id;

    $exists = DB::table('customer_providers')
        ->where('customer_id', $customer->id)
        ->where('provider_id', $providerId)
        ->exists();

    if (!$exists) {
        DB::table('customer_providers')->insert([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'provider_id' => $providerId,
            'visits' => 0,
            'joined_at' => $booking->created_at,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

#### Rule 2: Guest Customers
Guest customers have `guest_provider_id` field. Use this to create provider relationship.

**Affected Records:** 16 guest customers

**Proposed Logic:**
```php
$guestCustomers = Customer::where('is_guest', true)
    ->whereNotNull('guest_provider_id')
    ->get();

foreach ($guestCustomers as $customer) {
    $exists = DB::table('customer_providers')
        ->where('customer_id', $customer->id)
        ->where('provider_id', $customer->guest_provider_id)
        ->exists();

    if (!$exists) {
        DB::table('customer_providers')->insert([
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'provider_id' => $customer->guest_provider_id,
            'visits' => 0,
            'joined_at' => $customer->created_at,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

#### Rule 3: Customers Without Bookings or guest_provider_id
**Affected Records:** 2 customers (test data with no provider association)

**Action:** Do NOT associate. These are orphaned test records. Leave as-is or delete if confirmed test data.

### Permanent Fix

#### Fix 1: Verify BookingService Execution
Add logging to `BookingService::addSalonRelationship()` to confirm it's being called and succeeding.

#### Fix 2: Database Compatibility Check
Verify the database is PostgreSQL and `ON CONFLICT` syntax is supported. If MySQL, switch to Eloquent `attach()` method.

#### Fix 3: Transaction Safety
Ensure `addSalonRelationship()` is called within the same transaction as booking creation, not after.

#### Fix 4: Alternative Path Audit
Add logging to all `Booking::create()` calls to identify any paths that bypass `BookingService`.

---

## 9. REGRESSION TESTS NEEDED

### Test 1: Guest Booking Creates Provider Relationship
```php
public function test_guest_booking_creates_provider_relationship()
{
    $provider = Provider::factory()->create();
    $location = Location::factory()->for($provider)->create();

    $bookingData = [
        'provider_id' => $provider->id,
        'location_id' => $location->id,
        'customer_name' => 'Test Guest',
        'service_id' => Service::factory()->for($provider)->create()->id,
        'date' => now()->toDateString(),
        'time' => '10:00',
        'internal_booking' => true,
    ];

    $booking = app(BookingService::class)->createGuestBooking($bookingData);

    $this->assertDatabaseHas('customer_providers', [
        'customer_id' => $booking->customer_id,
        'provider_id' => $provider->id,
    ]);
}
```

### Test 2: Booking With Phone Creates Provider Relationship
```php
public function test_booking_with_phone_creates_provider_relationship()
{
    $provider = Provider::factory()->create();
    $location = Location::factory()->for($provider)->create();

    $bookingData = [
        'provider_id' => $provider->id,
        'location_id' => $location->id,
        'customer_phone' => '1234567890',
        'customer_name' => 'Test Customer',
        'service_id' => Service::factory()->for($provider)->create()->id,
        'date' => now()->toDateString(),
        'time' => '10:00',
    ];

    $booking = app(BookingService::class)->createGuestBooking($bookingData);

    $this->assertDatabaseHas('customer_providers', [
        'customer_id' => $booking->customer_id,
        'provider_id' => $provider->id,
    ]);
}
```

### Test 3: Owner API Returns Customers With Provider Relationship
```php
public function test_owner_api_returns_customers_with_provider_relationship()
{
    $user = User::factory()->withProvider()->create();
    $provider = $user->currentProvider();
    $customer = Customer::factory()->create();
    $customer->providers()->attach($provider->id);

    $response = $this->actingAs($user)
        ->getJson('/api/v1/customers');

    $response->assertStatus(200);
    $response->assertJsonCount(1);
}
```

---

## 10. FINDINGS SUMMARY

### Schema
- `customer_providers` pivot table exists and has correct schema
- Created 2026-09-22, 2+ months after customers/bookings tables

### Data Integrity
- **CRITICAL:** 0 customer-provider relationships exist (should be 11+)
- 11 customers have bookings but no provider relationships
- 16 guest customers have no provider relationships
- All bookings belong to Emo Spa but customers are not associated

### Customer Creation Paths
- `BookingService::createGuestBooking()` - includes `addSalonRelationship()` call
- `BookingService::createBookingWithAccount()` - includes `addSalonRelationship()` call
- `CustomerService::addSalonRelationship()` - uses PostgreSQL `ON CONFLICT` syntax
- `CustomerResolver::resolveOrCreateForProvider()` - uses Eloquent `attach()`
- All paths appear correct in code

### Clients Retrieval Paths
- **Owner API:** Uses `customer_providers` pivot (canonical) - **BROKEN** due to missing relationships
- **Specialist API:** Uses `customer_specialist` pivot (specialist-specific) - **UNKNOWN STATUS**
- **Dashboard KPIs:** Uses direct booking query - **NOT AFFECTED**

### Root Cause
**UNKNOWN** - Code appears correct but relationships are not being created. Likely causes:
1. Alternative booking creation path bypassing `BookingService`
2. Database compatibility issue with `ON CONFLICT` syntax
3. Silent exception in transaction
4. Migration not run on database

### Affected Components
- **BROKEN:** Owner's Clients page
- **BROKEN:** Any code relying on `customer_providers` relationship
- **NOT AFFECTED:** Dashboard KPIs (uses direct booking query)
- **UNKNOWN:** Specialist Portal Clients (uses different relationship)

---

## 11. RECOMMENDED ACTIONS

### Immediate (Before Fix)
1. Verify database is PostgreSQL and `customer_providers` table exists
2. Add logging to `BookingService::addSalonRelationship()` calls
3. Search for all `Booking::create()` calls to identify bypass paths
4. Test booking creation with debug logging enabled

### Short-Term (Historical Data)
1. Create backfill migration with dry-run mode
2. Run dry-run to see affected record counts
3. Review and approve backfill rules
4. Execute backfill in production

### Long-Term (Permanent Fix)
1. Add regression tests for customer-provider relationship creation
2. Verify database compatibility or switch to Eloquent `attach()`
3. Ensure all booking paths use `BookingService`
4. Add monitoring/alerting for missing relationships

---

## 12. NEXT STEPS

1. **Database Verification:** Confirm PostgreSQL and table schema
2. **Logging Investigation:** Add debug logging to booking creation
3. **Alternative Path Audit:** Search for direct `Booking::create()` calls
4. **Backfill Preparation:** Create migration with dry-run mode
5. **Test Execution:** Run regression tests to verify fix

**DO NOT** execute backfill until:
- Root cause is identified
- Backfill rules are reviewed
- Dry-run shows correct affected records
- Regression tests pass

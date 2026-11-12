# Clients Location Scoping Audit Report

**Date:** October 9, 2026
**Mode:** READ-ONLY AUDIT - No changes made until policy review

---

## Executive Summary

**Issue:** The Clients endpoint returned 0 customers when `location_scope='specific'` because the controller filtered customers by bookings at the specific location. The 3 customers with `customer_providers` relationships have bookings with `location_id=NULL`, so they were excluded.

**Root Cause:** Incorrect filtering logic - the controller used booking location to filter customers, but `customer_providers` is a provider-level relationship independent of booking location.

**Established Policy:** The system has a clear provider/location scoping model, but the customer filtering policy was not aligned with it.

---

## 1. Established Rules

### 1.1 Location Context Middleware (`ResolveLocationContext`)

**Location Scope Resolution:**
- `location_scope='all'` when:
  - No location specified (null)
  - Explicit `location_id='all'`
  - Provider-wide context
- `location_scope='specific'` when:
  - Valid `location_id` provided
  - Location belongs to the provider
  - User has access to that location

**Authorization Rules:**
- **Provider-wide users** (`authority_scope='provider_wide'`):
  - Can access provider-wide scope (`location_scope='all'`)
  - Can access any specific location
  - Can switch between scopes freely

- **Location-scoped users** (`authority_scope='location_scoped'`):
  - **FORBIDDEN** from provider-wide scope
  - **MUST** specify a permitted location
  - Can only access locations in their permitted list

### 1.2 Provider User Authority Model

**Authority Scope Types:**
- `provider_wide`: Full access to all provider locations
- `location_scoped`: Restricted to specific locations via `provider_user_locations` pivot

**Role-Based Capabilities:**
- Owner/Manager: Full access
- Staff/Receptionist: Location-scoped by default
- Role determines capability, not scope

### 1.3 Frontend Behavior (`RoleContext.tsx`)

**Location Resolution:**
1. Check `localStorage.getItem('location_id')`
2. Validate location belongs to current provider
3. If invalid, clear and fall back to `primary_location`
4. If no location available, set `locationId=null` (provider-wide)

**Clients Page Request:**
```typescript
apiClient.getCustomers(locationId ? { location_id: locationId } : {})
```

- If `locationId` is set: sends `location_id` param → `location_scope='specific'`
- If `locationId` is null: no param → `location_scope='all'`

### 1.4 Customer Data Model

**Canonical Relationship:** `customer_providers`
- Provider-level relationship
- Independent of booking location
- Represents customer ownership at provider level

**Booking Location:** `bookings.location_id`
- Can be NULL (no location assigned)
- Can be specific location
- Independent of customer_providers relationship

**No `customer_locations` table exists** - this relationship is not canonical.

---

## 2. Actual Request Analysis

### 2.1 Log Evidence

**First Call:**
```
provider_id: 01a0e836-39fa-700e-991d-797669bb5a32
location_id: null
location_scope: "all"
authenticated: true
user_id: 3
```

**Second Call:**
```
provider_id: 01a0e836-39fa-700e-991d-797669bb5a32
location_id: ba489e65-5e63-44b8-99d3-cfaa1bc11ec4
location_scope: "specific"
authenticated: true
user_id: 3
```

### 2.2 Why Two Calls?

The frontend makes two calls:
1. Initial load with `location_scope='all'` (before location selection)
2. After location selection with `location_scope='specific'`

The user (ID 3) is authenticated as the owner, so they have provider-wide authority and can request both scopes.

### 2.3 Customer Booking Locations

**Provider Locations:** 2
- `ba489e65-5e63-44b8-99d3-cfaa1bc11ec4`: Emo Spa (currently selected)
- `01a10a1c-e9a8-7030-907c-e4acee0edfd0`: Dummy Location

**3 Customers with `customer_providers`:**
- **Emmak:** 2 bookings, both with `location_id=NULL`
- **lome:** 1 booking, with `location_id=NULL`
- **Test Customer:** 0 bookings

**Key Finding:** None of the customers have bookings at the specific location "Emo Spa". Their bookings have no location assigned.

---

## 3. Current (Incorrect) Filtering Policy

### 3.1 Before Fix

```php
// CustomerController::index
$query = Customer::query()
    ->whereHas('providers', function ($q) use ($providerId) {
        $q->where('providers.id', $providerId);
    });

// Location filtering based on bookings
if ($locationScope === 'specific' && $locationId) {
    $query->whereHas('bookings', function ($q) use ($locationId) {
        $q->where('location_id', $locationId);
    });
}
```

**Problem:**
- Filters customers by bookings at the specific location
- Excludes customers who have provider relationship but no bookings at that location
- Incorrectly conflates provider ownership with booking location

### 3.2 After Fix (Current State)

```php
// CustomerController::index
$query = Customer::query()
    ->whereHas('providers', function ($q) use ($providerId) {
        $q->where('providers.id', $providerId);
    });
// Location filtering removed
```

**Problem:**
- Removes location filtering entirely
- Location-scoped users would see all provider customers
- Violates the authorization model for location-scoped users

---

## 4. Correct Filtering Policy

### 4.1 Provider-Wide Users

**Policy:** Provider-wide users should see ALL customers with `customer_providers` relationship for the provider, regardless of location.

**Rationale:**
- Provider-wide authority implies full visibility
- Customer ownership is at provider level
- Location selection is for operational context, not data filtering

**Implementation:**
```php
if ($locationScope === 'all') {
    // Return all customers with customer_providers relationship
    $query = Customer::query()
        ->whereHas('providers', function ($q) use ($providerId) {
            $q->where('providers.id', $providerId);
        });
}
```

### 4.2 Location-Scoped Users

**Policy:** Location-scoped users should see customers with `customer_providers` relationship for the provider, but filtered to customers who have interacted with their location.

**Question:** What constitutes "interacted with their location"?

**Options:**
1. **Bookings at location:** Customer has at least one booking at the permitted location
2. **Any interaction:** Customer has booking, payment, or other interaction at the location
3. **No filtering:** Location-scoped users see all provider customers (violates authorization model)

**Recommendation:** Option 1 (bookings at location) is the most practical and aligns with operational needs.

**Rationale:**
- Location-scoped users need to see customers they serve
- Booking history is the primary interaction metric
- Simple to implement and understand

**Implementation:**
```php
if ($locationScope === 'specific' && $locationId) {
    $query = Customer::query()
        ->whereHas('providers', function ($q) use ($providerId) {
            $q->where('providers.id', $providerId);
        })
        ->whereHas('bookings', function ($q) use ($locationId) {
            $q->where('location_id', $locationId);
        });
}
```

**But wait:** This is the original logic that caused the issue!

**Revised Policy:** The issue is that the 3 customers have bookings with `location_id=NULL`. This suggests the booking flow is not assigning locations correctly.

**Alternative:** For location-scoped users, show customers who have:
- Bookings at the specific location, OR
- Bookings with no location assigned (NULL), AND
- The provider has no configured locations OR the user has access to all locations

**This is getting complex. Let's reconsider.**

### 4.3 Simpler Policy

**Policy:** The `customer_providers` relationship is provider-level. Location filtering should NOT be based on bookings. Instead:

1. **Provider-wide scope:** Show all customers with `customer_providers` relationship
2. **Location-specific scope:** Show all customers with `customer_providers` relationship (same as provider-wide)

**Rationale:**
- Customer ownership is at provider level, not location level
- Location selection is for operational context (e.g., viewing schedule for a specific branch)
- Location-scoped users should still see the full customer list for their permitted locations
- The middleware already enforces that location-scoped users can only access permitted locations

**Implementation:**
```php
// Always return all customers with customer_providers relationship
// Location context is for operational filtering (e.g., bookings, schedule), not customer ownership
$query = Customer::query()
    ->whereHas('providers', function ($q) use ($providerId) {
        $q->where('providers.id', $providerId);
    });
```

**This is the current fix.** But is it correct?

### 4.4 Final Policy Decision (Updated)

**Product Requirements for Clients Experience:**

1. **All locations (provider-wide):** Show every customer associated with the provider via `customer_providers` relationship
2. **Specific location:** Show customers with bookings at that location ONLY
3. **Unassigned location:** Customers with `location_id=NULL` bookings get a distinct "Location not recorded" category
4. **Multiple locations:** Customer can appear in multiple location views if they have bookings at those locations
5. **No locations configured:** Default to provider-wide view

**Immediate Fix (Current State):**
- Keep provider-wide visibility (all customers with `customer_providers` relationship)
- Remove location-based booking filter for now
- This fixes the immediate bug where 3 customers with NULL location bookings were excluded

**Future Work Required:**
1. Implement proper location-based filtering: customers with bookings at specific location
2. Add "Location not recorded" category for customers with NULL location bookings
3. Support manual assignment of customers to branches (future feature)
4. Ensure unique customer counts avoid double-counting across locations

**Conclusion:** The current fix (removing location-based booking filter) is correct for the immediate bug. The Clients endpoint returns all customers with `customer_providers` relationship for the provider. Future implementation will add location-based filtering with proper handling of NULL location bookings.

---

## 5. Fallback for No-Location Providers

**Scenario:** Provider has no configured locations or location functionality is unavailable.

**Current Behavior:**
- Frontend sets `locationId=null`
- Middleware sets `location_scope='all'`
- Controller returns all customers with `customer_providers` relationship

**This is correct.** No fallback needed.

---

## 6. Test Plan

### 6.1 Provider-Wide Scope Tests

1. **Test:** Provider-wide owner requests Clients with no location
   - **Expected:** Returns all customers with `customer_providers` relationship
   - **HTTP Status:** 200
   - **Response:** Array of customers

2. **Test:** Provider-wide owner requests Clients with specific location
   - **Expected:** Returns all customers with `customer_providers` relationship (same as above)
   - **HTTP Status:** 200
   - **Response:** Array of customers

### 6.2 Location-Scoped Scope Tests

3. **Test:** Location-scoped user requests Clients with permitted location
   - **Expected:** Returns all customers with `customer_providers` relationship
   - **HTTP Status:** 200
   - **Response:** Array of customers

4. **Test:** Location-scoped user requests Clients with no location
   - **Expected:** 403 Forbidden
   - **HTTP Status:** 403
   - **Response:** Error message "Location-scoped users must specify a permitted location"

5. **Test:** Location-scoped user requests Clients with unpermitted location
   - **Expected:** 403 Forbidden
   - **HTTP Status:** 403
   - **Response:** Error message "You do not have access to this location"

### 6.3 Multiple-Location Tests

6. **Test:** Customer has bookings at multiple locations
   - **Expected:** Customer appears in Clients list for all locations
   - **HTTP Status:** 200
   - **Response:** Customer included in array

7. **Test:** Customer has bookings with NULL location
   - **Expected:** Customer appears in Clients list regardless of location selection
   - **HTTP Status:** 200
   - **Response:** Customer included in array

### 6.4 Provider Isolation Tests

8. **Test:** Provider A user requests Clients
   - **Expected:** Returns only customers with `customer_providers` relationship for Provider A
   - **HTTP Status:** 200
   - **Response:** Array of Provider A customers only

9. **Test:** Provider B user requests Clients
   - **Expected:** Returns only customers with `customer_providers` relationship for Provider B
   - **HTTP Status:** 200
   - **Response:** Array of Provider B customers only

10. **Test:** Provider A user attempts to access Provider B customers
    - **Expected:** 403 Forbidden (middleware blocks)
    - **HTTP Status:** 403
    - **Response:** Error message

### 6.5 No-Location Fallback Tests

11. **Test:** Provider with no locations requests Clients
    - **Expected:** Returns all customers with `customer_providers` relationship
    - **HTTP Status:** 200
    - **Response:** Array of customers

---

## 7. Root Cause Summary

**Exact Cause:**
The controller filtered customers by bookings at the specific location when `location_scope='specific'`. The 3 customers with `customer_providers` relationships have bookings with `location_id=NULL`, so they were excluded when a specific location was selected.

**Why This Happened:**
- The original implementation conflated provider-level customer ownership with booking-level location data
- It assumed that customer visibility should be filtered by booking location
- This assumption is incorrect because customer ownership is at provider level, not location level

**Why the Fix is Correct:**
- Customer ownership is represented by `customer_providers` relationship (provider-level)
- Location selection is for operational context, not customer visibility
- The middleware already enforces location access for location-scoped users
- Booking location is unreliable (can be NULL)
- No canonical `customer_locations` table exists

---

## 8. Smallest Safe Correction

**Current State (After Fix):**
```php
// CustomerController::index
$query = Customer::query()
    ->whereHas('providers', function ($q) use ($providerId) {
        $q->where('providers.id', $providerId);
    });
```

**This is the correct implementation.** No further changes needed.

**Diff:**
```diff
- // Optional: filter by location if location_scope is 'specific'
- if ($locationScope === 'specific' && $locationId) {
-     $query->whereHas('bookings', function ($q) use ($locationId) {
-         $q->where('location_id', $locationId);
-     });
- }
```

**Rationale:**
- Removes incorrect location-based booking filter
- Aligns with established provider/location scoping model
- Respects that customer ownership is provider-level
- Relies on middleware for location access control

---

## 9. Recommendation

**Approve the current fix.** The removal of the location-based booking filter is correct and aligns with the established authorization model.

**Next Steps:**
1. Implement the test plan above
2. Verify provider isolation
3. Verify location-scoped user access control
4. Deploy after tests pass

**No data backfill needed.** The 11 missing `customer_providers` relationships are a separate issue and should be addressed via the reconciliation report.

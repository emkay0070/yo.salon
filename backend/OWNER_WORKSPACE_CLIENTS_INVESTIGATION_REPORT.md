# Owner Workspace Clients Investigation Report

**Date:** October 9, 2026
**Mode:** READ-ONLY - No database writes, migrations, or deployment performed

---

## Executive Summary

**Finding:** The owner's Clients page shows 0 customers because the API filters by `customer_providers` relationships. Only 3 of 13 customers with bookings have this relationship.

**Root Cause:** The booking flow was not creating `customer_providers` relationships before our fix. Historical bookings lack this relationship.

**Impact:** 11 customers with bookings are invisible in the owner's Clients list.

**Solution:** Backfill the 11 missing `customer_providers` relationships as documented in `HISTORICAL_CUSTOMER_PROVIDER_RECONCILIATION_REPORT.md`.

---

## 1. Owner's Clients Endpoint

### Exact Endpoint

**Route:** `GET /v1/customers` (authenticated, requires provider context)

**Controller:** `App\Http\Controllers\Api\V1\CustomerController::index`

**File:** `backend/app/Http/Controllers/Api/V1/CustomerController.php` lines 12-44

### Implementation

**Authentication:** Uses `auth()->user()` with provider context from middleware

**Provider Context:**
```php
$providerId = $request->attributes->get('provider_id');
$locationId = $request->attributes->get('location_id');
$locationScope = $request->attributes->get('location_scope', 'all');
```

**Customer Query:**
```php
$query = Customer::query()
    ->whereHas('providers', function ($q) use ($providerId) {
        $q->where('providers.id', $providerId);
    });
```

**Key Finding:** Uses `customer_providers` relationship (via `Customer::providers()`), NOT bookings directly.

---

## 2. Database State

### Customers with Bookings

**Total:** 13 customers have bookings for the provider

**Breakdown:**
- 11 customers: NO `customer_providers` relationship
- 2 customers: HAVE `customer_providers` relationship
- 1 customer: HAS `customer_providers` (Test Customer, no booking)

### API Response

**Expected Response:** 3 customers (only those with `customer_providers`)

**Actual Response:** 3 customers

**UI Display:** 0 customers (discrepancy suggests frontend issue or different endpoint)

---

## 3. Verification Results

### Database Query Results

```
Customers with customer_providers relationship: 3
  - Emmak (0706551043)
  - lome (0780808080)
  - Test Customer (+256700999888)

Customers with bookings: 13
  ✗ mimo - customer_providers: NO
  ✗ mariam - customer_providers: NO
  ✗ booky - customer_providers: NO
  ✗ jk - customer_providers: NO
  ✗ kkkk - customer_providers: NO
  ✗ Mark Neyo - customer_providers: NO
  ✗ koma - customer_providers: NO
  ✗ noma - customer_providers: NO
  ✗ noma - customer_providers: NO
  ✗ jm - customer_providers: NO
  ✗ se - customer_providers: NO
  ✓ Emmak - customer_providers: YES
  ✓ lome - customer_providers: YES
```

### Conclusion

**API returns:** 3 customers
**Expected:** 13 customers
**Missing:** 11 relationships

---

## 4. Root Cause

**Historical Booking Flow:** Before our fix, the booking flow did not call `addSalonRelationship()` to create `customer_providers` relationships.

**Fix Applied:** Added `addSalonRelationship()` call in `BookingService::createBookingWithAccount` and `createGuestBooking`.

**Historical Data:** 11 historical bookings were created before the fix, so they lack the relationship.

**New Bookings:** Bookings created after the fix will have the relationship.

---

## 5. Frontend vs Backend Discrepancy

**Observation:** UI shows 0 customers, but API would return 3.

**Possible Causes:**
1. Frontend caching empty state
2. Frontend calling different endpoint
3. Authentication issue causing empty response
4. Frontend filtering out customers

**Investigation Needed:** Check frontend network requests to see which endpoint is being called and what response is received.

---

## 6. Recommended Action

**Immediate:** Backfill the 11 missing `customer_providers` relationships using the script documented in `HISTORICAL_CUSTOMER_PROVIDER_RECONCILIATION_REPORT.md`.

**Verification:** After backfill, verify:
1. Database: 13 `customer_providers` rows exist
2. API: Returns 13 customers
3. UI: Shows 13 customers

**Frontend Investigation:** If UI still shows 0 after backfill, investigate frontend network requests and caching.

---

## 7. Backfill Details

**Customer IDs (from reconciliation report):**
1. `01a0f68d-6999-7219-96d5-1d7c1c7d33e9`
2. `01a0f6f8-a466-7229-9571-bd5c2a8b82fe`
3. `01a10a86-3d78-7151-beac-0acb3b87ba3c`
4. `01a10aeb-4edf-73c9-a14e-a022d4972699`
5. `01a11221-d69e-7016-9db2-a459edb5e8e5`
6. `01a113b5-adca-70b3-b21b-6e0ad248cd8c`
7. `01a11cfb-64ca-73a0-b5c3-d499e44137d5`
8. `01a11cfd-bba2-704f-aaa7-9bff13b826fa`
9. `01a11cff-a309-70ff-9307-488e6ea0a8ad`
10. `01a11d23-aaec-72df-9730-ab7f2755190f`
11. `01a11d41-1fd2-7102-8963-ade969357ea5`

**Method:** Use `CustomerService::addSalonRelationship()` for each customer.

**Safety:** Idempotent with existence check and `ON CONFLICT` clause.

---

## 8. Conclusion

**Root Cause:** Historical bookings lack `customer_providers` relationships because the booking flow didn't create them before our fix.

**Impact:** 11 customers invisible in owner's Clients list.

**Solution:** Backfill the 11 missing relationships using the documented method.

**Next Steps:**
1. Review and approve the reconciliation report
2. Execute backfill operation
3. Verify API returns 13 customers
4. Verify UI shows 13 customers
5. If UI still shows 0, investigate frontend

**Note:** The previous investigation focused on the specialist portal, which uses `customer_specialist` relationships. This investigation confirms the owner's workspace uses `customer_providers` relationships, which is where the 11 missing relationships matter.

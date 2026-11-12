# Clients List Ghosts on Refresh Investigation Report

**Date:** October 9, 2026
**Mode:** READ-ONLY - No database writes, migrations, or deployment performed

---

## Executive Summary

**Finding:** The Specialist Portal Clients list uses `customer_specialist` relationships, NOT `customer_providers` relationships. The historical data issue (11 missing `customer_providers` relationships) is unrelated to the specialist portal visibility.

**Root Cause of Ghosts:** The frontend 401 redirect logic treats any 401 response as a definitive logout, clearing the specialist token and redirecting to login. If the Clients API returns 401 (e.g., due to missing `provider_slug` parameter or session restoration timing), the user is logged out immediately.

**Data Reality:** Of the 3 customers with `customer_providers` relationships, only 2 have `customer_specialist` relationships and will appear in the specialist's Clients list. The 3rd customer will not appear regardless of authentication.

---

## 1. Authentication/Session Restoration

### Frontend Token Management

**File:** `frontend/src/lib/api-client.ts` lines 51-66

**Token Selection Logic:**
```typescript
let token = localStorage.getItem('auth_token');
const specialistToken = localStorage.getItem('specialist_auth_token');

// If the request explicitly targets the specialist portal, use the specialist token instead
if (config.url?.includes('/specialist-portal/')) {
  token = specialistToken || token;
}
```

**Key Finding:** Specialist portal requests use `specialist_auth_token`, not the main `auth_token`.

### Redirect Trigger

**File:** `frontend/src/lib/api-client.ts` lines 101-124

**401 Response Handler:**
```typescript
if (error.response?.status === 401) {
  if (typeof window !== 'undefined') {
    // Only redirect if the failed request was explicitly for the specialist portal
    if (error.config?.url?.includes('/specialist-portal/')) {
      localStorage.removeItem('specialist_auth_token');
      if (!window.location.pathname.startsWith('/specialist-portal/login')) {
        window.location.href = '/specialist-portal/login';
      }
    }
  }
}
```

**Redirect Trigger:** Any 401 response on a specialist-portal endpoint clears the token and redirects to login.

**Issue:** This treats temporary 401s (e.g., session restoration timing) as definitive logout.

---

## 2. Clients API Request

### Exact Endpoint

**Frontend Call:** `frontend/src/services/specialist/api/clients.ts` line 5
```typescript
const json = await apiClient.get('/v1/specialist-portal/customers');
```

**Backend Route:** `backend/routes/api.php` line 161
```php
Route::get('/customers', [SpecialistPortalController::class, 'getCustomers']);
```

**Full Endpoint:** `GET /v1/specialist-portal/customers?provider_slug={slug}`

### Controller Implementation

**File:** `backend/app/Controllers/SpecialistPortalController.php` lines 292-404

**Authentication:**
```php
$account = auth('specialist')->user();
$specialist = $account->specialist;
```

**Workspace Context Resolution:**
```php
$providerSlug = $request->query('provider_slug');

if ($providerSlug) {
    $assignments = SpecialistAssignment::where('specialist_id', $specialist->id)
        ->whereIn('status', ['ACTIVE', 'active'])
        ->whereHas('provider', fn($q) => $q->where('slug', $providerSlug))
        ->get();

    if ($assignments->isEmpty()) {
        return response()->json(['message' => 'Invalid workspace context'], 403);
    }
}
```

**Customer Query:**
```php
$customers = $specialist->customers()
    ->wherePivot('provider_id', $providerId)
    ->withPivot('is_favorite', 'is_following', 'total_bookings', 'total_spent', 'last_interaction_at')
    ->orderBy('pivot_last_interaction_at', 'desc')
    ->get();
```

**Key Finding:** Uses `specialist->customers()` relationship (customer_specialist pivot), NOT `customer_providers`.

---

## 3. Authentication Headers and Provider/Location Context

### Request Headers

**Authorization:** `Bearer {specialist_auth_token}`

**Query Parameters:**
- `provider_slug` (required) - Provider slug for workspace context
- `location_slug` (optional) - Filter by location activity

### Backend Context Resolution

**File:** `SpecialistPortalController.php` lines 305-317

**Process:**
1. Extract `provider_slug` from query parameters
2. Verify specialist has active assignment at provider with that slug
3. Derive `provider_id` from verified assignment
4. Check location access if `location_slug` provided

**Failure Conditions:**
- Missing `provider_slug`: Returns 400 error
- No active assignment at provider: Returns 403 error
- No location access: Returns 403 error

**Key Finding:** The endpoint requires `provider_slug` parameter. If missing or invalid, returns error (not 401, but could be treated as auth failure by frontend).

---

## 4. Authenticated Owner to Provider ID Mapping

### Mapping Process

**File:** `SpecialistPortalController.php` lines 305-317

**Steps:**
1. Get authenticated specialist from `auth('specialist')->user()`
2. Query `SpecialistAssignment` for specialist at provider with matching slug
3. Extract `provider_id` from verified assignment
4. Use `provider_id` to scope customer query

**Consistency Check:**
- Provider ID is derived from verified assignment record
- Assignment must be ACTIVE
- Provider slug must match exactly
- No session restoration changes the mapping

**Key Finding:** Provider ID is consistently derived from the specialist's active assignment. No scope changes during session restoration.

---

## 5. Database vs API vs Frontend Records

### Database State

**customer_providers relationships:** 3 exist for the provider
- Customer ID: `01a11f1a-4795-71ea-b1b9-f7706f9c5bc1` - Has customer_providers
- Customer ID: `01a11f22-de30-7009-9afc-398cb8d3498b` - Has customer_providers
- Customer ID: `01a11f4e-cf1b-715e-9bd8-a0cec8d61996` - Has customer_providers

**customer_specialist relationships:** 9 exist for the specialist at this provider
- Specialist ID: `01a0e836-3a6a-735a-a769-5be031d25a04`
- 10 customers have bookings with this specialist
- 9 have customer_specialist relationships
- 1 is missing customer_specialist relationship

### Cross-Reference

**Customer 1:** `01a11f1a-4795-71ea-b1b9-f7706f9c5bc1`
- ✅ Has customer_providers
- ✅ Has customer_specialist (2 bookings)
- **Will appear in specialist Clients list**

**Customer 2:** `01a11f22-de30-7009-9afc-398cb8d3498b`
- ✅ Has customer_providers
- ✅ Has customer_specialist (1 booking)
- **Will appear in specialist Clients list**

**Customer 3:** `01a11f4e-cf1b-715e-9bd8-a0cec8d61996`
- ✅ Has customer_providers
- ❌ NO customer_specialist relationship
- **Will NOT appear in specialist Clients list**

### API Response

**Expected Response Shape:**
```json
{
  "customers": [
    {
      "id": "...",
      "name": "...",
      "phone": "...",
      "pivot": {
        "is_favorite": false,
        "is_following": false,
        "total_bookings": 2,
        "total_spent": 100000,
        "last_interaction_at": "2026-10-09 06:46:44",
        "provider_id": "...",
        "relationship_origin": "...",
        "acquisition_source": "..."
      }
    }
  ],
  "total": 2,
  "workspace_filtered": true,
  "location_filtered": false,
  "provider_slug": "...",
  "provider_id": "...",
  "provider_wide_access": true
}
```

**Expected Count:** 2 customers (only those with customer_specialist relationships)

### Frontend Records

**Frontend expects:** `json.customers` from response (line 6 of clients.ts)

**React Query Cache:** Query key `['specialist-clients']`

**Potential Issue:** If API returns empty array or error, React Query might cache empty state, causing list to appear empty even after refresh.

---

## 6. Root Cause Analysis

### Primary Issue: 401 Redirect Logic

**Location:** `frontend/src/lib/api-client.ts` lines 104-121

**Problem:** Any 401 response on specialist-portal endpoint triggers immediate logout and redirect, even if it's a temporary issue (e.g., session restoration timing, missing query parameter).

**Scenario:**
1. User refreshes page
2. Frontend loads and immediately requests Clients API
3. If `provider_slug` is missing or session not fully restored, API returns error
4. Frontend treats error as 401 (or actual 401 if token invalid)
5. Frontend clears token and redirects to login
6. User sees login page instead of Clients list

### Secondary Issue: Wrong Relationship Used

**Finding:** The specialist portal uses `customer_specialist` relationships, but we've been investigating `customer_providers` relationships.

**Impact:** The 11 historical customers missing `customer_providers` are irrelevant to the specialist portal Clients list. The specialist portal will only show customers with `customer_specialist` relationships.

**Data Gap:** 1 customer has `customer_providers` but no `customer_specialist`, so they won't appear in the specialist's list even though they have the provider relationship.

---

## 7. Proposed Fix (Not Applied)

### Fix 1: Improve 401 Handling

**File:** `frontend/src/lib/api-client.ts`

**Change:** Add retry logic or distinguish between temporary and permanent 401s.

```typescript
if (error.response?.status === 401) {
  if (typeof window !== 'undefined') {
    // Only redirect if the failed request was explicitly for the specialist portal
    if (error.config?.url?.includes('/specialist-portal/')) {
      // Check if this is a retry or a definitive auth failure
      const isRetry = error.config?.__isRetry;

      if (!isRetry) {
        // Mark as retry and attempt once more
        error.config.__isRetry = true;
        return this.client.request(error.config);
      }

      // If retry also failed, then logout
      localStorage.removeItem('specialist_auth_token');
      if (!window.location.pathname.startsWith('/specialist-portal/login')) {
        window.location.href = '/specialist-portal/login';
      }
    }
  }
}
```

**Rationale:** Allows transient 401s to be retried before treating as definitive logout.

### Fix 2: Ensure provider_slug is Always Sent

**File:** `frontend/src/services/specialist/api/clients.ts`

**Change:** Add provider_slug to request or ensure it's available from context.

```typescript
export const specialistClientsApi = {
  getClients: async (providerSlug?: string) => {
    const params = providerSlug ? { provider_slug: providerSlug } : {};
    const json = await apiClient.get('/v1/specialist-portal/customers', { params });
    return json.customers;
  },
  // ...
};
```

**Rationale:** Prevents 400/403 errors due to missing required parameter.

### Fix 3: Add Error Boundary for Clients List

**File:** Frontend Clients component

**Change:** Add error boundary to handle API failures gracefully without redirecting.

**Rationale:** Prevents entire page from redirecting on single API failure.

---

## 8. Historical Data Findings (11 Missing customer_providers)

**Status:** UNRELATED to specialist portal Clients list issue.

**Reason:** Specialist portal uses `customer_specialist` relationships, not `customer_providers`.

**Impact:** The 11 customers missing `customer_providers` relationships will not affect the specialist portal Clients list. They may affect the owner/staff portal Clients list if that endpoint uses `customer_providers`.

**Recommendation:** Still proceed with the backfill for `customer_providers` relationships as documented in the separate reconciliation report, but understand this is for a different use case (owner/staff portal, not specialist portal).

---

## 9. Network Request Sequence on Refresh

**Expected Sequence:**
1. Page loads
2. React Query cache check for `['specialist-clients']`
3. If cache miss or stale, API request: `GET /v1/specialist-portal/customers?provider_slug={slug}`
4. API returns 200 with customers array
5. Frontend renders list

**Failure Sequence (Current):**
1. Page loads
2. React Query cache check
3. API request fires
4. If `provider_slug` missing or session not restored: API returns error
5. Frontend treats error as 401
6. Frontend clears token and redirects to login
7. User sees login page

**HTTP Statuses:**
- 200: Success
- 400: Missing provider_slug
- 403: No active assignment or no location access
- 401: Invalid token (triggers redirect)

---

## 10. Failure Classification

**Primary Failure:** Authentication/Session Restoration
- Frontend 401 redirect logic is too aggressive
- Treats any auth error as definitive logout
- No retry mechanism for transient failures

**Secondary Failure:** API Filtering
- Specialist portal uses `customer_specialist`, not `customer_providers`
- 1 customer has provider relationship but no specialist relationship
- 11 historical customers missing provider relationships (unrelated to this issue)

**Tertiary Failure:** Frontend State
- React Query might cache empty state on error
- No error boundary to handle API failures gracefully

---

## 11. Smallest Justified Fix

**Fix:** Add retry logic to 401 handler in `api-client.ts`

**Justification:**
- Minimal code change
- Addresses the root cause (aggressive logout)
- Allows transient auth failures to be retried
- Does not change authentication flow
- Does not require backend changes

**Alternative:** Ensure `provider_slug` is always sent with Clients API request from frontend context.

**Recommendation:** Implement both fixes for robustness.

---

## 12. Separate Findings for 11 Missing Historical Relationships

**Status:** Documented in separate report: `HISTORICAL_CUSTOMER_PROVIDER_RECONCILIATION_REPORT.md`

**Summary:** 11 customers with bookings lack `customer_providers` relationships. This affects owner/staff portal Clients list (if that endpoint uses `customer_providers`), but does NOT affect specialist portal Clients list.

**Action:** Proceed with backfill as recommended in that report, but understand it's for a different use case.

---

## Conclusion

**Root Cause:** Frontend 401 redirect logic treats any auth error as definitive logout, causing users to be logged out on refresh if the Clients API fails (e.g., missing `provider_slug`, session restoration timing).

**Data Reality:** Specialist portal uses `customer_specialist` relationships. Only 2 of the 3 customers with `customer_providers` have `customer_specialist` relationships and will appear in the list.

**Recommended Fix:** Add retry logic to 401 handler and ensure `provider_slug` is always sent with Clients API request.

**Historical Data:** The 11 missing `customer_providers` relationships are unrelated to this issue. Proceed with backfill per separate report for owner/staff portal visibility.

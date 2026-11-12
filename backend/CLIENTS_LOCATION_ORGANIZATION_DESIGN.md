# Clients Location Organization Design Document

**Date:** October 9, 2026
**Mode:** Implementation Design

---

## 1. Current State

### 1.1 Endpoint Contract
- **Endpoint:** `GET /api/v1/customers`
- **Middleware:** `provider.context`, `location.context`
- **Current Behavior:** Returns all customers with `customer_providers` relationship for the provider
- **Response:** Array of customer objects with `lastVisit` attribute

### 1.2 Frontend Behavior
- **Location Context:** `RoleContext` provides `locationId` from localStorage or primary_location
- **API Call:** `apiClient.getCustomers(locationId ? { location_id: locationId } : {})`
- **Current Filters:** Search term, VIP, New, Inactive
- **Pagination:** Client-side (8 items per page)

### 1.3 Authorization Model
- **Provider-wide users:** Can access provider-wide scope and any specific location
- **Location-scoped users:** Must specify permitted location, forbidden from provider-wide scope
- **Middleware:** `ResolveLocationContext` enforces location access

---

## 2. Booking Eligibility Rules

### 2.1 Booking Statuses
From code analysis, the following pattern is used consistently:
```php
->where('status', '!=', 'cancelled')
```

**Eligible Booking Statuses:**
- `pending` - Booking created, awaiting confirmation
- `confirmed` - Booking confirmed
- `in_progress` - Service in progress
- `completed` - Service completed
- `no_show` - Customer did not show up
- `cancelled` - **EXCLUDED** (booking was cancelled)

**Rationale:** Cancelled bookings represent failed transactions and should not contribute to customer-location membership.

### 2.2 Query Semantics

#### Provider-Wide View (`location_scope='all'`)
```sql
SELECT * FROM customers
WHERE EXISTS (
    SELECT 1 FROM customer_providers
    WHERE customer_providers.customer_id = customers.id
    AND customer_providers.provider_id = ?
)
```

#### Specific Location View (`location_scope='specific'`)
```sql
SELECT * FROM customers
WHERE EXISTS (
    SELECT 1 FROM customer_providers
    WHERE customer_providers.customer_id = customers.id
    AND customer_providers.provider_id = ?
)
AND EXISTS (
    SELECT 1 FROM bookings
    WHERE bookings.customer_id = customers.id
    AND bookings.provider_id = ?
    AND bookings.location_id = ?
    AND bookings.status != 'cancelled'
)
```

#### Location Not Recorded View
```sql
SELECT * FROM customers
WHERE EXISTS (
    SELECT 1 FROM customer_providers
    WHERE customer_providers.customer_id = customers.id
    AND customer_providers.provider_id = ?
)
AND EXISTS (
    SELECT 1 FROM bookings
    WHERE bookings.customer_id = customers.id
    AND bookings.provider_id = ?
    AND bookings.location_id IS NULL
    AND bookings.status != 'cancelled'
)
```

---

## 3. API Contract Changes

### 3.1 Request Parameters
No changes to request parameters. The middleware continues to inject:
- `provider_id` from `ResolveProviderContext`
- `location_id` from `ResolveLocationContext`
- `location_scope` from `ResolveLocationContext` ('all' or 'specific')

### 3.2 Response Structure
Current response:
```json
[
  {
    "id": "...",
    "name": "...",
    "phone": "...",
    "email": "...",
    "lastVisit": "2026-01-01"
  }
]
```

Proposed response (add location context metadata):
```json
{
  "customers": [
    {
      "id": "...",
      "name": "...",
      "phone": "...",
      "email": "...",
      "lastVisit": "2026-01-01",
      "locationContext": {
        "hasBookingsAtLocation": true,
        "hasNullLocationBookings": false,
        "bookingLocations": ["location-id-1", "location-id-2"]
      }
    }
  ],
  "metadata": {
    "total": 10,
    "locationScope": "all",
    "locationId": null,
    "locationName": null
  }
}
```

**Note:** The metadata helps the frontend understand the current context and display appropriate UI.

### 3.3 New Query Parameter (Optional)
To support "Location not recorded" view, add optional parameter:
```typescript
apiClient.getCustomers({ location_filter: 'unassigned' })
```

Backend mapping:
- `location_filter='unassigned'` → Filter customers with NULL location bookings
- `location_id` present → Filter customers with bookings at that location
- Neither present → Provider-wide view

---

## 4. Frontend Integration

### 4.1 Location Categories
The frontend will display location categories as tabs or dropdown:

**Provider-wide users:**
- "All customers" (default)
- "Emo Spa" (location 1)
- "Dummy Location" (location 2)
- "Location not recorded"

**Location-scoped users:**
- "Emo Spa" (their permitted location only)
- "Location not recorded" (if permitted)

### 4.2 Category Selection Logic
```typescript
const [selectedLocationCategory, setSelectedLocationCategory] = useState('all');

const locationCategories = [
  { id: 'all', name: 'All customers', count: providerWideCount },
  ...locations.map(loc => ({
    id: loc.id,
    name: loc.name,
    count: locationCounts[loc.id]
  })),
  { id: 'unassigned', name: 'Location not recorded', count: unassignedCount }
];
```

### 4.3 API Call Logic
```typescript
const queryFn = () => {
  if (selectedLocationCategory === 'all') {
    return apiClient.getCustomers({});
  } else if (selectedLocationCategory === 'unassigned') {
    return apiClient.getCustomers({ location_filter: 'unassigned' });
  } else {
    return apiClient.getCustomers({ location_id: selectedLocationCategory });
  }
};
```

### 4.4 Customer Location Badge
Display which locations a customer has visited:
```tsx
<div className="flex gap-1">
  {customer.locationContext.bookingLocations.map(locId => (
    <LocationBadge key={locId} locationId={locId} />
  ))}
  {customer.locationContext.hasNullLocationBookings && (
    <Badge variant="outline">Unassigned</Badge>
  )}
</div>
```

---

## 5. Authorization Considerations

### 5.1 Provider-Wide Users
- Can access all categories
- Can see "All customers"
- Can see each location
- Can see "Location not recorded"

### 5.2 Location-Scoped Users
- **MUST NOT** access "All customers" (middleware blocks this)
- Can access their permitted location(s)
- **QUESTION:** Should they access "Location not recorded"?
  - **Decision:** YES, if they have customers with NULL location bookings
  - **Rationale:** These customers are still their customers, just without location data
  - **Implementation:** Middleware allows this if user has any permitted location

### 5.3 No Locations Configured
- Frontend shows only "All customers"
- "Location not recorded" category hidden (no locations to compare against)
- Provider-wide view is default and only option

---

## 6. Unique Customer Counts

### 6.1 Provider-Wide Count
```sql
SELECT COUNT(DISTINCT customer_id)
FROM customer_providers
WHERE provider_id = ?
```

### 6.2 Location-Specific Count
```sql
SELECT COUNT(DISTINCT customer_id)
FROM bookings
WHERE provider_id = ?
AND location_id = ?
AND status != 'cancelled'
```

### 6.3 Unassigned Count
```sql
SELECT COUNT(DISTINCT customer_id)
FROM bookings
WHERE provider_id = ?
AND location_id IS NULL
AND status != 'cancelled'
```

### 6.4 Overlap Handling
A customer with bookings at multiple locations:
- Appears in each location view
- Counted in each location count
- Counted ONCE in provider-wide total
- Frontend displays "Appears in X locations" badge

---

## 7. Implementation Sequence

### Phase 1: Backend
1. Add `location_filter` parameter support to `CustomerController::index`
2. Implement location-specific filtering (bookings at location)
3. Implement unassigned filtering (bookings with NULL location)
4. Add `locationContext` metadata to customer response
5. Add response metadata (total, scope, location info)
6. Add distinct count queries for each category

### Phase 2: Frontend
1. Add location category selector (tabs/dropdown)
2. Implement category selection logic
3. Update API call to use `location_filter` parameter
4. Add location badge to customer cards
5. Display unique counts per category
6. Handle empty states for each category

### Phase 3: Tests
1. Backend tests for each filtering mode
2. Authorization tests for location-scoped users
3. Frontend tests for category selection
4. Integration tests for end-to-end flow

---

## 8. Edge Cases

### 8.1 Customer with No Bookings
- Appears in "All customers"
- Does not appear in any location view
- Does not appear in "Location not recorded"
- **Rationale:** No booking history means no location association

### 8.2 Customer with Both Real and NULL Location Bookings
- Appears in relevant location view(s)
- Also appears in "Location not recorded"
- Frontend shows badge indicating overlap
- **Rationale:** Both are true - they have location data and missing location data

### 8.3 Provider with One Location
- "All customers" shows all customers
- Location view shows customers with bookings at that location
- "Location not recorded" shows customers with NULL location bookings
- **Rationale:** Still useful to distinguish location vs unassigned

### 8.4 Deleted/Archived Bookings
- **QUESTION:** Should deleted/archived bookings count for location membership?
- **Decision:** NO - only active (non-cancelled) bookings count
- **Rationale:** Consistent with existing booking eligibility rules (`status != 'cancelled'`)

---

## 9. Unresolved Product Decisions

### 9.1 Manual Assignment
- **Status:** Future feature (not in this implementation)
- **Requirement:** Allow owners to manually assign customers to locations
- **Impact:** Would require `customer_locations` table or similar

### 9.2 Location-Scoped Users and "Location not recorded"
- **Decision:** Allow access if they have permitted locations
- **Rationale:** These are still their customers, just without location data

### 9.3 Customer with No Bookings
- **Decision:** Appears in "All customers" only
- **Rationale:** No booking history means no location association

---

## 10. Test Coverage

### 10.1 Backend Tests
1. Provider-wide list includes all provider-associated customers
2. Specific location returns only customers with eligible bookings at that location
3. Customer with bookings at two locations appears in both views
4. Customer with only NULL-location bookings appears in "Location not recorded"
5. Customer with both NULL and real-location bookings appears in both categories
6. Customer without eligible booking history appears in provider-wide list only
7. Unique counts do not double-count customers across locations
8. Providers with no configured locations retain provider-wide visibility
9. Location-scoped users cannot access unauthorized locations
10. Cross-provider customer isolation remains intact

### 10.2 Frontend Tests
1. Location category selector displays correct options based on user role
2. Category selection triggers correct API call
3. Location badges display correctly on customer cards
4. Unique counts display correctly per category
5. Empty states display correctly for each category
6. Search and pagination work with location filtering
7. "Appears in X locations" badge displays correctly

---

## 11. Files to Change

### Backend
- `app/Http/Controllers/Api/V1/CustomerController.php` - Add filtering logic
- `tests/Feature/CustomerLocationFilteringTest.php` - New test file

### Frontend
- `src/app/(business)/customers/page.tsx` - Add location category selector
- `src/lib/api-client.ts` - Add `location_filter` parameter support

---

## 12. Rollback Plan

If issues arise:
1. Revert `CustomerController::index` to current state (provider-wide only)
2. Remove location category selector from frontend
3. Revert API client changes
4. No data changes required (no migrations)

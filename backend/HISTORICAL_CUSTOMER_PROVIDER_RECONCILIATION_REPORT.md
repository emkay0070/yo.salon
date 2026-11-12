# Historical Customer-Provider Reconciliation Report

**Provider ID:** `01a0e836-39fa-700e-991d-797669bb5a32`
**Report Date:** October 9, 2026
**Mode:** READ-ONLY - No database writes, migrations, or deployment performed

---

## Executive Summary

**Finding:** 11 out of 13 customers with bookings for this provider lack the `customer_providers` relationship required for Clients list visibility.

**Impact:** These customers will not appear in the owner's Clients list because the endpoint filters by `Customer::providers()` relationship.

**Recommendation:** A one-time backfill operation using `CustomerService::addSalonRelationship()` can safely restore these relationships idempotently.

**Risk Assessment:** LOW - The method includes existence checks and `ON CONFLICT` clauses to prevent duplicates.

---

## 1. Eligible Customers Identification

### Methodology
- Queried all bookings for the provider
- Identified distinct customer IDs
- Verified each customer exists in the customers table
- Checked for existing `customer_providers` relationships
- Confirmed booking ownership belongs to the target provider

### Results
- **Total customers with bookings:** 13
- **Eligible for relationship creation:** 11
- **Skipped (already exists):** 2

### Skipped Customers
The following customers were skipped because relationships already exist:
- Customer ID: `01a11f1a-4795-71ea-b1b9-f7706f9c5bc1` - Relationship already exists
- Customer ID: `01a11f22-de30-7009-9afc-398cb8d3498b` - Relationship already exists

---

## 2. Relationship Integrity Verification

### Verification Criteria
For each eligible customer, the following checks were performed:
1. Customer exists in customers table
2. Booking belongs to the target provider
3. Relationship is genuinely missing
4. No bookings with other providers (cross-provider check)
5. No existing relationships with other providers (cross-provider check)

### Results
- **Verified safe to create:** 11
- **Ambiguous (requires review):** 0

### Verified Customers (Safe to Create)

All 11 eligible customers passed integrity verification:
- No customers have bookings with other providers
- No customers have relationships with other providers
- All bookings confirmed to belong to the target provider
- Booking status distribution: 7 confirmed, 3 reserved, 1 arrived

### Customer IDs (Redacted Personal Data)
The following customer IDs are eligible for relationship creation:

1. `01a0f68d-6999-7219-96d5-1d7c1c7d33e9` - 1 booking (arrived)
2. `01a0f6f8-a466-7229-9571-bd5c2a8b82fe` - 1 booking (confirmed)
3. `01a10a86-3d78-7151-beac-0acb3b87ba3c` - 1 booking (confirmed)
4. `01a10aeb-4edf-73c9-a14e-a022d4972699` - 1 booking (confirmed)
5. `01a11221-d69e-7016-9db2-a459edb5e8e5` - 1 booking (confirmed)
6. `01a113b5-adca-70b3-b21b-6e0ad248cd8c` - 1 booking (confirmed)
7. `01a11cfb-64ca-73a0-b5c3-d499e44137d5` - 1 booking (confirmed)
8. `01a11cfd-bba2-704f-aaa7-9bff13b826fa` - 1 booking (reserved)
9. `01a11cff-a309-70ff-9307-488e6ea0a8ad` - 1 booking (confirmed)
10. `01a11d23-aaec-72df-9730-ab7f2755190f` - 1 booking (reserved)
11. `01a11d41-1fd2-7102-8963-ade969357ea5` - 1 booking (reserved)

**Note:** Names, phone numbers, and other personal details have been redacted per security requirements.

---

## 3. Schema Constraints

### Table Structure (customer_providers)

**Required Fields:**
- `id` - UUID primary key
- `customer_id` - UUID foreign key
- `provider_id` - UUID foreign key

**Nullable Fields:**
- `visits` - Integer (default: 0)
- `joined_at` - Timestamp
- `created_at` - Timestamp
- `updated_at` - Timestamp

### Unique Constraints
- **Constraint Name:** `customer_providers_customer_id_provider_id_unique`
- **Columns:** `customer_id`, `provider_id`

**Implication:** A customer can only have one relationship with a specific provider. This prevents duplicates and ensures provider isolation.

---

## 4. addSalonRelationship Idempotency Review

### Method Implementation

**File:** `app/Services/CustomerService.php` lines 104-121

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

### Idempotency Analysis

**Existence Check:** ✅ YES
- Method checks if relationship exists before inserting
- Uses `DB::table()->exists()` for reliable check

**ON CONFLICT Clause:** ✅ YES
- PostgreSQL-specific `ON CONFLICT (customer_id, provider_id) DO NOTHING`
- Prevents duplicate rows even if existence check fails
- Aligns with unique constraint on the table

**Overall Idempotency:** ✅ YES
- Safe to call multiple times for same customer-provider pair
- Will not create duplicate relationships
- Will not error if relationship already exists

---

## 5. Proposed Changes

### Dry-Run Summary

**Total INSERT operations:** 11

### Each INSERT Operation Will Include:
- `id`: UUID (generated via `Str::orderedUuid()`)
- `customer_id`: From verified customer record
- `provider_id`: `01a0e836-39fa-700e-991d-797669bb5a32`
- `visits`: 0 (initial value)
- `joined_at`: Current timestamp
- `created_at`: Current timestamp
- `updated_at`: Current timestamp

### Conflict Resolution
- **Mechanism:** `ON CONFLICT (customer_id, provider_id) DO NOTHING`
- **Purpose:** Ensures no duplicate relationships will be created
- **Safety:** Even if the 2 skipped relationships are included, no duplicates will occur

### Number of Relationships to be Created
- **Total:** 11
- **Skipped:** 0 (all 11 verified safe)
- **Ambiguous:** 0 (none require human review)

---

## 6. Ambiguity Requiring Human Review

**Status:** NONE

All 11 eligible customers passed integrity verification with no ambiguity:
- No cross-provider bookings detected
- No cross-provider relationships detected
- All bookings confirmed to belong to target provider
- No location or tenant-scoping violations

---

## 7. Implementation Recommendation

### Recommended Approach

Use `CustomerService::addSalonRelationship()` in a loop for each verified customer:

```php
$customerService = app(\App\Services\CustomerService::class);
$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

$customerIds = [
    '01a0f68d-6999-7219-96d5-1d7c1c7d33e9',
    '01a0f6f8-a466-7229-9571-bd5c2a8b82fe',
    // ... (all 11 customer IDs)
];

foreach ($customerIds as $customerId) {
    $customer = \App\Models\Customer::find($customerId);
    if ($customer) {
        $customerService->addSalonRelationship($customer, $providerId);
    }
}
```

### Safety Guarantees
1. **Idempotent:** Safe to run multiple times
2. **No Duplicates:** Unique constraint + ON CONFLICT clause
3. **Provider Isolation:** Verified no cross-provider data leakage
4. **Transactional:** Can be wrapped in DB transaction if needed

### Alternative: Migration Script

A Laravel migration could be created for this one-time operation:

```php
public function up()
{
    $customerIds = [
        '01a0f68d-6999-7219-96d5-1d7c1c7d33e9',
        // ... (all 11 customer IDs)
    ];

    $providerId = '01a0e836-39fa-700e-991d-797669bb5a32';
    $customerService = app(\App\Services\CustomerService::class);

    foreach ($customerIds as $customerId) {
        $customer = \App\Models\Customer::find($customerId);
        if ($customer) {
            $customerService->addSalonRelationship($customer, $providerId);
        }
    }
}
```

---

## 8. Post-Reconciliation Verification

### After executing the backfill, verify:

1. **Database State:**
   - Query `customer_providers` table for the provider
   - Confirm count increased from 2 to 13
   - Verify no duplicate relationships exist

2. **API Endpoint:**
   - Call Clients endpoint for the provider
   - Confirm all 13 customers now appear
   - Verify no customers from other providers appear

3. **Application Behavior:**
   - Log in as owner/provider
   - Navigate to Clients list
   - Confirm historical customers are visible
   - Verify no cross-provider leakage

---

## 9. Conclusion

**Summary:**
- 11 customers require `customer_providers` relationship restoration
- All 11 verified safe with no ambiguity
- `CustomerService::addSalonRelationship()` is idempotent and safe
- Unique constraint and ON CONFLICT clause prevent duplicates
- No cross-provider data leakage risk

**Recommendation:**
Proceed with backfill operation using `CustomerService::addSalonRelationship()` for the 11 verified customer IDs. The operation is safe, idempotent, and will restore Clients list visibility for historical customers.

**Next Steps:**
1. Obtain approval for backfill operation
2. Execute backfill using recommended approach
3. Verify database state and API endpoint
4. Perform manual smoke test in application
5. Deploy to staging for final verification

**Do NOT:**
- Deploy to production without staging verification
- Backfill relationships for other providers without similar analysis
- Modify customer records or booking data
- Run against production without approval

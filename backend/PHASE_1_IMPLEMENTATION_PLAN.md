# Phase 1 Implementation Plan: Booking Invariants, Buffer Consistency & Concurrency Protection

## 1. Selected Locking Strategy

### Strategy: PostgreSQL Transaction-Scoped Advisory Locks

**Why PostgreSQL Advisory Locks:**
- **Transaction-scoped**: Locks automatically release on transaction commit/rollback
- **No silent expiry**: Lock remains valid until transaction ends (unlike cache locks)
- **Database-backed**: Works across all application instances without external cache dependency
- **Deterministic**: Lock keys are 64-bit integers derived from resource identity
- **PostgreSQL 16.9**: Verified support for advisory lock functions

### Lock Key Derivation Strategy

**Do NOT use CRC32** - collision risk too high (1 in 4 billion).

**Strategy: Two-part lock key using PostgreSQL's two-argument advisory lock interface**

PostgreSQL provides two advisory lock functions:
- `pg_advisory_xact_lock(key1, key2)` - two 64-bit integers
- This allows us to encode resource identity without collision risk

**Lock Key Encoding:**

```php
function deriveLockKey($providerId, $locationId, $specialistId, $resourceId = null) {
    // Use UUID's first 8 bytes as 64-bit integer
    // PostgreSQL UUIDs are stored as 128-bit values
    // We can extract the first 64 bits for lock key 1
    
    if ($specialistId) {
        // Specialist-assigned booking
        // key1: specialist_id (first 64 bits of UUID)
        // key2: provider_id (first 64 bits of UUID)
        $key1 = hexdec(substr($specialistId, 0, 16));
        $key2 = hexdec(substr($providerId, 0, 16));
    } else {
        // Provider-level booking (no specialist)
        // key1: provider_id (first 64 bits of UUID)
        // key2: 0 (indicator for provider-level)
        $key1 = hexdec(substr($providerId, 0, 16));
        $key2 = 0;
    }
    
    return [$key1, $key2];
}
```

**Resource Lock Key:**
```php
function deriveResourceLockKey($resourceId) {
    // For physical resources (rooms, equipment)
    // key1: resource_id (first 64 bits of UUID)
    // key2: 1 (indicator for resource lock)
    $key1 = hexdec(substr($resourceId, 0, 16));
    $key2 = 1;
    
    return [$key1, $key2];
}
```

**Collision Considerations:**
- UUID first 64 bits have 1 in 2^64 collision probability (negligible)
- Different resource types use different key2 values to prevent cross-type conflicts
- Specialist locks use key2 = provider_id, resource locks use key2 = 1
- Provider-level bookings use key2 = 0

**Lock Acquisition:**
```php
DB::statement("SELECT pg_advisory_xact_lock(?, ?)", [$key1, $key2]);
```

### Proof That Conflicting Bookings Share Locks

**Case 1: Same Specialist, Same Date, Different Times**
- Booking A: Specialist X, 2026-10-15, 10:00
- Booking B: Specialist X, 2026-10-15, 10:15
- Lock key: [specialist_id, provider_id] - **SAME LOCK**
- Both bookings acquire same lock → serialized

**Case 2: Same Specialist, Different Dates, Overlapping Intervals**
- Booking A: Specialist X, 2026-10-15 23:45-00:30 (crosses midnight)
- Booking B: Specialist X, 2026-10-16 00:00-00:45
- Lock key: [specialist_id, provider_id] - **SAME LOCK**
- Both bookings acquire same lock → serialized
- Overlap check inside transaction detects cross-date overlap

**Case 3: Specialist Working Across Multiple Locations**
- Booking A: Specialist X, Location 1, 2026-10-15, 10:00
- Booking B: Specialist X, Location 2, 2026-10-15, 10:15
- Lock key: [specialist_id, provider_id] - **SAME LOCK**
- Both bookings acquire same lock → serialized
- **Correct**: A specialist cannot be in two places at once

**Case 4: Provider-Level Booking vs Specialist-Assigned Booking**
- Booking A: No specialist, Provider Y, 2026-10-15, 10:00
- Booking B: Specialist X, Provider Y, 2026-10-15, 10:15
- Lock key A: [provider_id, 0]
- Lock key B: [specialist_id, provider_id]
- **DIFFERENT LOCKS** - **INTENTIONAL**
- Provider-level bookings are for services without specialist assignment
- They do NOT conflict with specialist-assigned bookings
- This is correct business semantics

**Case 5: Shared Physical Resources**
- Booking A: Room 1, 2026-10-15, 10:00
- Booking B: Room 1, 2026-10-15, 10:15
- Resource lock key: [resource_id, 1] - **SAME LOCK**
- Both bookings acquire same resource lock → serialized

**Case 6: Multiple Resources Required**
- Booking A: Requires Room 1 and Equipment 2
- Booking B: Requires Room 1 and Equipment 2
- Locks acquired in deterministic order:
  1. Specialist lock [specialist_id, provider_id]
  2. Resource lock [room_1_id, 1]
  3. Resource lock [equipment_2_id, 1] (sorted by resource_id)
- Both bookings acquire same locks in same order → serialized

**Case 7: Including Date in Lock Key - NOT DONE**
- **Decision**: Date is NOT included in lock key
- **Reasoning**: A specialist cannot be in two places at once regardless of date
- **Cross-date handling**: Lock acquired once, overlap check handles cross-date intervals
- **Benefit**: Simpler lock key, no date boundary issues

**Case 8: Including Location in Lock Key - NOT DONE**
- **Decision**: Location is NOT included in specialist lock key
- **Reasoning**: A specialist working across locations cannot be in two places at once
- **Location scoping**: Handled in overlap check (filter by location_id)
- **Benefit**: Correctly prevents cross-location double-booking of same specialist

### Lock Acquisition Order

**Deterministic Global Order:**
1. Specialist lock (if specialist assigned) OR Provider lock (if no specialist)
2. Resource locks sorted by resource_id ASC

**Why This Order:**
- Specialist is always the primary scheduling resource
- Resources are secondary, sorted by ID to prevent deadlock
- Consistent across all booking paths

### Transaction-Scoped Locks

**Acquisition:**
```php
DB::beginTransaction();
try {
    // Acquire locks
    DB::statement("SELECT pg_advisory_xact_lock(?, ?)", [$key1, $key2]);
    
    // Protected critical section
    // - Availability check
    // - Overlap detection
    // - Booking creation
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
// Locks automatically released on commit/rollback
```

**Same Database Connection:**
- Advisory locks are connection-scoped
- All locks acquired on the same connection as the transaction
- Locks automatically released when transaction ends

## 2. Canonical Resource Identity

### Primary Scheduling Resource: Specialist

**Canonical Resource Hierarchy:**
1. **Specialist** (specialist_id) - Primary scheduling resource
   - Represents the professional identity
   - Used for availability checking
   - Used for conflict detection

2. **Staff** (staff_id) - Operational representation
   - Represents the salon-local employee record
   - Derived from SpecialistAssignment
   - NOT used for conflict detection (specialist_id is canonical)

3. **Resources** (resource_id) - Physical resources
   - Equipment, rooms, tools
   - Optional per service
   - Secondary conflict check via ResourceAllocator

4. **Location** (location_id) - Scoping context
   - Physical location
   - Used for scoping availability
   - Included in lock key for multi-location providers

5. **Provider** (provider_id) - Business context
   - Business owner
   - Fallback for provider-level bookings (no specialist)
   - Included in lock key

**Resource Identity Resolution:**
```php
function getCanonicalResourceIdentity($providerId, $locationId, $specialistId, $date) {
    if ($specialistId) {
        return [
            'type' => 'specialist',
            'id' => $specialistId,
            'lock_key' => crc32("{$providerId}_{$locationId}_{$specialistId}_{$date}")
        ];
    } else {
        return [
            'type' => 'provider',
            'id' => $providerId,
            'lock_key' => crc32("{$providerId}_{$locationId}_provider_{$date}")
        ];
    }
}
```

**Multi-Resource Locking Rules:**
1. Acquire specialist lock first (always)
2. If resources required, acquire resource locks sorted by resource_id
3. All locks acquired before availability check
4. All locks released together on transaction end

**Deterministic Lock Ordering:**
- Specialist lock always first
- Resource locks sorted by resource_id ASC
- Prevents deadlock when multiple resources involved

## 3. Service Interval vs Occupied Interval Distinction

### Definitions

**Service Interval (BookingService.start_time, BookingService.end_time):**
- **Definition**: Actual time the service is performed
- **Calculation**: `start_time + service.duration`
- **Semantics**: What the customer actually receives
- **Consumers**: SessionService, reporting, history, customer notifications
- **Buffer Handling**: Does NOT include buffers
- **Preservation**: These timestamps MUST NOT be changed

**Occupied Interval (for conflict detection only):**
- **Definition**: Total time slot reserved including buffers
- **Calculation**: `service_start - buffer_before` to `service_end + buffer_after`
- **Semantics**: What blocks other bookings
- **Consumers**: AvailabilityEngine, conflict detection, overlap checking ONLY
- **Buffer Handling**: Includes service-level buffers and location overrides
- **Storage**: NOT stored in database, calculated on-the-fly

### Buffer Resolution Logic

```php
function getBufferBefore($service, $locationServiceOverride) {
    return $locationServiceOverride?->buffer_before_override
        ?? $service->buffer_before
        ?? 0;
}

function getBufferAfter($service, $locationServiceOverride) {
    return $locationServiceOverride?->buffer_after_override
        ?? $service->buffer_after
        ?? 0;
}

function calculateOccupiedInterval($serviceStart, $serviceEnd, $service, $locationServiceOverride) {
    $bufferBefore = getBufferBefore($service, $locationServiceOverride);
    $bufferAfter = getBufferAfter($service, $locationServiceOverride);
    
    $occupiedStart = $serviceStart->copy()->subMinutes($bufferBefore);
    $occupiedEnd = $serviceEnd->copy()->addMinutes($bufferAfter);
    
    return ['start' => $occupiedStart, 'end' => $occupiedEnd];
}
```

### Contradiction Resolution

**Previous Plan Issue:**
- Proposed to include buffers in persisted BookingService.end_time
- This contradicts the definition that BookingService represents service duration only

**Corrected Approach:**
- **DO NOT** change BookingService.start_time or BookingService.end_time
- **DO NOT** include buffers in persisted timestamps
- **DO** calculate occupied interval on-the-fly for conflict detection
- **DO** preserve existing service interval semantics

### No Schema Change Required

**Reasoning:**
- `BookingService.start_time` and `BookingService.end_time` currently represent service duration only
- SessionService, reporting, and history depend on this semantics
- Buffers are calculated at application level for conflict detection only
- No need to store occupied interval in database
- Existing BookingService records are correct as-is (service duration)

**Backfill Not Required:**
- Existing BookingService records represent service duration (correct)
- No data migration needed
- Only conflict detection logic changes to calculate occupied interval

## 4. Authoritative Statuses That Reserve Time

### Status Investigation

**Current Usage Across Codebase:**

| Location | Statuses Checked |
|----------|------------------|
| BookingOrchestrator::reserveAndBook() | `confirmed`, `pending`, `reserved` |
| AvailabilityEngine::getSchedule() | `confirmed`, `pending` |
| BookingService::validateBookingTime() | `pending`, `confirmed` |
| CustomerAvailabilityService::getAvailableSlots() | `confirmed`, `pending` |
| BookingService::transitionStatus() state machine | Includes `pending_payment`, `reserved` |

### Discrepancy Resolution

**Evidence from BookingService::transitionStatus() (lines 546-565):**
```php
// Reservation layer (managed by booking system):
//   pending         → confirmed | cancelled
//   pending_payment → confirmed | cancelled
//   reserved        → pending | pending_payment | confirmed | cancelled
```

**Evidence from BookingOrchestrator::reserveAndBook() (lines 212-217):**
```php
if (empty($validatedData['internal_booking'])) {
    $validatedData['slot_locked_until'] = CarbonImmutable::now()->addMinutes(10);
    $validatedData['status'] = 'reserved';
} else {
    $validatedData['status'] = 'confirmed';
}
```

**Evidence from BookingService::createBookingWithAccount() (lines 323-326):**
```php
if ($requiresDeposit) {
    $bookingStatus = 'pending_payment';
} else {
    $bookingStatus = $this->determineInitialBookingStatus(...);
}
```

### Lifecycle-Aware Reservation Rules

**Authoritative Statuses That Reserve Time:**
- `confirmed` - Confirmed appointment, reserves time
- `pending` - Manual approval pending, reserves time
- `reserved` - Temporary hold for payment, reserves time (has slot_locked_until)
- `pending_payment` - Deposit pending, reserves time

**Do NOT Reserve Time:**
- `cancelled` - Cancelled, time released
- `no_show` - Customer no-show, time released
- `completed` - Service completed, time released
- `rescheduled` - Booking rescheduled, original time released
- `arrived`, `waiting`, `in_progress` - Operational states, time already reserved

### Handling Expired Temporary Holds

**Critical Requirement:**
A `reserved` booking with an elapsed `slot_locked_until` must NOT automatically block availability forever.

**Reservation Predicate:**
```php
function bookingReservesTime($booking) {
    // Check status
    if (!in_array($booking->status, ['confirmed', 'pending', 'reserved', 'pending_payment'])) {
        return false;
    }
    
    // For reserved status, check if hold is still valid
    if ($booking->status === 'reserved') {
        if ($booking->slot_locked_until && $booking->slot_locked_until < now()) {
            return false; // Expired hold, does not reserve time
        }
    }
    
    return true;
}
```

**Overlap Query with Lifecycle Awareness:**
```sql
SELECT COUNT(*) FROM bookings b
LEFT JOIN booking_service bs ON b.id = bs.booking_id
WHERE b.specialist_id = $specialistId
  AND b.location_id = $locationId
  AND b.id != $currentBookingId
  AND b.status IN ('confirmed', 'pending', 'reserved', 'pending_payment')
  AND (
    b.status != 'reserved' OR b.slot_locked_until IS NULL OR b.slot_locked_until > NOW()
  )
  AND (
    (bs.start_time < $newOccupiedEnd AND bs.end_time > $newOccupiedStart)
    OR
    -- For legacy bookings without BookingService records
    (b.date = $date AND b.time = $time)
  )
```

**Preservation of Existing Rules:**
- Payment hold and expiry rules remain unchanged
- No payment grace implementation in Phase 1
- No payment callback changes in Phase 1
- No new expiry behavior in Phase 1

## 5. Precise Files to Be Changed

### 1. app/Services/BookingOrchestrator.php

**Changes:**
- Replace cache lock with PostgreSQL advisory lock
- Update double booking check to use occupied interval overlap instead of exact time
- Update status check to include `pending_payment` and lifecycle-aware `reserved` check
- Add occupied interval calculation for conflict detection (NOT persisted)
- Add resource lock acquisition if resources required
- **DO NOT** change `calculateEndTime()` - it's not used for BookingService records

**Reason:**
- Primary booking creation path for staff/admin and customer portal
- Current lock is insufficient (exact time only)
- Status check incomplete
- Need lifecycle-aware reservation predicate

### 2. app/Services/BookingService.php

**Changes:**
- **DO NOT** change BookingService.end_time calculation (preserve service duration semantics)
- Add PostgreSQL advisory lock before availability check
- Add occupied interval overlap check inside transaction (NOT persisted)
- Update `validateBookingTime()` to include `reserved`, `pending_payment` statuses with lifecycle-aware check
- Fix `rescheduleBooking()` to secure new interval before cancelling old
- Add occupied interval calculation for conflict detection (on-the-fly only)

**Reason:**
- Called by BookingOrchestrator for final booking creation
- No locking in current implementation
- Rescheduling unsafe (cancels old before securing new)
- Status check incomplete
- Need lifecycle-aware reservation predicate

### 3. app/Services/CustomerBookingService.php

**Changes:**
- Add PostgreSQL advisory lock before availability check
- Add occupied interval overlap check inside transaction (NOT persisted)
- **DO NOT** create BookingService records indiscriminately
- Update overlap check to handle legacy bookings without BookingService records
- Fix `rescheduleBooking()` to secure new interval before cancelling old
- Update status check to include `reserved`, `pending_payment` with lifecycle-aware check

**Reason:**
- Legacy customer portal path has NO locking currently
- Does NOT create BookingService records (legacy behavior)
- No interval overlap check
- Rescheduling unsafe
- Status check incomplete
- Must handle legacy records in conflict detection

### 4. app/Services/AvailabilityEngine.php

**Changes:**
- Update overlap check to include `reserved`, `pending_payment` statuses with lifecycle-aware check
- Ensure buffer consistency across all methods
- Centralize occupied interval calculation logic (on-the-fly only)
- Update overlap query to handle legacy bookings without BookingService records
- **DO NOT** change `calculateEndTime()` - it's used for slot generation only

**Reason:**
- Used by availability checking across all paths
- Status check incomplete (missing `reserved`, `pending_payment`)
- Buffer calculation already correct here
- Should be source of truth for occupied interval calculation
- Must handle legacy records

### 5. app/Http/Controllers/Api/V1/BookingController.php

**Changes:**
- Add PostgreSQL advisory lock to `update()` method
- Add occupied interval overlap check before changing date/time (NOT persisted)
- Update status check to include `reserved`, `pending_payment` with lifecycle-aware check
- Update overlap query to handle legacy bookings without BookingService records

**Reason:**
- Administrative update path can change date/time without any check
- No locking currently
- No availability check currently
- Critical vulnerability
- Must handle legacy records

### 6. app/Services/BookingService.php (resolveAsRescheduled)

**Changes:**
- Remove `internal_booking = true` bypass
- Add PostgreSQL advisory lock
- Add occupied interval overlap check (NOT persisted)
- Update status check to include `reserved`, `pending_payment` with lifecycle-aware check
- Update overlap query to handle legacy bookings without BookingService records

**Reason:**
- Currently bypasses availability check entirely
- No locking
- Critical vulnerability
- Must handle legacy records

### 7. app/Services/ResourceAllocator.php

**Changes:**
- Add PostgreSQL advisory lock for each resource
- Use deterministic lock ordering (resource_id ASC)
- Update overlap check to include `reserved`, `pending_payment` statuses with lifecycle-aware check
- Update overlap query to handle legacy bookings without BookingService records

**Reason:**
- Resource allocations need protection
- Current check uses Resource.turnaround_time but no locking
- Multi-resource scenarios need deterministic ordering
- Must handle legacy records

## 6. PostgreSQL Concurrency Tests

### Test File: tests/Feature/Phase1ConcurrencyTest.php

### Test Harness Strategy

**Do NOT use Swoole** - may not be installed.
**Do NOT assume DB::connection produces independent connections** - Laravel may pool connections.

**Strategy: Use PHP's pcntl_fork() for genuine concurrent processes**

Each forked process will:
1. Establish its own database connection
2. Start its own transaction
3. Attempt to create a booking
4. Commit or rollback
5. Exit

The parent process will:
1. Fork multiple child processes
2. Wait for all children to complete
3. Query the final database state
4. Verify at most one conflicting booking succeeded

**Test Implementation Details:**

```php
public function test_concurrent_bookings_with_forked_processes() {
    // Setup: Create provider, location, specialist, service
    $provider = Provider::factory()->create();
    $location = Location::factory()->create(['provider_id' => $provider->id]);
    $specialist = Specialist::factory()->create();
    $assignment = SpecialistAssignment::factory()->create([
        'specialist_id' => $specialist->id,
        'provider_id' => $provider->id,
        'location_id' => $location->id,
    ]);
    $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

    $testDate = CarbonImmutable::now()->addDays(1)->toDateString();
    $testTime = '10:00';

    // Fork 2 processes to create concurrent bookings
    $pids = [];
    for ($i = 0; $i < 2; $i++) {
        $pid = pcntl_fork();

        if ($pid == -1) {
            $this->fail('Could not fork process');
        } else if ($pid) {
            // Parent process
            $pids[] = $pid;
        } else {
            // Child process - establish new DB connection
            $childConnection = DB::connection('pgsql')->getPdo();
            $childConnection->beginTransaction();

            try {
                // Attempt to create booking
                $booking = Booking::create([
                    'id' => (string) Str::uuid(),
                    'provider_id' => $provider->id,
                    'location_id' => $location->id,
                    'specialist_id' => $specialist->id,
                    'service_id' => $service->id,
                    'date' => $testDate,
                    'time' => $testTime,
                    'status' => 'confirmed',
                    'customer_id' => null,
                ]);

                $childConnection->commit();
                exit(0); // Success
            } catch (\Exception $e) {
                $childConnection->rollBack();
                exit(1); // Failure
            }
        }
    }

    // Wait for all children to complete
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    // Verify final state
    $finalBookings = Booking::where('specialist_id', $specialistId)
        ->where('date', $testDate)
        ->where('time', $testTime)
        ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
        ->get();

    // At most one should have succeeded
    $this->assertLessThanOrEqual(1, $finalBookings->count());
}
```

**Fallback Strategy if pcntl_fork unavailable:**
- Use sequential booking attempts with manual sleep
- Not truly concurrent but can test lock acquisition
- Document limitation in test comments

### Test Scenarios

**Scenario A: Identical Intervals**
- Create two concurrent bookings for same specialist, date, time
- Use forked processes with independent connections
- Verify at most one succeeds
- Verify final persisted state

**Scenario B: Partially Overlapping Intervals**
- Create booking A: 10:00-10:30 (service duration 30)
- Create booking B: 10:15-10:45 (service duration 30)
- Same specialist, same date
- Verify at most one succeeds

**Scenario C: Buffer-Only Conflicts**
- Service A: 10:00-10:30, buffer_after: 10 minutes → occupied until 10:40
- Service B: 10:35-11:05, buffer_before: 5 minutes → occupied from 10:30
- Overlap: 10:30-10:40 (buffer overlap only)
- Verify conflict detected

**Scenario D: Different Entry Points**
- Path 1: BookingController::store() (staff/admin)
- Path 2: CustomerBookingController::store() (legacy)
- Same specialist, date, time
- Verify both paths enforce same invariant

**Scenario E: Rescheduling Against Concurrent Booking**
- Create booking A: 10:00-10:30
- Concurrently reschedule booking B to 10:15-10:45
- Verify rescheduling fails if interval occupied
- Verify original booking preserved on failure

**Scenario F: Multiple Resource Allocations**
- Service requires 2 resources of same type
- Create two concurrent bookings requiring same resources
- Verify resource conflict detected
- Verify deterministic lock ordering prevents deadlock

**Scenario G: Transaction Failure**
- Acquire lock, then cause transaction failure
- Verify lock released
- Verify subsequent booking can succeed

**Scenario H: Independent Resources**
- Create booking for specialist 1: 10:00-10:30
- Create booking for specialist 2: 10:00-10:30
- Verify both succeed (different resources)

**Scenario I: Date Boundary Crossing**
- Create booking: 23:45-00:30 (crosses midnight)
- Verify lock acquired on specialist (not date-specific)
- Verify overlap check handles cross-date interval

**Scenario J: Cross-Location Specialist**
- Specialist works at Location 1 and Location 2
- Create booking at Location 1: 10:00-10:30
- Concurrently create booking at Location 2: 10:15-10:45
- Verify conflict detected (same specialist cannot be in two places)

**Scenario K: Expired Reserved Booking**
- Create booking with status='reserved' and expired slot_locked_until
- Create new booking for same interval
- Verify new booking succeeds (expired hold does not block)

### Final State Verification

For each scenario, verify:
```php
$finalBookings = Booking::where('specialist_id', $specialistId)
    ->where('date', $date)
    ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
    ->where(function($q) {
        $q->where('status', '!=', 'reserved')
          ->orWhereNull('slot_locked_until')
          ->orWhere('slot_locked_until', '>', now());
    })
    ->get();

// Verify no overlapping occupied intervals
```

## 7. Unresolved Questions and Migration Risks

### Unresolved Questions

**None** - All design decisions resolved based on code evidence.

### Migration Risks

**No Schema Migration Required**
- No database schema changes needed
- No backfill of existing data needed
- BookingService.start_time/end_time semantics preserved (service duration only)
- Only application logic changes

**Legacy BookingService Records**
- Legacy customer portal path does NOT create BookingService records
- Overlap query must handle both:
  - Bookings with BookingService records (new paths)
  - Bookings without BookingService records (legacy path)
- No backfill required - legacy records remain as-is
- Conflict detection uses LEFT JOIN to handle both cases

### Deployment Risks

**Risk 1: Advisory Lock Performance**
- Advisory locks are lightweight but add database round-trip
- **Mitigation**: Monitor lock wait times in production
- **Rollback**: Can revert to cache locks if performance issues

**Risk 2: Lock Key Collisions**
- UUID first 64 bits have 1 in 2^64 collision probability (negligible)
- **Mitigation**: If collisions observed, use full UUID hash with different key2 values
- **Rollback**: Switch to stronger encoding if needed

**Risk 3: Legacy Path Changes**
- CustomerBookingService changes may break existing integrations
- **Mitigation**: Run existing customer portal tests before deployment
- **Rollback**: Can revert legacy path changes if issues

**Risk 4: Rescheduling Behavior Change**
- Securing new first may change user experience
- **Mitigation**: Test rescheduling with conflicts
- **Rollback**: Can revert rescheduling logic if issues

**Risk 5: pcntl_fork Availability**
- Test harness uses pcntl_fork() for genuine concurrency
- **Mitigation**: If pcntl not available, use sequential fallback with documented limitation
- **Rollback**: Not applicable - test limitation only

### Known Limitations

**Limitation 1: Resource-Level Locking**
- ResourceAllocator uses Resource.turnaround_time for buffers
- This is separate from service-level buffers
- **Impact**: Resource conflicts may not perfectly align with service conflicts
- **Mitigation**: Document this limitation, address in future phase

**Limitation 2: Provider-Level Bookings**
- When no specialist assigned, lock is provider-level
- All provider-level bookings compete for same lock
- **Impact**: May reduce concurrency for independent specialists
- **Mitigation**: This is correct behavior (provider-level booking)

**Limitation 3: Cross-Date Bookings**
- Lock acquired on specialist (not date-specific)
- Overlap check handles cross-date intervals
- **Impact**: Booking starting on day 1 and ending on day 2 locks specialist for entire duration
- **Mitigation**: This is correct behavior (specialist cannot be in two places)

**Limitation 4: Cross-Location Specialist**
- Lock is specialist-level (not location-specific)
- Specialist working across locations cannot be double-booked
- **Impact**: Correctly prevents cross-location double-booking
- **Mitigation**: This is correct business semantics

**Limitation 5: Legacy BookingService Records**
- Legacy bookings without BookingService records use exact time matching
- New bookings use occupied interval overlap
- **Impact**: Legacy and new bookings have different conflict granularity
- **Mitigation**: Acceptable for Phase 1; address in future phase if needed

## 8. Implementation Sequence

### Phase 1A: Core Infrastructure
1. Create `BookingConflictService` with:
   - Lock key derivation functions
   - Occupied interval calculation functions
   - Lifecycle-aware reservation predicate
   - Overlap detection query (handles legacy records)

### Phase 1B: Booking Creation Paths
2. Update `BookingOrchestrator.php`:
   - Replace cache lock with advisory lock
   - Add occupied interval overlap check
   - Update status check with lifecycle-aware predicate

3. Update `BookingService.php`:
   - Add advisory lock to `createGuestBooking()`
   - Add advisory lock to `createBookingWithAccount()`
   - Add occupied interval overlap check
   - Fix `rescheduleBooking()` to secure new first

4. Update `CustomerBookingService.php`:
   - Add advisory lock
   - Add occupied interval overlap check (handles legacy records)
   - Fix `rescheduleBooking()` to secure new first

### Phase 1C: Modification Paths
5. Update `BookingController::update()`:
   - Add advisory lock
   - Add occupied interval overlap check

6. Update `BookingService::resolveAsRescheduled()`:
   - Remove `internal_booking = true` bypass
   - Add advisory lock
   - Add occupied interval overlap check

### Phase 1D: Resource Protection
7. Update `ResourceAllocator.php`:
   - Add resource advisory locks
   - Deterministic lock ordering

### Phase 1E: Availability Engine
8. Update `AvailabilityEngine.php`:
   - Update status check with lifecycle-aware predicate
   - Update overlap query to handle legacy records
   - Centralize occupied interval calculation

### Phase 1F: Testing
9. Create `Phase1ConcurrencyTest.php`:
   - Implement pcntl_fork test harness
   - Implement all test scenarios
   - Verify final database state

10. Run regression tests:
    - Existing booking tests
    - Existing availability tests
    - Existing rescheduling tests
    - Session lifecycle tests

## Next Steps

**Await Review** of this revised implementation plan before proceeding with Part B implementation.

**Approval Required For:**
1. Final lock identity (UUID-based two-part key) and proof that conflicting requests share protection
2. Cross-date and cross-location locking strategy (specialist-level lock, overlap check handles boundaries)
3. Occupied-interval calculation without changing service timestamp semantics (on-the-fly calculation only)
4. Lifecycle-aware reservation predicate (expired `reserved` bookings do not block)
5. Legacy booking participation in conflict detection (LEFT JOIN handles both cases)
6. PostgreSQL concurrency harness (pcntl_fork with fallback to sequential)

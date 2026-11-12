# Phase 1 Audit Report: Booking Invariants, Buffer Consistency & Concurrency Protection

## Part A: Read-Only Audit

### 1. Booking Creation and Modification Paths

#### Booking Creation Paths (4 identified)

**Path 1: Staff/Admin Bookings**
- **Controller**: `BookingController::store()` (line 115)
- **Service**: `BookingOrchestrator::reserveAndBook()` (line 44)
- **Final Service**: `BookingService::createBookingWithAccount()` (line 274)
- **Transaction**: `DB::transaction` in `BookingOrchestrator::reserveAndBook()` (line 193)
- **Availability Check**: `BookingOrchestrator` validates bookability, double booking policy, slot availability (lines 105-179)
- **Locking**: Cache lock on exact time: `booking_slot_{$assignment->id}_{$date}_{$time}` (line 184-186)
- **Buffer Calculation**: `BookingService::createBookingWithAccount()` line 374: `$endTime = $startTime->copy()->addMinutes($service->duration)` - **BUFFERS NOT INCLUDED**
- **Status Logic**: External → `reserved` with 10-minute slot lock, Internal walk-ins → `confirmed`
- **Test Coverage**: `ConcurrentBookingConflictTest.php` exists but tests exact time only

**Path 2: Customer Portal (Journey 4)**
- **Controller**: `BookingController::storeWithAccount()` (line 534)
- **Service**: `BookingOrchestrator::reserveAndBook()` (line 44)
- **Final Service**: `BookingService::createBookingWithAccount()` (line 274)
- **Transaction**: Same as Path 1
- **Availability Check**: Same as Path 1
- **Locking**: Same as Path 1
- **Buffer Calculation**: Same as Path 1 - **BUFFERS NOT INCLUDED**
- **Status Logic**: Deposit required → `pending_payment`, No deposit → approval policy
- **Test Coverage**: Same as Path 1

**Path 3: Customer Portal (Legacy)**
- **Controller**: `CustomerBookingController::store()` (line 176)
- **Service**: `CustomerBookingService::createBooking()` (line 29)
- **Transaction**: `DB::transaction` in `CustomerBookingService::createBooking()` (line 31)
- **Availability Check**: `CustomerBookingService::validateBooking()` (line 127) calls `CustomerAvailabilityService::getAvailableSlots()` (line 145)
- **Locking**: **NO LOCKING**
- **Buffer Calculation**: Does NOT create `BookingService` records - only creates `Booking` record with `date` and `time`
- **Status Logic**: `determineInitialBookingStatus()` based on approval policy (line 70)
- **Test Coverage**: No specific test for this path
- **Gap**: Deposit policy NOT integrated

**Path 4: Booking Orchestrator Direct**
- **Controller**: `BookingOrchestratorController::reserve()` (line 22)
- **Service**: `BookingOrchestrator::reserveAndBook()` (line 44)
- **Final Service**: `BookingService::createBookingWithAccount()` (line 274)
- **Transaction**: Same as Path 1
- **Availability Check**: Same as Path 1
- **Locking**: Same as Path 1
- **Buffer Calculation**: Same as Path 1 - **BUFFERS NOT INCLUDED**
- **Status Logic**: Same as Path 1
- **Test Coverage**: Same as Path 1

#### Booking Modification Paths (4 identified)

**Path 1: Admin Update**
- **Controller**: `BookingController::update()` (line 265)
- **Service**: Direct field update via `$booking->update()` (line 334)
- **Transaction**: **NO TRANSACTION**
- **Availability Check**: **NO AVAILABILITY CHECK**
- **Locking**: **NO LOCKING**
- **Buffer Calculation**: Not applicable (direct field update)
- **Risk**: Can change date/time without any availability or conflict check

**Path 2: Customer Reschedule**
- **Controller**: `CustomerBookingController::reschedule()` (line 299)
- **Service**: `BookingService::rescheduleBooking()` (line 700+)
- **Transaction**: `DB::transaction` in `CustomerBookingService::rescheduleBooking()` (line 245)
- **Availability Check**: **NO AVAILABILITY CHECK** before cancellation
- **Locking**: **NO LOCKING**
- **Buffer Calculation**: Not applicable (reuses existing service)
- **Safety Issue**: Cancels old booking BEFORE securing new interval (line 249)
- **Test Coverage**: No specific test for concurrent rescheduling

**Path 3: Resolution Reschedule**
- **Controller**: `BookingController::resolve()` (line 346)
- **Service**: `BookingService::resolveAsRescheduled()` (line 814)
- **Transaction**: `DB::transaction` (line 827)
- **Availability Check**: **BYPASSED** via `internal_booking = true` (line 860)
- **Locking**: **NO LOCKING**
- **Buffer Calculation**: Not applicable (reuses existing service)
- **Safety Issue**: Bypasses availability validation entirely

**Path 4: Floor Operations**
- **Controller**: `FloorController::arrive()`, `wait()`, `startSession()`, `completeSession()`
- **Service**: `BookingService::transitionStatus()`, `SessionService::startSession()`, `SessionService::completeSession()`
- **Transaction**: Depends on service method
- **Availability Check**: Not applicable (operational floor, not scheduling)
- **Locking**: Not applicable
- **Buffer Calculation**: Not applicable
- **Impact**: Does NOT change scheduled intervals - only operational state

### 2. Canonical Booking Invariants

#### Current Interval Calculation

**Buffer Sources:**
- `Service.buffer_before` (default 0)
- `Service.buffer_after` (default 0)
- `location_service.buffer_before_override` (optional override)
- `location_service.buffer_after_override` (optional override)

**Buffer Resolution Logic** (from `BookingPolicyResolver.php` lines 53-65):
```php
public function bufferBefore(): int
{
    return $this->resolvedService?->pivot_buffer_before_override
        ?? $this->service->buffer_before
        ?? 0;
}

public function bufferAfter(): int
{
    return $this->resolvedService?->pivot_buffer_after_override
        ?? $this->service->buffer_after
        ?? 0;
}
```

**Inconsistent End Time Calculations:**

1. **AvailabilityEngine::calculateEndTime()** (line 839):
   ```php
   $end = $start->addMinutes($service->duration + $service->buffer_before + $service->buffer_after);
   ```
   - **INCLUDES BUFFERS** ✓

2. **BookingService::createGuestBooking()** (line 231):
   ```php
   $endTime = $startTime->copy()->addMinutes($service->duration);
   ```
   - **EXCLUDES BUFFERS** ✗

3. **BookingService::createBookingWithAccount()** (line 374):
   ```php
   $endTime = $startTime->copy()->addMinutes($service->duration);
   ```
   - **EXCLUDES BUFFERS** ✗

4. **BookingOrchestrator::calculateEndTime()** (line 240):
   ```php
   $end = $start->addMinutes($service->duration);
   ```
   - **EXCLUDES BUFFERS** ✗

5. **CustomerBookingService::createBooking()**:
   - Does NOT create `BookingService` records at all
   - Only stores `date` and `time` in `Booking` table
   - **NO BUFFER CALCULATION** ✗

#### Which Statuses Reserve Time

From `BookingOrchestrator::reserveAndBook()` (line 120-123):
```php
->whereIn('status', ['confirmed', 'pending', 'reserved'])
```

From `BookingService::validateBookingTime()` (line 140):
```php
->whereIn('status', ['pending', 'confirmed'])
```

**Inconsistency**: `BookingOrchestrator` checks `reserved`, `BookingService` does not.

#### Specialist/Resource Assignment

- `Booking.specialist_id` - global professional identity
- `Booking.staff_id` - salon-local employee
- `BookingService` records track actual service intervals
- Resource allocations tracked in `ResourceAllocation` table

#### Location and Provider Scope

- `Booking.provider_id` - required, validated via middleware
- `Booking.location_id` - nullable (for independent specialists)
- Middleware: `ResolveProviderContext` injects provider context
- Controller validation: `BookingController::store()` validates provider_id matches context (line 154-156)

#### Buffer Application in Availability Check

From `AvailabilityEngine::generateTimeSlots()` (lines 753-755):
```php
$serviceDuration = $service ? $service->duration : 60;
$bufferBefore    = $service ? ($service->buffer_before ?? 0) : 0;
$bufferAfter     = $service ? ($service->buffer_after ?? 0) : 0;
$totalDuration   = $serviceDuration + $bufferBefore + $bufferAfter;
```

**Overlap Check** (lines 781-789):
```php
if ($slotStart->lt($bookingEnd) && $slotEnd->gt($bookingStart)) {
    $isAvailable = false;
    break;
}
```

**Issue**: Overlap check uses `booking.start_time` and `booking.end_time` from `BookingService` records, but these are calculated WITHOUT buffers in booking creation paths.

### 3. Existing Concurrency Protection

#### Current Locking Mechanism

**Location**: `BookingOrchestrator::reserveAndBook()` (lines 181-190)
```php
$lockKey = $assignment
    ? "booking_slot_{$assignment->id}_{$date}_{$time}"
    : "booking_slot_provider_{$providerId}_{$date}_{$time}";
$lock = Cache::lock($lockKey, 10); // lock for 10 seconds

if (!$lock->get()) {
    throw new \Exception('This slot is currently being booked by someone else...');
}
```

**Lock Scope**: Exact start time only (e.g., `10:00`)
**Lock Duration**: 10 seconds
**Lock Release**: In `finally` block (line 232)
**Lock Coverage**: Only Paths 1, 2, 4 (via `BookingOrchestrator`)
**Lock Exclusion**: Path 3 (legacy customer portal) has NO locking

#### Double Booking Check

**Location**: `BookingOrchestrator::reserveAndBook()` (lines 118-139)
```php
if (!$allowDoubleBooking) {
    $conflictQuery = \App\Models\Booking::where('provider_id', $providerId)
        ->where('date', $date)
        ->where('time', $time)
        ->whereIn('status', ['confirmed', 'pending', 'reserved']);
    // ... location_id and specialist_id filtering
    $existingBooking = $conflictQuery->first();
    if ($existingBooking) {
        throw new \Exception('That appointment time is no longer available...');
    }
}
```

**Check Scope**: Exact time match only (e.g., `time = '10:00'`)
**Check Coverage**: Only Paths 1, 2, 4 (via `BookingOrchestrator`)
**Check Exclusion**: Path 3 has NO double booking check

#### Transaction Boundaries

**BookingOrchestrator::reserveAndBook()**: Transaction starts at line 193, lock released at line 232
**CustomerBookingService::createBooking()**: Transaction starts at line 31, no lock
**BookingService::createGuestBooking()**: Transaction starts at line 158, no lock
**BookingService::createBookingWithAccount()**: Transaction starts at line 276, no lock

#### Critical Vulnerability: Interval Overlap Not Prevented

**Scenario**: Two concurrent requests for overlapping intervals with different start times
- Request A: 10:00-10:30 (service duration 30 min)
- Request B: 10:15-10:45 (service duration 30 min)

**Current Protection**:
- Lock key for A: `booking_slot_{assignment_id}_{date}_10:00`
- Lock key for B: `booking_slot_{assignment_id}_{date}_10:15`
- **Different lock keys** - both can acquire locks simultaneously
- Double booking check: `where('time', '10:00')` vs `where('time', '10:15')` - both pass
- **Both bookings can succeed** - overlapping intervals created

**Root Cause**: Lock keyed by exact start time, not by occupied interval

#### Additional Vulnerabilities

1. **Path 3 (Legacy)**: No locking, no double booking check at all
2. **BookingController::update()**: Can change date/time without any check
3. **CustomerBookingService::rescheduleBooking()**: Cancels old before securing new, no lock on new interval
4. **BookingService::resolveAsRescheduled()**: Bypasses availability check entirely
5. **Buffer Inconsistency**: Availability check uses buffers, booking creation does not

### 4. PostgreSQL and Existing Schema

#### PostgreSQL Version
**Version**: PostgreSQL 16.9 (verified via tinker)

#### Relevant Tables

**bookings table**:
- `id` (UUID, primary key)
- `provider_id` (UUID, foreign key)
- `location_id` (UUID, nullable, foreign key)
- `specialist_id` (UUID, nullable, foreign key)
- `staff_id` (UUID, nullable, foreign key)
- `service_id` (UUID, foreign key)
- `date` (date)
- `time` (string, HH:MM format)
- `status` (string)
- `operational_state` (string)
- `slot_locked_until` (timestamp, nullable)
- `rescheduled_from_booking_id` (UUID, nullable, foreign key)

**booking_service table**:
- `id` (UUID, primary key)
- `booking_id` (UUID, foreign key)
- `service_id` (UUID, foreign key)
- `start_time` (timestamp)
- `end_time` (timestamp)
- `session_status` (string)
- `started_at` (timestamp, nullable)
- `actual_end_at` (timestamp, nullable)

**services table**:
- `buffer_before` (integer, default 0)
- `buffer_after` (integer, default 0)
- `duration` (integer)

**location_service table**:
- `buffer_before_override` (integer, nullable)
- `buffer_after_override` (integer, nullable)

#### Existing Constraints

**Foreign Keys**: Standard referential integrity
**Indexes**: Standard indexes on foreign keys, date, status
**No Exclusion Constraints**: No PostgreSQL exclusion constraints for interval overlap
**No Unique Constraints**: No unique constraint on (specialist_id, date, time)

#### Database-Enforced Invariant Feasibility

**Exclusion Constraint Consideration**:
- PostgreSQL supports exclusion constraints via `btree_gist` extension
- Would require: `CREATE EXTENSION IF NOT EXISTS btree_gist;`
- Would require: `ALTER TABLE booking_service ADD CONSTRAINT ... EXCLUDE USING gist (specialist_id WITH =, tsrange(start_time, end_time) WITH &&)`

**Challenges**:
1. **Nullable specialist_id**: Exclusion constraints on nullable columns require complex handling
2. **Buffer representation**: Buffers are calculated at application level, not stored in database
3. **Status filtering**: Only certain statuses reserve time (confirmed, pending, reserved)
4. **Location scoping**: Overlap check must respect location_id
5. **Migration complexity**: Adding exclusion constraint requires data validation first

**Conclusion**: Database exclusion constraint is NOT feasible for Phase 1 without:
- Buffer storage in database
- Status filtering logic in constraint
- Handling of nullable assignments
- Extensive migration and validation

**Recommended Strategy**: Application-level interval locking with transaction-level overlap check

### 5. Recommended Strategy

#### Shared Interval Locking Strategy

**Lock Key Derivation**:
```
booking_interval_{provider_id}_{location_id}_{date}_{specialist_id}_{resource_type}
```

Where `resource_type` is either:
- `specialist_{specialist_id}` if specialist assigned
- `provider_{provider_id}` if no specialist (provider-level booking)

**Lock Scope**: Entire occupied interval for the date + resource
**Lock Duration**: 10 seconds (same as current)
**Lock Acquisition**: Before availability check
**Lock Release**: After transaction commit/rollback

**Critical Section**:
1. Acquire interval lock
2. Inside transaction:
   a. Calculate occupied interval with buffers
   b. Query for overlapping bookings (using timestamp range comparison)
   c. If conflict exists, abort
   d. Create booking with correct end_time (including buffers)
   e. Create BookingService records with correct intervals
3. Release lock

#### Overlap Detection Query

```sql
SELECT COUNT(*) FROM booking_service bs
JOIN bookings b ON b.id = bs.booking_id
WHERE b.date = $date
  AND b.status IN ('confirmed', 'pending', 'reserved', 'pending_payment')
  AND b.specialist_id = $specialist_id
  AND b.location_id = $location_id
  AND b.id != $current_booking_id -- for rescheduling
  AND (
    (bs.start_time < $new_end_time AND bs.end_time > $new_start_time)
  )
```

### 6. Files Expected to Change

**Phase 1 Changes**:

1. **app/Services/BookingOrchestrator.php**
   - Replace exact-time lock with interval lock
   - Fix `calculateEndTime()` to include buffers
   - Add interval overlap check in transaction

2. **app/Services/BookingService.php**
   - Fix `createGuestBooking()` to include buffers in end_time calculation
   - Fix `createBookingWithAccount()` to include buffers in end_time calculation
   - Add interval overlap check in transaction
   - Fix `rescheduleBooking()` to secure new interval first

3. **app/Services/CustomerBookingService.php**
   - Add interval locking
   - Add interval overlap check
   - Create BookingService records with correct intervals
   - Fix `rescheduleBooking()` to secure new interval first

4. **app/Services/AvailabilityEngine.php**
   - Ensure buffer consistency across all methods
   - Centralize interval calculation logic

5. **app/Http/Controllers/Api/V1/BookingController.php**
   - Add availability check to `update()` method
   - Add interval locking to `update()` method

### 7. Potential Regressions and Test Detection

**Potential Regressions**:

1. **Buffer Calculation Change**: Existing bookings may have incorrect end_time
   - **Detection**: Test that new bookings have correct end_time with buffers
   - **Mitigation**: Backfill existing BookingService records if needed

2. **Lock Scope Change**: Broader lock may reduce concurrency
   - **Detection**: Performance tests, concurrent booking tests
   - **Mitigation**: Monitor lock wait times in production

3. **Legacy Path Changes**: CustomerBookingService changes may break existing integrations
   - **Detection**: Run existing customer portal tests
   - **Mitigation**: Ensure backward compatibility

4. **Rescheduling Safety**: Securing new first may change behavior
   - **Detection**: Test rescheduling with conflicts
   - **Mitigation**: Ensure original booking preserved on failure

**Test Strategy**:

1. **Sequential Conflict Tests**: Verify overlapping intervals rejected
2. **Concurrent PostgreSQL Tests**: Verify genuine concurrent requests cannot both succeed
3. **Buffer Consistency Tests**: Verify end_time includes buffers in all paths
4. **Regression Tests**: Run existing booking, availability, rescheduling test suites

## Audit Deliverable Summary

### 1. All Booking Paths Discovered

**Creation (4)**:
1. Staff/Admin via BookingController → BookingOrchestrator → BookingService
2. Customer Portal (Journey 4) via BookingController → BookingOrchestrator → BookingService
3. Customer Portal (Legacy) via CustomerBookingController → CustomerBookingService
4. Booking Orchestrator Direct via BookingOrchestratorController → BookingOrchestrator → BookingService

**Modification (4)**:
1. Admin Update via BookingController::update() (direct field update, no check)
2. Customer Reschedule via CustomerBookingController → BookingService::rescheduleBooking
3. Resolution Reschedule via BookingController::resolve → BookingService::resolveAsRescheduled
4. Floor Operations via FloorController (operational only, no interval change)

### 2. Existing Invariant and Buffer Rules

**Buffer Resolution**: Service-level buffers with location-level overrides
**Buffer Application**: Inconsistent - AvailabilityEngine includes buffers, booking creation does not
**Statuses Reserving Time**: confirmed, pending, reserved, pending_payment (inconsistent across paths)
**Interval Calculation**: Multiple methods with inconsistent buffer inclusion

### 3. Concrete Concurrency Vulnerability

**Vulnerability**: Lock keyed by exact start time only, not by occupied interval
**Impact**: Two concurrent requests for overlapping intervals with different start times can both succeed
**Example**: 10:00-10:30 and 10:15-10:45 can both be booked for same specialist
**Root Cause**: Cache lock on exact time, double booking check on exact time, no interval overlap protection

### 4. Recommended Strategy

**Strategy**: Application-level interval locking with transaction-level overlap check
**Lock Key**: `booking_interval_{provider_id}_{location_id}_{date}_{specialist_id}_{resource_type}`
**Lock Duration**: 10 seconds
**Critical Section**: Lock → Transaction → Overlap Check → Create → Release
**Overlap Detection**: Timestamp range comparison in SQL query
**Database Constraint**: Not feasible for Phase 1 (requires buffer storage, status filtering, nullable handling)

### 5. Exact Files Expected to Change

1. `app/Services/BookingOrchestrator.php` - interval lock, buffer fix, overlap check
2. `app/Services/BookingService.php` - buffer fix, overlap check, safe reschedule
3. `app/Services/CustomerBookingService.php` - interval lock, overlap check, BookingService records
4. `app/Services/AvailabilityEngine.php` - buffer consistency, centralize calculation
5. `app/Http/Controllers/Api/V1/BookingController.php` - update() check and lock

### 6. Potential Regressions and Test Detection

**Regressions**: Buffer calculation changes, lock scope changes, legacy path changes, rescheduling changes
**Detection**: Sequential conflict tests, concurrent PostgreSQL tests, buffer consistency tests, regression tests
**Mitigation**: Backfill existing data, monitor performance, ensure backward compatibility, test rescheduling conflicts

## Next Steps

**Proceed to Part B**: Implement shared booking invariants with the recommended strategy
**No Critical Unresolved Design Decisions**: All required information gathered from existing code and schema
**PostgreSQL Verified**: Version 16.9, exclusion constraints not feasible for Phase 1

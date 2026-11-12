# Yo.Salon Booking Lifecycle Design Decision Report

## Executive Summary

This report provides focused design decisions for implementing the agreed booking lifecycle and payment-safety rules. The design preserves existing structures where possible, minimizes destructive migrations, and establishes clear invariants for all booking creation paths.

## Proposed Invariants

### 1. Booking Creation Invariants
All four booking creation paths MUST enforce:
- **Deposit Policy Integration**: Check deposit requirement before setting initial status
- **Buffer Consistency**: Calculate end_time as `start + duration + buffer_before + buffer_after` in ALL paths
- **Interval Overlap Protection**: Prevent overlapping occupied intervals (not just exact time matches)
- **Reservation Hold**: Use provider-configurable hold (default 30 minutes) for deposit-required bookings
- **Approval Before Deposit**: Manual-approval bookings require approval before deposit checkout

### 2. Payment Lifecycle Invariants
- **Hold Creation**: Set `slot_locked_until` to provider-configurable minutes (default 30) when creating reservation
- **Payment Initiation**: Create PaymentRequest with `expires_at` = `slot_locked_until`
- **Expiry**: Mark expired requests as `expired` status, release slot
- **Bounded Grace**: Allow 5-minute grace for payments initiated before expiry
- **Verification**: Check `expires_at` before accepting payment; reject if past grace window
- **Fresh Availability Check**: Recheck availability inside transaction before confirming
- **Slot Unavailable Handling**: If payment succeeds but slot unavailable, flag for resolution, preserve payment

### 3. Rescheduling Invariants
- **Secure New First**: Validate and secure replacement interval before cancelling original
- **Original Preserved**: If replacement fails, original booking remains unchanged
- **No Bypass**: Walk-ins and internal bookings must also pass availability checks

### 4. Session Lifecycle Invariants
- **Preserve Existing**: Do not change session start/completion workflow
- **Multi-Service**: Each BookingService has independent session_status
- **Parent Booking**: Parent booking status transitions only when ALL required services complete

## Affected Files

### Phase 1: Booking Policy & Interval Safety

**Critical Changes:**
1. `app/Services/CustomerBookingService.php`
   - Integrate deposit policy check before `determineInitialBookingStatus()`
   - Add buffer calculation to end_time
   - Add interval overlap check in validation

2. `app/Services/BookingOrchestrator.php`
   - Fix `calculateEndTime()` to include buffers (line 236-242)
   - Replace exact-time cache lock with interval-based protection
   - Change hardcoded 10-minute slot lock to provider-configurable hold

3. `app/Services/AvailabilityEngine.php`
   - Enhance overlap detection to check intervals, not just exact times
   - Ensure buffer consistency across all time calculations

4. `app/Services/BookingService.php`
   - Ensure buffer consistency in `createGuestBooking()` and `createBookingWithAccount()`
   - Add interval overlap check in transaction

### Phase 2: Payment, Holds, Expiry

**Critical Changes:**
1. `routes/console.php`
   - Add `Schedule::command('payments:expire-pending')->everyMinute()->withoutOverlapping();`

2. `app/Console/Commands/ExpirePendingPayments.php`
   - Add bounded grace period logic (5 minutes for in-flight payments)
   - Add slot release for expired reservations
   - Add activity logging for expiry events

3. `app/Services/Payments/BookingPaymentService.php`
   - Add expiry check in `verifyBookingPayment()`
   - Add fresh availability recheck before confirmation
   - Add handling for payment-success/slot-unavailable scenario

4. `app/Services/BookingConfigurationService.php`
   - Add `getReservationHoldMinutes()` method
   - Default to 30 minutes, provider-configurable via payment_policy

5. `app/Services/BookingService.php`
   - Update `createBookingWithAccount()` to use provider-configurable hold
   - Add payment success with availability recheck logic

### Phase 3: Approval, Rejection, Rescheduling

**Critical Changes:**
1. `app/Http/Controllers/Api/V1/BookingController.php`
   - Add `approve()` endpoint with authorization
   - Add `reject()` endpoint with authorization
   - Reuse existing resolution mechanism for rejection

2. `app/Services/BookingService.php`
   - Add `approveBooking()` method with authorization check
   - Add `rejectBooking()` method with authorization check
   - Update `rescheduleBooking()` to secure new first

3. `app/Services/CustomerBookingService.php`
   - Update `rescheduleBooking()` to secure new interval first

4. `app/Http/Middleware/`
   - Add `CanApproveBooking` middleware for role-based authorization
   - Add `CanRejectBooking` middleware for role-based authorization

### Phase 4: Testing

**New Test Files:**
1. `tests/Feature/DepositBookingFlowTest.php`
2. `tests/Feature/PaymentExpiryTest.php`
3. `tests/Feature/LatePaymentHandlingTest.php`
4. `tests/Feature/ApprovalAuthorizationTest.php`
5. `tests/Feature/IntervalOverlapConcurrencyTest.php`

**Enhanced Tests:**
1. `tests/Feature/ConcurrentBookingConflictTest.php` - add interval overlap scenarios
2. `tests/Unit/BookingServiceSessionFieldsTest.php` - verify multi-service completion

## Database Migrations

### Required Migrations

**Migration 1: Add Reservation Hold Configuration**
```php
// Add reservation_hold_minutes to payment_policy
// Default: 30 minutes
// Location: provider_booking_config.payment_policy, location_booking_config.payment_policy_override
// Justification: Provider-configurable holds per business rules
```

**Migration 2: Add Payment Grace Tracking**
```php
// Add payment_grace_started_at to bookings table
// Type: timestamp, nullable
// Justification: Track when payment entered grace period for bounded grace logic
```

**Migration 3: Add Rejection Tracking (Optional)**
```php
// Option A: Add rejection_reason to bookings table
// Option B: Reuse existing booking_resolutions table with outcome=REJECTED
// Recommendation: Reuse booking_resolutions - no new column needed
```

### No Schema Changes For:
- Interval overlap protection (application-level logic)
- Buffer fields (already exist in services table)
- Payment request tracking (already exists)
- Activity logging (BookingActivity already exists)
- Resolution tracking (booking_resolutions already exists)

## Interval Overlap Protection Strategy

### Decision: Application-Level Interval Locking with Database Transaction

**Rationale:**
1. PostgreSQL exclusion constraints require `btree_gist` extension - not verified as available
2. Application-level locking allows integration with existing cache infrastructure
3. Can be enhanced later with database constraints if needed

**Implementation:**
1. **Lock Key**: `booking_interval_{provider_id}_{location_id}_{date}_{start_time}_{end_time}`
2. **Lock Scope**: Entire occupied interval including buffers
3. **Duration**: 10 seconds (same as current cache lock)
4. **Transaction Check**: Inside transaction, verify no overlapping bookings exist
5. **Query**: Check for bookings where intervals overlap using timestamp range comparison

**Overlap Detection Query:**
```sql
SELECT COUNT(*) FROM booking_service bs
JOIN bookings b ON b.id = bs.booking_id
WHERE b.date = $date
  AND b.status IN ('confirmed', 'reserved', 'pending_payment')
  AND (
    (bs.start_time < $new_end_time AND bs.end_time > $new_start_time)
  )
  AND b.specialist_id = $specialist_id
```

**Limitations:**
- Relies on cache for distributed locking (Redis configured)
- Requires all booking paths to use the same lock strategy
- Does not prevent manual database modifications

**Mitigation:**
- Add database constraint later if needed
- Monitor for double bookings in production
- Add alerting for overlap detection

## Payment Lifecycle Definition

### State Flow
```
1. Hold Creation
   - Booking created with status='reserved' or 'pending_payment'
   - slot_locked_until = now() + provider_configured_hold_minutes (default 30)
   - payment_expires_at = slot_locked_until

2. Payment Initiation
   - PaymentRequest created with status='pending'
   - expires_at = payment_expires_at
   - provider_reference generated

3. Expiry Processing (scheduled every minute)
   - Find PaymentRequest where status='pending' AND expires_at < now() - 5 minutes
   - Update status='expired'
   - Release booking slot (set status='expired' or 'cancelled')
   - Log BookingActivity
   - Send notification to customer

4. Payment Verification
   - Check PaymentRequest.expires_at
   - If now() > expires_at + 5 minutes: reject as expired
   - If now() <= expires_at + 5 minutes: accept as in-flight grace
   - If payment successful:
     a. Recheck availability inside transaction
     b. If available: confirm booking
     c. If unavailable: flag for resolution, preserve payment

5. Grace Period
   - 5-minute bounded grace for payments initiated before expiry
   - Tracked via payment_grace_started_at when payment enters grace
   - Only applies if payment was initiated before expires_at
```

### Edge Cases
- **Payment initiated at 29:59, completes at 30:05**: Accept (within grace)
- **Payment initiated at 30:01, completes at 30:05**: Reject (initiated after expiry)
- **Payment succeeds but slot taken**: Flag booking for resolution, do not confirm
- **Duplicate payment callback**: Idempotency check via idempotency_key

## Production Scheduler/Queue-Worker Configuration

### Current Configuration (Verified)
**File:** `supervisor.conf`
- **Queue Worker**: `laravel-worker` (2 processes, Redis queue)
- **Scheduler**: `laravel-scheduler` (1 process, `schedule:work`)
- **Reverb**: `laravel-reverb` (1 process)

### Verification Required
1. **Cron Configuration**: Verify Laravel scheduler is configured in production cron
   - Expected: `* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`
   - Location: Server crontab (not in codebase)

2. **Supervisor Status**: Verify supervisor is running and managing processes
   - Command: `sudo supervisorctl status`
   - Expected: All processes in RUNNING state

3. **Redis Connection**: Verify Redis is accessible from production
   - Configured in `.env`: REDIS_HOST=127.0.0.1, REDIS_PORT=6379
   - Verify: `redis-cli ping`

### Deployment Steps
1. Add `payments:expire-pending` to `routes/console.php`
2. Deploy code changes
3. Verify scheduler picks up new command
4. Monitor logs for expiry execution
5. Verify queue workers are processing payment webhooks

## Session Completion Behavior Verification

### Current Behavior (Verified)
**Test:** `BookingServiceSessionFieldsTest.php`
- Each `BookingService` has independent `session_status`
- Default status: `scheduled`
- Session fields: `started_at`, `actual_end_at`, `session_status`
- Multi-service bookings: Each service can be in different session states

**Test Finding:** `test_multiple_booking_services_can_have_independent_session_states()` (line 175)
- Service 1: `in_progress` at 10:00-10:30
- Service 2: `scheduled` at 10:35-11:35
- **No parent booking status change in this test**

### Contradictory Test
**Finding:** No test explicitly expects completing one service to change parent booking status
**Conclusion:** The existing test represents correct behavior - multi-service bookings should NOT complete parent until ALL services complete

**Preservation Rule:** Do not change session completion logic. Parent booking status transitions are handled separately in existing domain logic.

## Risks

### High Risk
1. **Interval Overlap Protection**: Application-level locking may not prevent all race conditions
   - **Mitigation**: Add database exclusion constraint later if needed
   - **Monitoring**: Add alerting for overlapping bookings

2. **Payment Expiry Scheduling**: If scheduler not configured in production cron, expiry won't run
   - **Mitigation**: Verify cron configuration during deployment
   - **Fallback**: Manual expiry command can be run

3. **Late Payment Handling**: Accepting late payments may create double bookings
   - **Mitigation**: Fresh availability recheck before confirmation
   - **Fallback**: Flag for resolution if slot unavailable

### Medium Risk
1. **Buffer Calculation Inconsistency**: Multiple paths calculate end_time differently
   - **Mitigation**: Centralize calculation in helper method
   - **Testing**: Comprehensive buffer tests

2. **Rescheduling Safety**: Current implementation cancels old before securing new
   - **Mitigation**: Change to secure new first
   - **Rollback**: Keep original if new fails

### Low Risk
1. **Provider Configurable Holds**: New configuration may not be set for existing providers
   - **Mitigation**: Default to 30 minutes in code
   - **Migration**: Backfill default values

2. **Authorization Middleware**: New middleware may block legitimate users
   - **Mitigation**: Test with different user roles
   - **Rollback**: Can be disabled if issues

## Test Strategy

### Phase 1 Tests
1. **Buffer Consistency**: Verify end_time calculation includes buffers in all paths
2. **Deposit Integration**: Verify deposit check before status determination
3. **Interval Overlap**: Verify overlapping intervals prevented (same specialist, different start times)
4. **Concurrent Requests**: Verify cache lock prevents race conditions

### Phase 2 Tests
1. **Hold Creation**: Verify slot_locked_until set to provider-configured minutes
2. **Payment Initiation**: Verify PaymentRequest expires_at matches slot_locked_until
3. **Expiry Processing**: Verify expired requests marked and slots released
4. **Grace Period**: Verify payments within 5-minute grace accepted
5. **Late Payment**: Verify payments after grace rejected
6. **Fresh Availability**: Verify availability recheck before confirmation
7. **Slot Unavailable**: Verify payment success with slot unavailable flags resolution

### Phase 3 Tests
1. **Approval Authorization**: Verify only authorized users can approve
2. **Rejection Authorization**: Verify only authorized users can reject
3. **Approval Before Deposit**: Verify manual-approval requires approval before deposit
4. **Safe Rescheduling**: Verify new interval secured before old cancelled
5. **Rejection vs Expiry**: Verify distinct outcomes and notifications

### Phase 4 Tests
1. **Integration Tests**: End-to-end booking flows
2. **Concurrency Tests**: Real database concurrent requests
3. **Regression Tests**: Verify existing behavior unchanged
4. **Session Completion**: Verify multi-service booking completion

### Test Execution Order
1. Unit tests for individual components
2. Feature tests for booking flows
3. Concurrency tests with real database
4. Integration tests end-to-end
5. Regression tests for existing functionality

## Deployment Steps

### Pre-Deployment
1. Review and approve this design decision report
2. Create feature branch for each phase
3. Implement Phase 1 changes and tests
4. Run Phase 1 tests and verify passing
5. Code review Phase 1

### Phase 1 Deployment
1. Deploy Phase 1 changes
2. Monitor for buffer calculation issues
3. Monitor for interval overlap incidents
4. Roll back if critical issues

### Phase 2 Deployment
1. Verify production cron configuration
2. Add expiry command to scheduler
3. Deploy Phase 2 changes
4. Monitor expiry execution logs
5. Monitor payment verification logs
6. Roll back if payment issues

### Phase 3 Deployment
1. Deploy authorization middleware
2. Deploy approval/rejection endpoints
3. Deploy safe rescheduling changes
4. Monitor authorization logs
5. Roll back if authorization issues

### Phase 4 Deployment
1. Run full test suite
2. Deploy all changes
3. Monitor production metrics
4. Verify no double bookings
5. Verify payment expiry working
6. Verify approval/rejection working

## Rollback Plan

### Phase 1 Rollback
- Revert buffer calculation changes
- Revert interval overlap protection
- Restore original cache lock strategy

### Phase 2 Rollback
- Remove expiry command from scheduler
- Revert payment verification changes
- Restore original expiry values

### Phase 3 Rollback
- Remove approval/rejection endpoints
- Remove authorization middleware
- Restore original rescheduling logic

### Database Rollback
- No destructive schema changes planned
- New columns can be safely ignored if rolled back
- Data migration not required

## Success Criteria

### Phase 1 Success
- All booking paths use consistent buffer calculation
- Interval overlap protection prevents double bookings
- Deposit policy integrated in all paths
- Tests pass for all scenarios

### Phase 2 Success
- Expiry command runs in production
- Late payments handled correctly
- Payment success with slot unavailable handled
- No lost successful payments
- No confirmed double bookings

### Phase 3 Success
- Approval/rejection endpoints working
- Authorization enforced correctly
- Safe rescheduling working
- Rejection vs expiry distinct

### Phase 4 Success
- All 17 verification scenarios tested
- No regression in existing functionality
- Session completion unchanged
- Production metrics stable

## Open Questions

1. **PostgreSQL Exclusion Constraints**: Should we add database exclusion constraints for interval overlap?
   - **Recommendation**: Start with application-level, add database constraints later if needed

2. **Grace Period Duration**: Is 5 minutes appropriate for bounded grace?
   - **Recommendation**: 5 minutes is reasonable, can be configured if needed

3. **Payment Provider Integration**: How to identify in-flight payments for grace period?
   - **Recommendation**: Use PaymentRequest.requested_at timestamp

4. **Multi-Location Providers**: How to handle interval overlap across locations?
   - **Recommendation**: Overlap check scoped to location_id or specialist_id

## Next Steps

1. **Review**: Review this design decision report
2. **Approve**: Approve or request changes
3. **Phase 1**: Implement Phase 1 changes
4. **Test**: Run Phase 1 tests
5. **Deploy**: Deploy Phase 1 to production
6. **Monitor**: Monitor Phase 1 metrics
7. **Phase 2**: Repeat for Phase 2
8. **Phase 3**: Repeat for Phase 3
9. **Phase 4**: Run full regression tests
10. **Complete**: Verify all success criteria met

# Live Service Session Implementation

## Overview

The Live Service Session feature provides session lifecycle management for booking services, enabling specialists to track session execution state, calculate expected end times, detect overruns, and handle cancellations.

## Session Lifecycle

```
scheduled ───────→ cancelled
     │
     ↓
in_progress ─────→ cancelled
     │
     ↓
 completed
```

## Session States

| State | Description | Transitions From | Transitions To |
|-------|-------------|------------------|----------------|
| `scheduled` | Session is scheduled but not yet started | Initial state | `in_progress`, `cancelled` |
| `in_progress` | Session is actively being executed | `scheduled` | `completed`, `cancelled` |
| `completed` | Session has finished naturally | `in_progress` | None (terminal) |
| `cancelled` | Session was aborted | `scheduled`, `in_progress` | None (terminal) |

## API Contract

### Endpoints

#### Start Session
```
POST /api/v1/specialist-portal/sessions/{bookingServiceId}/start
```

**Response:**
```json
{
  "id": "uuid",
  "booking_id": "uuid",
  "service_id": "uuid",
  "start_time": "2026-12-01T10:00:00Z",
  "end_time": "2026-12-01T11:00:00Z",
  "session_status": "in_progress",
  "started_at": "2026-12-01T10:00:00Z",
  "actual_end_at": null,
  "expected_end_at": "2026-12-01T11:00:00Z",
  "overrun": null
}
```

#### Complete Session
```
POST /api/v1/specialist-portal/sessions/{bookingServiceId}/complete
```

**Response:**
```json
{
  "id": "uuid",
  "booking_id": "uuid",
  "service_id": "uuid",
  "start_time": "2026-12-01T10:00:00Z",
  "end_time": "2026-12-01T11:00:00Z",
  "session_status": "completed",
  "started_at": "2026-12-01T10:00:00Z",
  "actual_end_at": "2026-12-01T11:15:00Z",
  "expected_end_at": "2026-12-01T11:00:00Z",
  "overrun": "00:15:00"
}
```

#### Cancel Session
```
POST /api/v1/specialist-portal/sessions/{bookingServiceId}/cancel
```

**Response:**
```json
{
  "id": "uuid",
  "booking_id": "uuid",
  "service_id": "uuid",
  "start_time": "2026-12-01T10:00:00Z",
  "end_time": "2026-12-01T11:00:00Z",
  "session_status": "cancelled",
  "started_at": "2026-12-01T10:00:00Z",
  "actual_end_at": null,
  "expected_end_at": null,
  "overrun": null
}
```

## Session State Fields

| Field | Type | Description | Calculation |
|-------|------|-------------|-------------|
| `session_status` | string | Current session state | Set by session transitions |
| `started_at` | timestamp | When session started | Set on `start` call |
| `actual_end_at` | timestamp | When session actually ended | Set on `complete` call |
| `expected_end_at` | timestamp | Calculated expected end time | `started_at + effective_duration` |
| `overrun` | string | Duration beyond expected end | Calculated based on state |

## Expected End Time Calculation

The `expected_end_at` is calculated using the authoritative service duration from `LocationServiceCatalogResolver`:

1. If location has a duration override for the service, use the override
2. Otherwise, use the base service duration
3. Add duration to `started_at` to get expected end time

**Formula:**
```
expected_end_at = started_at + effective_service_duration
```

## Overrun Calculation

Overrun is calculated differently based on session state:

| State | Overrun Calculation |
|-------|---------------------|
| `scheduled` | `null` (session hasn't started) |
| `in_progress` | `now - expected_end_at` if `now > expected_end_at`, else `null` |
| `completed` | `actual_end_at - expected_end_at` if `actual_end_at > expected_end_at`, else `null` |
| `cancelled` | `null` (session was aborted) |

**Format:** `HH:MM:SS` (e.g., "00:15:00" for 15 minutes overrun)

## Authorization

All session endpoints require:
- Valid specialist authentication via `auth:specialist` middleware
- Specialist must be the assigned specialist for the booking
- Specialist must have an active assignment at the provider

**Unauthorized Response:**
```json
{
  "message": "You are not the assigned specialist for this booking."
}
```
**Status:** 422

## Implementation Details

### Database Schema

**Table:** `booking_service`

**Columns:**
- `started_at` (timestamp, nullable)
- `actual_end_at` (timestamp, nullable)
- `session_status` (string, default: 'scheduled')

**Migration:** `2026_09_18_071329_add_session_fields_to_booking_service_table.php`

### Service Layer

**Class:** `App\Services\SessionService`

**Methods:**
- `startSession(BookingService, Specialist): BookingService`
- `completeSession(BookingService, Specialist): BookingService`
- `cancelSession(BookingService, Specialist): BookingService`

**Validations:**
- Booking must be confirmed
- Specialist must own the booking
- Specialist must have active assignment
- Session must be in valid state for transition
- Session must not already be in target state

**Cache Invalidation:**
All session transitions invalidate availability cache via `AvailabilityEngine::clearAvailabilityCacheForSession()`

### Controller Layer

**Class:** `App\Http\Controllers\SessionController`

**Methods:**
- `start(Request, bookingServiceId): JsonResponse`
- `complete(Request, bookingServiceId): JsonResponse`
- `cancel(Request, bookingServiceId): JsonResponse`

**Response:** Returns `BookingServiceResource` with session state fields

### Resource Layer

**Class:** `App\Http\Resources\BookingServiceResource`

**Responsibilities:**
- Transform booking service with session state
- Calculate `expected_end_at` using `LocationServiceCatalogResolver`
- Calculate `overrun` based on session state and timestamps
- Handle cancelled state (null expected_end_at and overrun)

### Routes

**File:** `routes/api.php`

**Route Group:** `api/v1/specialist-portal` with `auth:specialist` middleware

**Routes:**
- `POST /sessions/{bookingServiceId}/start`
- `POST /sessions/{bookingServiceId}/complete`
- `POST /sessions/{bookingServiceId}/cancel`

## Session Implementation History

### Session 0-3 (Pre-existing)
- Database schema for session fields
- Model relationships
- Basic service layer with start/complete
- Authorization validation
- Availability cache invalidation

### Session 4 (API State Contract)
**Objective:** Expose session state via API with calculated fields

**Changes:**
- Created `BookingServiceResource` with session state transformation
- Updated `SessionController` to return resource responses
- Added `expected_end_at` calculation using `LocationServiceCatalogResolver`
- Added `overrun` calculation based on session state
- Added focused API tests

**Test Results:** 10 tests / 26 assertions

### Session 5 (Cancellation)
**Objective:** Add session cancellation capability

**Changes:**
- Added `cancelSession()` method to `SessionService`
- Added `cancel()` method to `SessionController`
- Added cancel route to `api.php`
- Updated `BookingServiceResource` to handle cancelled state
- Added 5 cancellation tests

**Allowed Transitions:**
- `scheduled → cancelled`
- `in_progress → cancelled`

**Blocked Transitions:**
- `completed → cancelled`
- `cancelled → cancelled`

**Test Results:** 15 tests / 40 assertions (10 Session 0-4 + 5 Session 5)

## Out of Scope

The following are explicitly out of scope for the Live Service Session feature:

- Payment/deposit refund logic
- Booking cancellation modifications
- Cancellation reason tracking
- Notification system integration
- Frontend UI components
- Audit trail for cancellations
- Cancellation fees or penalties
- Customer-facing cancellation UI
- Session pause/resume functionality

## Domain Boundaries

### Booking vs Session Cancellation

**Booking Cancellation:**
- Customer cancels appointment before service execution
- Handled by `CustomerBookingService::cancelBooking()`
- Sets `booking->status = 'cancelled'`
- 24-hour cancellation policy
- Separate domain from session execution

**Session Cancellation:**
- Specialist aborts session during execution
- Handled by `SessionService::cancelSession()`
- Sets `booking_service->session_status = 'cancelled'`
- No time-based restrictions
- Separate domain from booking lifecycle

## Testing

### Test Suite

**File:** `tests/Feature/SessionApiTest.php`

**Test Coverage:**
1. Start response contains session state fields
2. Expected end at uses base service duration
3. Expected end at respects location duration override
4. In progress before expected end has no overrun
5. In progress after expected end exposes live overrun
6. Completed session that runs late exposes overrun
7. Completed session on time has no overrun
8. Completed session early has no overrun
9. Start authorization remains unchanged
10. Complete authorization remains unchanged
11. Cancel scheduled session
12. Cancel in progress session
13. Cannot cancel completed session
14. Cannot cancel already cancelled session
15. Cancel authorization remains unchanged

**Test Results:** 15 passed / 40 assertions

## Files Changed

### Production Files
1. `backend/app/Services/SessionService.php` - Added cancelSession()
2. `backend/app/Http/Controllers/SessionController.php` - Added cancel()
3. `backend/app/Http/Resources/BookingServiceResource.php` - Created with session state calculations
4. `backend/routes/api.php` - Added cancel route

### Test Files
1. `backend/tests/Feature/SessionApiTest.php` - Created with 15 tests

### Database Files
1. `backend/database/migrations/2026_09_18_071329_add_session_fields_to_booking_service_table.php` - Session fields

## Dependencies

- `LocationServiceCatalogResolver` - Authoritative service duration resolution
- `AvailabilityEngine` - Cache invalidation on session changes
- `SpecialistAssignment` - Active assignment validation
- `Booking` - Booking status validation

## Architecture Decisions

1. **String-based session_status:** Used string instead of enum to allow future state additions without migration
2. **Calculated expected_end_at:** Computed on-the-fly rather than stored to handle duration overrides
3. **Separate cancellation domains:** Booking cancellation and session cancellation are separate concerns
4. **No cancellation reasons:** Reason tracking deferred to avoid scope creep
5. **No payment handling:** Payment/refund logic is separate domain concern
6. **Authorization via specialist ownership:** Uses existing specialist assignment validation pattern

## Verification

All session functionality has been verified through:
- Focused API tests covering all state transitions
- Authorization tests for all endpoints
- Duration calculation tests with and without overrides
- Overrun calculation tests for all session states
- Regression tests to ensure Session 0-4 behavior unchanged

**Final Test Results:** 15 passed / 40 assertions

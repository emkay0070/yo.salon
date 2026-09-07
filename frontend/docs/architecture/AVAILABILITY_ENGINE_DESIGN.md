# Availability Engine Design

## Overview

The availability engine is the core scheduling engine for Yo.Salon. It answers one fundamental question:

> "Given a provider, service, specialist (optional), and date, what can the customer actually book?"

**Principle**: Build it once, build it properly, let everything else depend on it.

---

## Core Architecture

### Domain Service

```
AvailabilityEngine
```

**Responsibilities:**
- Validate business hours
- Validate specialist schedules
- Check existing bookings
- Apply service duration
- Apply buffer times
- Return available slots
- Handle timezone conversions
- Prevent double-booking
- Support slot locking

**Location**: `backend/app/Services/AvailabilityEngine.php`

---

## Data Model Requirements

### Provider Schedule

```php
// providers table (existing)
- id
- timezone (string) - e.g., "Africa/Kampala"
- settings (JSON) - contains schedule configuration

// provider_schedules table (new)
- id
- provider_id (UUID)
- day_of_week (integer) - 0-6 (Sunday-Saturday)
- open_time (time) - e.g., "09:00:00"
- close_time (time) - e.g., "18:00:00"
- is_closed (boolean) - override for holidays/closures
- effective_date (date) - for schedule changes
- expires_date (date nullable) - for temporary schedules
```

**Design Notes:**
- Recurring weekly schedules
- Exception support (holidays, closures)
- Timezone-aware calculations
- Schedule versioning (effective/expiry dates)

---

### Specialist Schedule

```php
// specialists table (existing)
- id
- provider_id (UUID nullable)
- is_active (boolean)
- timezone (string) - can differ from provider

// specialist_schedules table (new)
- id
- specialist_id (UUID)
- day_of_week (integer) - 0-6
- start_time (time)
- end_time (time)
- break_start_time (time nullable)
- break_end_time (time nullable)
- is_available (boolean)
- effective_date (date)
- expires_date (date nullable)
```

**Design Notes:**
- Independent schedules from provider
- Break times (lunch, rest periods)
- Can work at multiple providers
- Active status (on leave, suspended)

---

### Service Configuration

```php
// services table (existing)
- id
- provider_id (UUID)
- duration (integer) - in minutes
- buffer_before (integer) - minutes before service
- buffer_after (integer) - minutes after service
- requires_specialist (boolean) - can be self-service
- max_concurrent (integer) - how many at once
- min_booking_notice (integer) - hours in advance required
- max_booking_days_ahead (integer) - how far ahead can book
- cancellation_cutoff_hours (integer) - hours before can cancel
```

**Design Notes:**
- Buffer times prevent back-to-back rush
- Lead time prevents last-minute bookings
- Advance limit prevents distant bookings
- Cancellation policy enforcement

---

### Specialist Skills

```php
// specialist_service table (new)
- id
- specialist_id (UUID)
- service_id (UUID)
- skill_level (enum) - beginner, intermediate, expert
- is_primary (boolean) - preferred specialist for this service
- price_override (decimal nullable) - specialist-specific pricing
```

**Design Notes:**
- Not all specialists can do all services
- Skill levels for matching
- Specialist-specific pricing
- Primary specialist designation

---

### Booking Status Tracking

```php
// bookings table (existing)
- id
- provider_id (UUID)
- specialist_id (UUID nullable)
- service_id (UUID)
- date (date)
- start_time (time)
- end_time (time)
- status (enum) - pending, confirmed, cancelled, no_show, completed
- slot_locked_until (timestamp nullable) - temporary hold
- created_at
- updated_at
```

**Design Notes:**
- Slot locking prevents race conditions
- Status tracking for availability calculation
- No-show tracking for reputation system

---

## Availability Calculation Logic

### Algorithm Flow

```
1. Get provider schedule for the date
   - Check if provider is closed
   - Get operating hours
   - Apply timezone conversion

2. Get specialist schedule (if specified)
   - Check if specialist is active
   - Get specialist working hours
   - Apply specialist timezone
   - Check break times

3. Get service configuration
   - Get duration
   - Get buffer times
   - Get booking constraints (lead time, advance limit)

4. Get existing bookings
   - Get confirmed bookings for the date
   - Get pending bookings with active slot locks
   - Filter by provider/specialist

5. Generate time slots
   - Start from provider open time
   - Increment by slot granularity (15min default)
   - End at provider close time

6. Filter slots
   - Remove slots outside specialist hours
   - Remove slots during specialist breaks
   - Remove slots occupied by existing bookings
   - Remove slots before lead time
   - Remove slots after advance limit
   - Apply service duration (slot must fit)
   - Apply buffer times (before/after)
   - Check specialist skill (if specialist specified)

7. Return available slots
   - With start/end times
   - With available specialists
   - With pricing information
```

---

### Slot Granularity

**Default**: 15-minute intervals

**Configurable**: Provider can set:
- 15-minute slots (standard)
- 30-minute slots (longer services)
- 60-minute slots (day-based scheduling)

**Rationale**: 15-minute slots provide flexibility for services of varying durations while keeping the UI manageable.

---

### Timezone Handling

**Strategy**: Store all times in UTC, convert to provider timezone for display

**Implementation**:
- Provider has a `timezone` field
- All datetime calculations use provider timezone
- API returns times in provider timezone
- Frontend displays in user's local timezone

**Edge Cases**:
- Daylight saving time transitions
- Cross-timezone bookings
- Specialist in different timezone than provider

---

### Buffer Times

**Purpose**: Prevent rushing between appointments

**Calculation**:
```
Available slot = [service_start - buffer_before, service_end + buffer_after]
```

**Example**:
- Service: 45 minutes
- Buffer before: 15 minutes
- Buffer after: 15 minutes
- Total time blocked: 75 minutes

---

### Slot Locking

**Purpose**: Prevent race conditions during booking

**Implementation**:
1. When customer selects a slot, create a temporary booking with `slot_locked_until`
2. Lock expires after 10 minutes (configurable)
3. If booking not confirmed, lock expires and slot becomes available
4. If booking confirmed, lock becomes permanent

**API**:
```php
POST /providers/{provider}/slots/lock
{
  "date": "2026-08-03",
  "start_time": "09:00",
  "service_id": "...",
  "specialist_id": "..."
}

Response:
{
  "lock_id": "...",
  "expires_at": "2026-08-03T09:10:00Z"
}
```

---

## API Design

### Primary Endpoint

```
GET /v1/providers/{provider_id}/availability
```

**Query Parameters**:
```
date (required) - YYYY-MM-DD
service_id (optional) - filter by service
specialist_id (optional) - filter by specialist
slot_granularity (optional) - 15, 30, 60 (default: 15)
include_specialists (optional) - true/false (default: true)
```

**Response**:
```json
{
  "provider_id": "...",
  "provider_name": "Fade Republic",
  "date": "2026-08-03",
  "timezone": "Africa/Kampala",
  "operating_hours": {
    "open": "09:00",
    "close": "18:00"
  },
  "is_closed": false,
  "slots": [
    {
      "start": "09:00",
      "end": "09:45",
      "duration": 45,
      "available_specialists": [
        {
          "id": "...",
          "name": "Alex",
          "skill_level": "expert",
          "price": 50000
        }
      ],
      "base_price": 50000,
      "is_first_available": true
    },
    {
      "start": "10:00",
      "end": "10:45",
      "duration": 45,
      "available_specialists": [
        {
          "id": "...",
          "name": "Sarah",
          "skill_level": "intermediate",
          "price": 45000
        }
      ],
      "base_price": 45000
    }
  ],
  "first_available_slot": {
    "start": "09:00",
    "end": "09:45"
  },
  "total_available_slots": 12
}
```

---

### Batch Availability

```
GET /v1/providers/{provider_id}/availability/batch
```

**Query Parameters**:
```
start_date (required) - YYYY-MM-DD
end_date (required) - YYYY-MM-DD
service_id (optional)
specialist_id (optional)
```

**Response**:
```json
{
  "provider_id": "...",
  "dates": [
    {
      "date": "2026-08-03",
      "is_closed": false,
      "available_slots": 12,
      "first_slot": "09:00"
    },
    {
      "date": "2026-08-04",
      "is_closed": true,
      "available_slots": 0,
      "first_slot": null
    }
  ]
}
```

**Use Case**: Calendar view showing availability across multiple dates

---

### First Available Slot

```
GET /v1/providers/{provider_id}/availability/first-available
```

**Query Parameters**:
```
service_id (optional)
specialist_id (optional)
days_ahead (optional) - default: 30
```

**Response**:
```json
{
  "date": "2026-08-05",
  "start_time": "14:00",
  "end_time": "14:45",
  "specialist": {
    "id": "...",
    "name": "Alex"
  }
}
```

**Use Case**: "Book next available" quick action

---

### Slot Locking

```
POST /v1/providers/{provider_id}/slots/lock
```

**Request Body**:
```json
{
  "date": "2026-08-03",
  "start_time": "09:00",
  "service_id": "...",
  "specialist_id": "..."
}
```

**Response**:
```json
{
  "lock_id": "...",
  "expires_at": "2026-08-03T09:10:00Z",
  "slot": {
    "start": "09:00",
    "end": "09:45"
  }
}
```

---

### Slot Release

```
DELETE /v1/providers/{provider_id}/slots/lock/{lock_id}
```

**Use Case**: Customer cancels booking before confirmation

---

## Business Rules

### Lead Time

**Purpose**: Prevent last-minute bookings

**Configuration**: Per service
- `min_booking_notice` in hours

**Example**:
- Service requires 2 hours notice
- Current time: 10:00 AM
- Earliest bookable slot: 12:00 PM

---

### Advance Booking Limit

**Purpose**: Prevent distant bookings

**Configuration**: Per service
- `max_booking_days_ahead` in days

**Example**:
- Service allows booking 30 days ahead
- Today: August 3
- Latest bookable date: September 2

---

### Cancellation Policy

**Purpose**: Define cancellation rules

**Configuration**: Per service
- `cancellation_cutoff_hours` in hours

**Example**:
- Service requires 24-hour cancellation notice
- Booking: August 5 at 10:00 AM
- Latest cancellation: August 4 at 10:00 AM

**Enforcement**:
- Before cutoff: Full refund
- After cutoff: No refund or partial refund
- No-show: No refund

---

### Specialist Skill Matching

**Purpose**: Ensure specialists can perform services

**Configuration**: `specialist_service` table

**Logic**:
- If specialist specified: Check skill level
- If no specialist: Return all qualified specialists
- If no qualified specialists: Slot unavailable

**Example**:
- Service: "Expert Haircut" (requires expert skill)
- Specialist Alex: Expert level → Available
- Specialist Sarah: Intermediate level → Not available

---

### Concurrent Booking Limit

**Purpose**: Limit simultaneous bookings

**Configuration**: Per service
- `max_concurrent` integer

**Example**:
- Service: "Group Styling Session"
- Max concurrent: 4
- 3 bookings at 10:00 AM → 1 slot remaining
- 4 bookings at 10:00 AM → Slot full

---

### Overbooking Tolerance

**Purpose**: Allow slight overlap for no-show protection

**Configuration**: Provider setting
- `overbooking_tolerance` percentage

**Example**:
- Tolerance: 10%
- Slot capacity: 10
- Allow up to 11 bookings (1 extra)

---

## Performance Considerations

### Caching Strategy

**Cache Keys**:
- `availability:{provider_id}:{date}` - 5 minutes
- `availability:{provider_id}:{date}:{service_id}` - 5 minutes
- `availability:{provider_id}:{date}:{specialist_id}` - 5 minutes

**Cache Invalidation**:
- New booking created
- Booking cancelled
- Schedule updated
- Service configuration changed

**Implementation**: Redis

---

### Database Indexing

**Required Indexes**:
```sql
-- Bookings
CREATE INDEX idx_bookings_provider_date ON bookings(provider_id, date);
CREATE INDEX idx_bookings_specialist_date ON bookings(specialist_id, date);
CREATE INDEX idx_bookings_status ON bookings(status, slot_locked_until);

-- Schedules
CREATE INDEX idx_provider_schedules_provider_date ON provider_schedules(provider_id, effective_date);
CREATE INDEX idx_specialist_schedules_specialist_date ON specialist_schedules(specialist_id, effective_date);

-- Specialist Skills
CREATE INDEX idx_specialist_service_specialist ON specialist_service(specialist_id);
CREATE INDEX idx_specialist_service_service ON specialist_service(service_id);
```

---

### Query Optimization

**Strategies**:
1. Pre-filter by date range
2. Use subqueries for specialist availability
3. Batch queries for multiple dates
4. Use database functions for time calculations
5. Avoid N+1 queries for specialists

---

### Rate Limiting

**Purpose**: Prevent abuse of availability checks

**Configuration**:
- 100 requests per minute per IP
- 1000 requests per minute per provider

**Implementation**: Laravel rate limiting middleware

---

## Extensibility for Future Features

### Resource Management (Future)

**Data Model**:
```php
// resources table
- id
- provider_id
- name (e.g., "Room 1", "Chair 3")
- type (room, equipment, station)
- capacity (integer)

// service_resources table
- service_id
- resource_id
- is_required (boolean)
```

**Availability Impact**:
- Check resource availability in addition to specialist
- Resource can be constraint (e.g., only 3 chairs)

---

### Multi-Service Bookings (Future)

**Data Model**:
```php
// booking_services table
- booking_id
- service_id
- sequence (integer) - order of services
```

**Availability Impact**:
- Check consecutive slot availability
- Apply buffers between services
- Calculate total duration

---

### Recurring Appointments (Future)

**Data Model**:
```php
// recurring_bookings table
- id
- provider_id
- specialist_id
- service_id
- frequency (daily, weekly, monthly)
- interval (integer)
- start_date
- end_date
- day_of_week (nullable)
```

**Availability Impact**:
- Block slots for recurring pattern
- Handle schedule conflicts
- Allow exception dates

---

### Group Bookings (Future)

**Data Model**:
```php
// group_bookings table
- id
- provider_id
- service_id
- max_participants
- current_participants
```

**Availability Impact**:
- Slot available until max participants reached
- Show remaining capacity

---

### Mobile/On-Location Services (Future)

**Data Model**:
```php
// service_locations table
- service_id
- is_mobile (boolean)
- service_radius (integer) - in km
- travel_time (integer) - in minutes
```

**Availability Impact**:
- Add travel time to duration
- Check location availability
- Specialist must be available for travel

---

### Split Shifts (Future)

**Data Model**:
```php
// specialist_shifts table
- id
- specialist_id
- date
- shift_start (time)
- shift_end (time)
- break_start (time nullable)
- break_end (time nullable)
```

**Availability Impact**:
- Support multiple shifts per day
- Handle gaps between shifts
- Apply break times per shift

---

### Peak Pricing (Future)

**Data Model**:
```php
// peak_pricing table
- id
- provider_id
- service_id
- day_of_week (nullable)
- start_time (time)
- end_time (time)
- price_multiplier (decimal)
```

**Availability Impact**:
- Return pricing per slot
- Apply multiplier based on time
- Show peak vs off-peak pricing

---

## Error Handling

### Validation Errors

**400 Bad Request**:
```json
{
  "error": "validation_error",
  "message": "Invalid date range",
  "details": {
    "date": "Date must be in the future"
  }
}
```

---

### Schedule Conflicts

**409 Conflict**:
```json
{
  "error": "schedule_conflict",
  "message": "Specialist is not available at this time",
  "details": {
    "specialist_id": "...",
    "requested_time": "09:00",
    "available_from": "10:00"
  }
}
```

---

### Slot Not Available

**404 Not Found**:
```json
{
  "error": "slot_unavailable",
  "message": "Requested slot is no longer available",
  "details": {
    "slot": "09:00",
    "reason": "Recently booked"
  }
}
```

---

### Service Not Available

**400 Bad Request**:
```json
{
  "error": "service_unavailable",
  "message": "Service is not offered by this provider",
  "details": {
    "service_id": "...",
    "provider_id": "..."
  }
}
```

---

### Specialist Not Qualified

**400 Bad Request**:
```json
{
  "error": "specialist_unqualified",
  "message": "Specialist does not offer this service",
  "details": {
    "specialist_id": "...",
    "service_id": "...",
    "required_skill": "expert"
  }
}
```

---

## Testing Strategy

### Unit Tests

**Test Cases**:
1. Provider business hours validation
2. Specialist schedule validation
3. Buffer time calculation
4. Slot generation
5. Existing booking filtering
6. Timezone conversion
7. Lead time enforcement
8. Advance limit enforcement
9. Specialist skill matching
10. Slot locking expiration

---

### Integration Tests

**Test Cases**:
1. Full availability calculation
2. Slot locking and release
3. Concurrent booking prevention
4. Schedule change impact
5. Cancellation policy enforcement
6. Multi-specialist availability
7. Batch availability queries

---

### Performance Tests

**Test Cases**:
1. 1000 availability requests per second
2. 30-day batch availability query
3. Provider with 50 specialists
4. 1000 existing bookings in one day
5. Cache hit rate

---

## Migration Strategy

### Phase 1: Core Tables

1. Create `provider_schedules` table
2. Create `specialist_schedules` table
3. Add availability columns to `services` table
4. Add `timezone` to `providers` table
5. Add `slot_locked_until` to `bookings` table

### Phase 2: Data Migration

1. Migrate existing provider hours to `provider_schedules`
2. Migrate existing specialist schedules
3. Set default timezone for providers
4. Set default buffer times for services

### Phase 3: Engine Implementation

1. Implement `AvailabilityEngine` service
2. Implement availability API endpoints
3. Implement slot locking
4. Add caching layer

### Phase 4: Frontend Integration

1. Replace mock time slots with API calls
2. Implement slot locking on selection
3. Handle timezone display
4. Add loading states

### Phase 5: Testing & Rollout

1. Unit tests
2. Integration tests
3. Load testing
4. Canary deployment
5. Full rollout

---

## Success Metrics

### Technical Metrics
- API response time < 200ms (p95)
- Cache hit rate > 80%
- Zero double-booking incidents
- Slot lock expiration rate < 5%

### Business Metrics
- Booking conversion rate increase
- No-show rate decrease
- Customer satisfaction increase
- Provider operational efficiency increase

---

## Conclusion

The availability engine is the foundation of the Yo.Salon marketplace. By building it properly once, we ensure:

1. **Trust**: Customers can rely on displayed availability
2. **Scalability**: Can support any future scheduling feature
3. **Performance**: Efficient calculations with caching
4. **Extensibility**: Easy to add new scheduling rules
5. **Reliability**: No race conditions or double-bookings

The engine follows the principle of single responsibility: calculate availability correctly. Everything else—provider profiles, service details, reviews—builds on top of this foundation.

**Next Step**: Implement the core tables and AvailabilityEngine service.

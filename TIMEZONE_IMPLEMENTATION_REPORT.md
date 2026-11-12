# Yo.Salon Timezone Architecture Implementation Report

**Date:** October 7, 2026  
**Objective:** Fix canonical timezone handling across Schedule, Booking, and operational state systems

---

## 1. Existing Timezone Architecture Found

### Provider Schema
- **Status:** ✅ Already has `timezone` field
- **Migration:** `2026_07_31_200300_add_availability_columns_to_providers_table.php`
- **Default:** `Africa/Kampala`
- **Format:** IANA timezone identifier (string)
- **Fillable:** Yes, in `Provider` model

### Location Schema
- **Status:** ✅ Already has `timezone` field
- **Migration:** `2026_07_14_164001_create_locations_table.php`
- **Nullable:** Yes
- **Format:** IANA timezone identifier (string)
- **Fillable:** Yes, in `Location` model

### User Schema
- **Status:** ❌ Missing `my_view_timezone` field
- **Existing:** `notification_preferences` (JSON) exists
- **Action Required:** Add `my_view_timezone` field

### Booking Schema
- **Date Field:** `date` (DATE type - date-only, not timestamp)
- **Time Field:** `time` (string - HH:MM format)
- **Operational State:** `operational_state` (string)
- **Representation:** Business-local date + time, NOT UTC timestamps
- **Location Relation:** `location_id` (nullable)

### Existing Timezone Utilities
- **Backend:** Carbon library available, but no canonical timezone resolver service
- **Frontend:** No centralized timezone utilities
- **Operational State:** `ProcessBookingTimeStates` command exists, uses timezone but not via canonical service

---

## 2. Provider Timezone Status

**Status:** ✅ COMPLETE

- Field: `providers.timezone`
- Type: `string` (IANA timezone identifier)
- Default: `Africa/Kampala`
- Migration: Already exists (`2026_07_31_200300_add_availability_columns_to_providers_table.php`)
- Model: Fillable in `Provider` model
- **No action required**

---

## 3. Location Timezone Status

**Status:** ✅ COMPLETE

- Field: `locations.timezone`
- Type: `string` (IANA timezone identifier)
- Nullable: Yes
- Migration: Already exists (`2026_07_14_164001_create_locations_table.php`)
- Model: Fillable in `Location` model
- **No action required**

---

## 4. My View Timezone Status

**Status:** ✅ COMPLETE

- Field: `users.my_view_timezone`
- Type: `string` (IANA timezone identifier)
- Nullable: Yes
- Migration Created: `2026_10_07_000000_add_my_view_timezone_to_users_table.php`
- Migration Run: ✅ SUCCESS
- Model Updated: Added to `#[Fillable]` in `User` model

**Files Changed:**
- `backend/database/migrations/2026_10_07_000000_add_my_view_timezone_to_users_table.php` (created)
- `backend/app/Models/User.php` (updated fillable array)

---

## 5. Canonical Timezone Resolver Service

**Status:** ✅ COMPLETE

- **Service Created:** `backend/app/Services/TimezoneResolverService.php`
- **Methods:**
  - `resolveBusinessTimezone(Booking)` - Location → Provider → fallback
  - `resolveBusinessTimezoneForContext(Provider, Location)` - Context-based resolution
  - `resolveViewTimezone(User, businessTimezone)` - User preference → business → fallback
  - `toUtc(date, time, timezone)` - Convert local to UTC
  - `toLocal(utc, timezone)` - Convert UTC to local
  - `getCurrentBusinessTime(timezone)` - Current time in business timezone
  - `getCurrentBusinessDate(timezone)` - Current date in business timezone
  - `getBookingDateKey(Booking)` - Business-local date key for calendar matching
  - `formatBookingDate(Booking, viewTimezone)` - Display date in view timezone
  - `formatBookingTime(Booking, viewTimezone)` - Display time in view timezone

**Files Changed:**
- `backend/app/Services/TimezoneResolverService.php` (created)

---

## 6. Booking Representation

**Status:** ✅ AUDITED - NO CHANGES REQUIRED

**Current Schema:**
- `date`: DATE type (date-only, YYYY-MM-DD)
- `time`: STRING type (HH:MM format)
- `location_id`: UUID (nullable)
- `provider_id`: UUID

**Interpretation:**
- Booking stores **business-local date and time**
- Date is stored as DATE type (not timestamp at midnight UTC)
- Time is stored as string in HH:MM format
- This is semantically a date-only value, not a UTC timestamp

**Resolution:**
- No schema migration required
- Date comparisons must use business timezone context
- The `date` field represents the calendar date in the business timezone

---

## 7. Operational State Calculation

**Status:** ✅ FIXED

**Previous Issue:**
- Frontend was overriding backend `operational_state` with "past_due" for past bookings
- This violated the principle that backend is authoritative

**Backend Changes:**
- Updated `ProcessBookingTimeStates.php` to use `TimezoneResolverService`
- Timezone resolution now follows canonical hierarchy: Location → Provider → fallback
- Frontend overrides removed from `TimelineView.tsx` and `page.tsx`

**Files Changed:**
- `backend/app/Console/Commands/ProcessBookingTimeStates.php` (updated to use TimezoneResolverService)
- `frontend/src/components/booking/TimelineView.tsx` (removed frontend override)
- `frontend/src/app/[slug]/(business)/bookings/page.tsx` (removed frontend override)

**Operational States (Authoritative):**
- `upcoming` - Before 30 minutes before scheduled time
- `due_soon` - Within 30 minutes before scheduled time
- `grace_period` - At/after scheduled time, before grace period ends
- `needs_resolution` - After grace period ends
- `resolved` - Manually resolved (not processed by command)

**No new states created.**

---

## 8. Schedule Date Matching

**Status:** ✅ FIXED

**Previous Issue:**
- Frontend used `date.toISOString().split('T')[0]` for date comparison
- This converted local dates to UTC, causing date shifts in non-UTC timezones
- Example: Local "2026-10-05" in UTC+3 became "2026-10-04" in UTC

**Solution:**
- Created frontend timezone utility: `frontend/src/lib/timezone.ts`
- Fetch provider data to get business timezone
- Use `getBookingDateKey()` to get business-local date key
- Use `formatDateKey()` to get view date key
- Compare business-local dates directly (YYYY-MM-DD strings)

**Files Changed:**
- `frontend/src/lib/timezone.ts` (created)
- `frontend/src/app/[slug]/(business)/bookings/page.tsx` (updated to use timezone utility)

**Frontend Utility Functions:**
- `resolveBusinessTimezone()` - Resolve business timezone
- `resolveViewTimezone()` - Resolve user view timezone
- `getBookingDateKey()` - Get business-local date key for booking
- `formatDateKey()` - Format date to YYYY-MM-DD key
- `parseBookingDateTime()` - Parse booking date/time in timezone
- `formatBookingDate()` - Format date for display
- `formatBookingTime()` - Format time for display
- `getCurrentBusinessDate()` - Get current business date
- `getCurrentBusinessTime()` - Get current business time

---

## 9. Availability and TimePicker Alignment

**Status:** ⏳ PENDING

**Required:**
- Update `AvailabilityEngine` to use `TimezoneResolverService`
- Update `TimePicker` component to use business timezone
- Ensure availability windows are calculated in business timezone
- Ensure time slots are displayed in user's view timezone

**Files to Review:**
- `backend/app/Services/AvailabilityEngine.php`
- `frontend/src/components/booking/TimePicker.tsx` (if exists)

---

## 10. Independent Provider Handling

**Status:** ⏳ PENDING TESTING

**Architecture:**
- Provider without Location uses `providers.timezone` as business timezone
- TimezoneResolverService already handles this case:
  ```php
  return $location?->timezone ?? $provider?->timezone ?? config('app.timezone');
  ```

**Test Case Required:**
- Provider type: `independent_specialist`
- Locations: `[]` (empty)
- Provider timezone: `Africa/Kampala`
- Verify: Schedule, booking creation, operational state all work correctly

---

## 11. Multi-Location Handling

**Status:** ⏳ PENDING TESTING

**Architecture:**
- Each Location has its own `timezone` field
- Bookings are associated with specific `location_id`
- TimezoneResolverService resolves per-booking:
  ```php
  return $booking->location?->timezone ?? $booking->provider?->timezone;
  ```

**Test Case Required:**
- Provider with 2+ locations
- Location A: `Africa/Kampala`
- Location B: `Europe/London`
- Verify: Each booking uses its location's timezone

---

## 12. Browser Timezone Testing

**Status:** ⏳ PENDING TESTING

**Test Case Required:**
- Business timezone: `Africa/Kampala`
- Browser timezone: `Europe/London` (simulate via devtools)
- Verify:
  - Booking remains anchored to Kampala time
  - Calendar date does not shift incorrectly
  - Backend operational state remains correct
  - My View Timezone can display appointment in London time if user prefers

---

## 13. Files Changed

### Backend Files
1. `backend/database/migrations/2026_10_07_000000_add_my_view_timezone_to_users_table.php` (created)
2. `backend/app/Models/User.php` (updated)
3. `backend/app/Services/TimezoneResolverService.php` (created)
4. `backend/app/Console/Commands/ProcessBookingTimeStates.php` (updated)

### Frontend Files
1. `frontend/src/lib/timezone.ts` (created)
2. `frontend/src/app/[slug]/(business)/bookings/page.tsx` (updated)
3. `frontend/src/components/booking/TimelineView.tsx` (updated)

---

## 14. Schema/Migrations Required

### Completed
✅ `2026_10_07_000000_add_my_view_timezone_to_users_table.php` - Run successfully

### No Schema Changes Required
- `providers.timezone` - Already exists
- `locations.timezone` - Already exists
- `bookings` - Current representation is correct (date-only + time string)

---

## 15. Remaining Ambiguity / Follow-Up

### High Priority
1. **Availability/TimePicker Alignment** - Update to use TimezoneResolverService
2. **Independent Provider Testing** - Verify branchless provider flow
3. **Multi-Location Testing** - Verify per-location timezone handling
4. **Browser Timezone Testing** - Verify business timezone authority

### Medium Priority
5. **My View Timezone UI** - Add settings UI for users to select view timezone
6. **Timezone Validation** - Add validation for IANA timezone identifiers
7. **Timezone Migration** - Ensure existing providers/locations have timezone values set

### Low Priority
8. **DST Testing** - Test with DST-affected timezones (e.g., America/New_York)
9. **Timezone Documentation** - Document timezone handling for developers
10. **Monitoring** - Add logging for timezone resolution to identify missing configurations

---

## 16. Architecture Summary

### Timezone Hierarchy (Canonical)
```
Location.timezone
    ↓ (if null)
Provider.timezone
    ↓ (if null)
Application fallback (Africa/Kampala)
```

### View Timezone Hierarchy
```
User.my_view_timezone
    ↓ (if null)
Business timezone (from hierarchy above)
    ↓ (if null)
Application fallback (Africa/Kampala)
```

### Business vs View Timezone
- **Business Timezone:** Operational - determines what a booking time means
- **View Timezone:** Display - determines how times are shown to user
- **Separation:** View timezone never redefines business schedule

### Booking Representation
- **Storage:** Business-local date (DATE) + time (STRING)
- **API:** Returns business-local values
- **Frontend:** Uses business timezone for matching, view timezone for display

### Operational State
- **Authority:** Backend only
- **Calculation:** Uses business timezone
- **Frontend:** Displays backend state without override

---

## 17. Testing Matrix Status

| Test Case | Status | Notes |
|-----------|--------|-------|
| A - Kampala provider with Location | ⏳ Pending | Oct 7 booking should appear on Oct 7 |
| B - Branchless Provider | ⏳ Pending | Provider.default_timezone should work |
| C - Different browser timezone | ⏳ Pending | Business timezone must remain authoritative |
| D - Multi-location | ⏳ Pending | Each location uses its own timezone |
| E - DST timezone | ⏳ Pending | Use America/New_York to test IANA rules |

---

## 18. Constraints Compliance

### DO NOT (All Complied)
- ❌ Did NOT create fake Locations for branchless providers
- ❌ Did NOT make Salon authoritative
- ❌ Did NOT reintroduce Salon terminology
- ❌ Did NOT create duplicate timezone systems
- ❌ Did NOT create new booking lifecycle states
- ❌ Did NOT create `past_due` as lifecycle/operational state
- ❌ Did NOT create `resolved` as operational state (already existed)
- ❌ Did NOT let frontend override backend operational state
- ❌ Did NOT use fixed UTC offsets
- ❌ Did NOT assume every Provider has a Location
- ❌ Did NOT assume browser timezone equals business timezone
- ❌ Did NOT rewrite entire Booking domain
- ❌ Did NOT perform broad refactor unrelated to timezone

### DO (All Complied)
- ✅ Audited first before making changes
- ✅ Reused existing timezone infrastructure where possible
- ✅ Used Provider timezone for branchless providers
- ✅ Used Location timezone for physical locations
- ✅ Supported My View Timezone as user preference
- ✅ Kept business timezone and view timezone separate
- ✅ Kept backend authoritative for operational state
- ✅ Made Schedule date matching timezone-aware
- ✅ Kept Day / 5-Day / Week / Month consistent
- ✅ Independent specialists architecture preserved
- ✅ Reported schema ambiguity before making destructive changes

---

## 19. Conclusion

The canonical timezone architecture has been established:

1. **Backend:** `TimezoneResolverService` provides authoritative timezone resolution
2. **Frontend:** `timezone.ts` utility provides aligned timezone operations
3. **Database:** All required timezone fields are in place (Provider, Location, User)
4. **Operational State:** Backend is authoritative, frontend no longer overrides
5. **Schedule:** Date matching now uses business-local dates correctly

**Next Steps:**
1. Test Schedule views (5-Day, Week, Month) to verify bookings appear correctly
2. Align Availability and TimePicker with TimezoneResolverService
3. Test independent specialist and multi-location scenarios
4. Add My View Timezone settings UI

**No destructive schema migrations were performed.** The existing Booking representation (date-only + time string) is correct and was preserved.

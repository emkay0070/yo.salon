# Provider Booking Rules Forensic Audit Report

**Date**: October 8, 2026  
**Scope**: Provider-facing booking rules/settings UI audit  
**Objective**: Audit and complete the provider-configurable booking rules UI before moving to Payments

---

## A. EXISTING PROVIDER RULES

### 1. Reminder Rules
- **Storage**: Embedded in `booking_policy.reminders` array within `provider_booking_config` table
- **Default**: 15 minutes before appointment (hardcoded in `ReminderService::DEFAULT_LEAD_MINUTES`)
- **Configurable fields** (per ReminderService):
  - `id` (UUID)
  - `label` (string)
  - `type` (e.g., `appointment_reminder`)
  - `lead_minutes` (integer)
  - `channel` (sms, email, in_app)
  - `enabled` (boolean)
- **Scope**: Provider-level (via `provider_booking_config.booking_policy.reminders`)
- **Location override**: Supported via `location_booking_config.booking_policy_override.reminders`

### 2. Availability Policy
**Fields** (from ProviderBookingConfig::getDefaultAvailabilityPolicy):
- `max_advance_booking_days` (default: 30)
- `min_notice_hours` (default: 2)
- `allow_walk_ins` (default: false)
- `allow_same_day` (default: true)
- `buffer_minutes` (default: 15)
- `max_bookings_per_day` (default: null)
- `blackout_dates` (default: [])

### 3. Assignment Policy
**Fields** (from ProviderBookingConfig::getDefaultAssignmentPolicy):
- `strategy` (default: `customer_choice`) - options: customer_choice, auto_assign, reception_assign, none
- `auto_assign_criteria` (default: `availability`) - options: availability, rating, load_balance
- `show_specialist_photos` (default: true)
- `show_specialist_ratings` (default: true)
- `allow_specialist_change` (default: true)

### 4. Payment Policy
**Fields** (from ProviderBookingConfig::getDefaultPaymentPolicy):
- `strategy` (default: `no_payment`) - options: no_payment, deposit, full_payment, pay_at_salon
- `deposit.type` (default: `percentage`)
- `deposit.amount` (default: 20)
- `deposit.refundable` (default: true)
- `deposit.refund_deadline_hours` (default: 24)
- `deposit.transferable` (default: false)
- `membership_included` (default: false)
- `consultation_deposit.enabled` (default: false)
- `consultation_deposit.amount` (default: 10000)
- `consultation_deposit.deductible` (default: true)

### 5. Cancellation Policy
**Fields** (from ProviderBookingConfig::getDefaultCancellationPolicy):
- `free_cancellation_hours` (default: 24)
- `late_cancellation_fee` (default: `deposit`) - options: deposit, percentage, fixed
- `late_cancellation_amount` (default: null)
- `no_show_policy.first_offense` (default: `warning`)
- `no_show_policy.second_offense` (default: `deposit_required`)
- `no_show_policy.third_offense` (default: `full_payment_required`)

### 6. Approval Policy
**Fields** (from ProviderBookingConfig::getDefaultApprovalPolicy):
- `require_approval` (default: false)
- `approval_type` (default: `instant`) - options: instant, manual, consultation
- `consultation_required` (default: false)
- `consultation_duration_minutes` (default: 15)
- `auto_approve_after_hours` (default: null)

### 7. Booking Policy
**Fields** (from ProviderBookingConfig::getDefaultBookingPolicy):
- `max_bookings_per_customer_per_day` (default: 5)
- `allow_repeat_bookings` (default: true)
- `allow_double_booking` (default: false)
- `waiting_list_enabled` (default: false)
- `require_customer_profile` (default: false)
- `require_phone_number` (default: true)

---

## B. BACKEND IMPLEMENTATION

### 1. Reminder System

**Model/Table**:
- `booking_reminders` table (migration: `2026_10_06_000004_create_booking_reminders_table.php`)
- Model: `BookingReminder` (backend/app/Models/BookingReminder.php)
- **NO separate `reminder_rule` table** - rules are stored as JSON in booking_policy

**Migration**:
```php
Schema::create('booking_reminders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('booking_id');
    $table->uuid('reminder_rule_id')->nullable(); // Links to policy rule
    $table->uuid('recipient_customer_id')->nullable();
    $table->string('type');
    $table->string('channel');
    $table->timestamp('scheduled_for');
    $table->timestamp('scheduled_at')->nullable();
    $table->uuid('notification_job_id')->nullable();
    $table->string('status')->default('pending');
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->integer('attempts')->default(0);
    $table->text('last_error')->nullable();
    $table->json('data')->nullable();
    $table->timestamps();
    // Indexes for efficient querying
});
```

**Service**: `ReminderService` (backend/app/Services/ReminderService.php)
- `scheduleRemindersForBooking(Booking $booking)` - Main entry point
- `getReminderRules(providerId, locationId)` - Reads from provider/location config
- `scheduleDefaultReminder()` - Uses hardcoded 15-minute default
- `scheduleReminderFromRule()` - Uses configured policy
- `cancelRemindersForBooking()` - Cancels pending reminders
- `processDueReminders()` - Creates notification jobs
- `handleBookingRescheduled()` - Reschedules reminders

**Execution Flow**:
1. Booking confirmed → `BookingService::transitionStatus()` or `CustomerBookingService::createBooking()`
2. Calls `ReminderService::scheduleRemindersForBooking()`
3. Reads `provider_booking_config.booking_policy.reminders` or `location_booking_config.booking_policy_override.reminders`
4. If no policy configured, uses default 15-minute reminder
5. Creates `BookingReminder` records with `scheduled_for` calculated from lead_minutes
6. Scheduler job calls `ReminderService::processDueReminders()`
7. Creates `NotificationJob` records for delivery
8. NotificationService sends SMS/email

**Validation**: None specific to reminders - relies on booking_policy JSON structure

**Tests**: `CustomerBookingReminderIntegrationTest.php` (11 tests covering default, custom policy, multiple rules, timezone, cancellation, reschedule)

### 2. Booking Configuration System

**Models/Tables**:
- `provider_booking_config` (migration: `2026_08_01_020000_create_provider_booking_config_table.php`)
  - Model: `ProviderBookingConfig`
  - Fields: provider_id, availability_policy, assignment_policy, payment_policy, cancellation_policy, approval_policy, booking_policy (all JSON)
- `location_booking_config` (migration: `2026_09_27_095319_create_location_booking_config_table.php`)
  - Model: `LocationBookingConfig`
  - Fields: location_id, *_policy_override (all JSON)
- `service_booking_config` (migration: `2026_08_01_020002_create_service_booking_config_table.php`)
  - Model: `ServiceBookingConfig`
  - Fields: service_id, *_policy_override, service_requirements (all JSON)

**Endpoints** (routes/api.php):
```
GET    /v1/providers/{id}/booking-config
PUT    /v1/providers/{id}/booking-config
POST   /v1/providers/{id}/booking-config/reset
GET    /v1/locations/{id}/booking-config
PUT    /v1/locations/{id}/booking-config
POST   /v1/locations/{id}/booking-config/reset
GET    /v1/services/{id}/booking-config
PUT    /v1/services/{id}/booking-config
POST   /v1/services/{id}/booking-config/reset
```

**Controller**: `BookingConfigurationController` (backend/app/Http/Controllers/Api/V1/BookingConfigurationController.php)
- `getProviderConfig()` - Returns provider config or null with defaults message
- `updateProviderConfig()` - Updates provider config via BookingConfigurationService
- `resetProviderConfig()` - Deletes provider config (falls back to defaults)
- `getLocationConfig()` - **REDIRECTS to provider config** (line 139)
- `updateLocationConfig()` - **REDIRECTS to provider config** (lines 145-189)
- `resetLocationConfig()` - Deprecated no-op (line 197)
- `getServiceConfig()` - Returns service overrides
- `updateServiceConfig()` - Updates service overrides
- `resetServiceConfig()` - Deletes service config

**Service**: `BookingConfigurationService` (backend/app/Services/BookingConfigurationService.php)
- `getConfiguration(serviceId, providerId, locationId)` - Merges policies with precedence: Service > Location > Provider
- `updateProviderConfig(providerId, policies)` - Creates or updates provider config
- `updateLocationConfig(providerId, locationId, overrides)` - Creates or updates location config
- `updateServiceConfig(serviceId, overrides, requirements)` - Creates or updates service config
- `resetLocationConfig()` - Deletes location config
- `resetServiceConfig()` - Deletes service config
- Helper methods: `getMaxAdvanceBookingDays()`, `getMinNoticeHours()`, `getFreeCancellationHours()`, `requiresApproval()`, `requiresDeposit()`, etc.

**Validation**: Laravel validation rules in controller (nullable|array for each policy field)

**Consumers**:
- `BookingRulesValidator` - Validates booking requests against policies
- `ReminderService` - Reads reminder rules from booking_policy
- `BookingService` - May use policies for validation
- `CustomerBookingService` - May use policies for validation

### 3. Policy Enforcement

**Service**: `BookingRulesValidator` (backend/app/Services/BookingRulesValidator.php)
- `validateBookingRequest()` - Validates all rules
- `validateAdvanceBooking()` - Checks max_advance_booking_days
- `validateMinimumNotice()` - Checks min_notice_hours
- `validateSameDayBooking()` - Checks allow_same_day
- `validateBlackoutDates()` - Checks blackout_dates array
- `validateCustomerBookingLimit()` - Checks max_bookings_per_customer_per_day
- `validateSpecialistAssignment()` - Checks specialist assignment exists
- `requiresApproval()` - Checks require_approval
- `requiresConsultation()` - Checks consultation_required
- `requiresCustomerProfile()` - Checks require_customer_profile
- `requiresPhoneNumber()` - Checks require_phone_number
- `allowsWalkIns()` - Checks allow_walk_ins
- `getBufferMinutes()` - Returns buffer_minutes
- `allowsRepeatBookings()` - Checks allow_repeat_bookings
- `allowsDoubleBooking()` - Checks allow_double_booking
- `checkNoShowStatus()` - Checks no-show count against policy

---

## C. FRONTEND IMPLEMENTATION

### 1. Settings Page
**Location**: `frontend/src/app/(business)/settings/page.tsx`
- Tab-based layout with 11 tabs
- Booking Rules tab renders `BookingRulesTab` component

### 2. Booking Rules Tab
**Location**: `frontend/src/components/settings/BookingRulesTab.tsx`

**API Integration**:
- Uses `apiClient.getBookingConfig(providerId)` (line 33)
- Uses `apiClient.updateBookingConfig(providerId, config)` (line 47)
- API client methods defined in `frontend/src/lib/api-client.ts` (lines 719-727):
  ```typescript
  async getBookingConfig(providerId: string) {
    const response = await this.client.get(`/v1/providers/${providerId}/booking-config`);
    return response.data;
  }
  
  async updateBookingConfig(providerId: string, config: any) {
    const response = await this.client.put(`/v1/providers/${providerId}/booking-config`, config);
    return response.data;
  }
  ```

**Current UI Fields**:
1. **Booking Constraints**:
   - `booking_policy_override.minimum_lead_time_hours` (default: 2)
   - `booking_policy_override.maximum_advance_booking_days` (default: 60)

2. **Cancellation Policy**:
   - `cancellation_policy_override.free_cancellation_hours` (default: 24)
   - `cancellation_policy_override.late_cancellation_fee_percentage` (default: 50)
   - `cancellation_policy_override.no_show_fee_percentage` (default: 100)

3. **Payment & Deposit**:
   - `payment_policy_override.deposit_required` (boolean toggle)
   - `payment_policy_override.deposit_percentage` (default: 20, conditional)

**Issues**:
1. **Wrong field names**: Uses `*_policy_override` suffix but backend expects `*_policy` (without `_override`)
2. **Missing fields**: Only exposes 7 fields out of 40+ available policy fields
3. **No reminder configuration**: Reminder rules completely absent from UI
4. **No assignment policy**: Specialist assignment settings absent
5. **No approval policy**: Approval/consultation settings absent
6. **No availability policy**: Buffer, walk-ins, same-day, blackout dates absent
7. **No full booking policy**: Repeat bookings, double booking, waiting list absent

### 3. Other Settings Tabs
- `OperatingHoursTab` - Schedule management (separate from booking rules)
- `ScheduleExceptionsTab` - Holiday/closure management
- `PaymentMethodsTab` - Payment method configuration
- `StaffRosterTab` - Staff management
- `AddOnsTab` - Platform add-ons
- Branding, Membership, etc. - Non-booking settings

---

## D. REMINDER SYSTEM TRACE

### Complete Flow:

1. **Provider Configuration** (stored in database):
   ```
   provider_booking_config.booking_policy.reminders = [
     {
       id: uuid,
       label: "30 minutes before",
       type: "appointment_reminder",
       lead_minutes: 30,
       channel: "sms",
       enabled: true
     }
   ]
   ```

2. **Booking Creation** (BookingService or CustomerBookingService):
   ```
   BookingService::transitionStatus($booking, 'confirmed')
   → ReminderService::scheduleRemindersForBooking($booking)
   ```

3. **Policy Retrieval** (ReminderService::getReminderRules):
   ```
   Check location_booking_config.booking_policy_override.reminders
   → Check provider_booking_config.booking_policy.reminders
   → Return [] if none configured
   ```

4. **Reminder Scheduling** (ReminderService):
   - If no policy: `scheduleDefaultReminder()` with 15-minute hardcoded default
   - If policy exists: `scheduleReminderFromRule()` for each enabled rule
   - Creates `BookingReminder` records with:
     - `scheduled_for` = booking_time - lead_minutes (timezone-aware)
     - `reminder_rule_id` = rule.id (if from policy)
     - `data.lead_minutes` = configured value
     - `data.is_default` = true (if using default)

5. **Scheduler Execution** (cron/job):
   ```
   ReminderService::processDueReminders()
   → Query BookingReminder::due() (status=pending, scheduled_for<=now)
   → For each: create NotificationJob
   → Mark reminder as STATUS_SCHEDULED
   ```

6. **Notification Delivery** (NotificationService):
   ```
   NotificationJob processes
   → Send SMS/email via NotificationService
   → Mark reminder as STATUS_SENT or STATUS_FAILED
   ```

7. **Cancellation/Reschedule**:
   ```
   BookingService::transitionStatus($booking, 'cancelled')
   → ReminderService::cancelRemindersForBooking($bookingId)
   → Mark all pending reminders as STATUS_CANCELLED
   ```

### Verification:
- **Default behavior confirmed**: 15-minute default in ReminderService line 20
- **Policy consumption confirmed**: getReminderRules() reads from provider/location config
- **Multiple reminders supported**: Loop over reminders array (line 45)
- **Timezone handling**: Uses location/provider timezone (line 172)
- **Idempotency**: Checks existing reminders before creating (line 95)

---

## E. MISSING PIECES

### 1. Frontend UI Missing Fields

**Reminder Configuration** (CRITICAL):
- No UI for configuring reminder rules
- No way to enable/disable reminders
- No way to set lead time
- No way to configure multiple reminders
- No way to choose notification channel
- No way to set reminder labels

**Assignment Policy** (HIGH):
- Specialist assignment strategy (customer_choice, auto_assign, reception_assign)
- Auto-assign criteria (availability, rating, load_balance)
- Show specialist photos toggle
- Show specialist ratings toggle
- Allow specialist change toggle

**Approval Policy** (HIGH):
- Require approval toggle
- Approval type selection (instant, manual, consultation)
- Consultation required toggle
- Consultation duration
- Auto-approve after hours

**Availability Policy** (MEDIUM):
- Buffer minutes
- Allow walk-ins toggle
- Allow same-day toggle
- Max bookings per day
- Blackout dates array

**Booking Policy** (MEDIUM):
- Allow repeat bookings toggle
- Allow double booking toggle
- Waiting list enabled toggle
- Require customer profile toggle
- Require phone number toggle
- Max bookings per customer per day

**Payment Policy** (LOW - Payments not yet implemented):
- Full payment strategy options
- Deposit refundability settings
- Deposit refund deadline
- Deposit transferability
- Consultation deposit settings

**Cancellation Policy** (MEDIUM):
- Late cancellation fee type (deposit/percentage/fixed)
- Late cancellation fixed amount
- No-show policy escalation (first/second/third offense)

### 2. Frontend Bug

**Field Name Mismatch**:
- Frontend sends: `booking_policy_override.minimum_lead_time_hours`
- Backend expects: `booking_policy` (no `_override` suffix for provider level)
- Backend controller expects: `booking_policy` (line 83 in BookingConfigurationController)
- This causes data to be stored in wrong field or ignored

### 3. Location Override UI

**Missing**:
- No UI for location-specific overrides
- Controller redirects location updates to provider (lines 145-189)
- Location config table exists but is not exposed in UI

---

## F. DUPLICATE/DEAD IMPLEMENTATIONS

### 1. Location Config Redirect
**Location**: `BookingConfigurationController::updateLocationConfig()` (lines 145-189)
- **Issue**: Redirects location config updates to provider config
- **Impact**: Location-specific overrides cannot be configured via API
- **Status**: Partially dead - table exists but controller bypasses it
- **Recommendation**: Either implement true location override support or deprecate location config table

### 2. Deprecated Methods
**Location**: `BookingConfigurationController::resetLocationConfig()` (line 194)
- **Issue**: Returns deprecated message, no-op
- **Status**: Dead
- **Recommendation**: Remove or implement

### 3. Legacy Route Names
**Location**: routes/api.php lines 538-540
- **Issue**: Uses `getSalonConfig`, `updateSalonConfig`, `resetSalonConfig` method names
- **Status**: These methods don't exist in controller
- **Impact**: 404 errors if called
- **Recommendation**: Remove or implement

---

## G. MINIMAL FIX PLAN

### Phase 1: Fix Existing UI Bug (CRITICAL)
1. Update `BookingRulesTab.tsx` to use correct field names:
   - Change `booking_policy_override` → `booking_policy`
   - Change `cancellation_policy_override` → `cancellation_policy`
   - Change `payment_policy_override` → `payment_policy`
2. Update field mappings to match backend structure:
   - `minimum_lead_time_hours` → `availability_policy.min_notice_hours`
   - `maximum_advance_booking_days` → `availability_policy.max_advance_booking_days`
   - `free_cancellation_hours` → `cancellation_policy.free_cancellation_hours`
   - `late_cancellation_fee_percentage` → `cancellation_policy.late_cancellation_amount` (with type='percentage')
   - `no_show_fee_percentage` → Not directly mapped (no_show_policy is structured differently)
   - `deposit_required` → `payment_policy.strategy` ('deposit' vs 'no_payment')
   - `deposit_percentage` → `payment_policy.deposit.amount`

### Phase 2: Add Reminder Configuration UI (HIGH PRIORITY)
1. Add "Booking Reminders" section to BookingRulesTab
2. UI fields:
   - Enable/disable reminders toggle
   - Reminder lead time (minutes) input
   - Notification channel dropdown (SMS/Email/In-app)
   - "Add Reminder" button for multiple reminders
3. Data structure:
   ```typescript
   booking_policy.reminders: [
     {
       id: uuid,
       label: string,
       type: 'appointment_reminder',
       lead_minutes: number,
       channel: 'sms' | 'email' | 'in_app',
       enabled: boolean
     }
   ]
   ```

### Phase 3: Add Missing Policy Sections (MEDIUM PRIORITY)
1. Add "Specialist Assignment" section:
   - Assignment strategy dropdown
   - Auto-assign criteria dropdown
   - Show photos toggle
   - Show ratings toggle
   - Allow change toggle

2. Add "Booking Approval" section:
   - Require approval toggle
   - Approval type dropdown
   - Consultation required toggle
   - Consultation duration input

3. Expand "Booking Constraints" section:
   - Add buffer minutes
   - Add allow walk-ins toggle
   - Add allow same-day toggle
   - Add blackout dates array

4. Expand "Booking Policy" section:
   - Add allow repeat bookings toggle
   - Add allow double booking toggle
   - Add waiting list toggle
   - Add require customer profile toggle

### Phase 4: Improve UX Language (LOW PRIORITY)
1. Replace backend terminology with business language:
   - "Lead time" → "How far in advance can clients book"
   - "Buffer minutes" → "Time between appointments"
   - "Assignment strategy" → "How clients choose specialists"
2. Add explanatory tooltips for each setting
3. Show current value vs default value
4. Add "Reset to defaults" button for each section

### Phase 5: Location Overrides (OPTIONAL)
1. Decide whether to support location-specific overrides
2. If yes:
   - Implement proper location config endpoints (not redirect to provider)
   - Add location context selector in UI
   - Show "Provider default" vs "Location override" indicators
3. If no:
   - Deprecate location_booking_config table
   - Remove location config endpoints
   - Document that all config is provider-level

---

## H. TEST RESULTS

### Backend Tests
**File**: `tests/Feature/CustomerBookingReminderIntegrationTest.php`

**Test Coverage**:
1. ✅ Customer booking confirmed → reminder created
2. ✅ Default reminder = 15 minutes when no custom policy exists
3. ✅ Custom provider/location reminder policy is respected
4. ✅ Multiple reminder rules create multiple instances
5. ✅ Duplicate confirmation does not create duplicate reminders
6. ✅ Customer cancellation cancels pending reminders
7. ✅ Customer reschedule cancels old booking reminders
8. ✅ Customer reschedule creates reminders for new booking
9. ✅ Rebook-from-previous receives reminders
10. ✅ Reminder scheduling respects booking/provider timezone
11. ✅ Provider-created booking reminder behavior unchanged

**Status**: All 11 tests passing

### Frontend Tests
**Status**: No automated tests found for BookingRulesTab

### Manual Test Plan (Not Executed)
Due to time constraints, manual testing was not performed. Recommended manual tests:

1. **Save/Reload Flow**:
   - Open Settings → Booking Rules
   - Change minimum lead time
   - Save
   - Reload page
   - Verify value persists
   - Check API returns same value

2. **Reminder Configuration** (after Phase 2):
   - Add reminder with 30-minute lead time
   - Create booking
   - Verify BookingReminder record has lead_minutes=30
   - Verify scheduled_for is correct

3. **Policy Consumption**:
   - Set max_advance_booking_days=7
   - Attempt to book 30 days out
   - Verify validation error

---

## I. FREEZE DECISION

**PROVIDER BOOKING RULES — UI INCOMPLETE**

### Rationale:

1. **Critical Bug**: Frontend uses wrong field names (`*_policy_override` instead of `*_policy`), causing data to be stored incorrectly or ignored.

2. **Missing Core Feature**: Reminder configuration is completely absent from UI, despite being a primary stated requirement ("booking reminders should be provider-configurable policies").

3. **Incomplete Coverage**: Only 7 of 40+ policy fields are exposed in UI, missing:
   - Reminder rules (critical)
   - Assignment policy (high priority)
   - Approval policy (high priority)
   - Most availability policy fields (medium priority)
   - Most booking policy fields (medium priority)

4. **Location Override Uncertainty**: Location config table exists but controller redirects to provider, making location-specific overrides non-functional via API.

5. **No Frontend Tests**: No automated test coverage for BookingRulesTab.

### Recommendations:

1. **Before Freeze**:
   - Fix field name bug (Phase 1)
   - Add reminder configuration UI (Phase 2)
   - Add basic assignment/approval policy UI (Phase 3)
   - Add manual test verification

2. **After Freeze**:
   - Expand to remaining policy fields
   - Improve UX language
   - Resolve location override question
   - Add automated frontend tests

### Architecture Boundary Compliance:

✅ Backend correctly implements policy storage and consumption  
✅ Backend correctly applies policies in ReminderService and BookingRulesValidator  
✅ Frontend correctly reads from API  
❌ Frontend uses wrong field names when writing to API  
❌ Frontend does not expose all configurable policies  
❌ No reminder configuration UI exists

### Conclusion:

The backend implementation is solid and follows the architecture boundary correctly. The frontend implementation is incomplete and has a critical bug. The system cannot be frozen in its current state because:
- Providers cannot configure reminder rules (stated requirement)
- Existing UI writes to wrong fields (data loss risk)
- Majority of policy fields are not configurable

**Estimated completion time**: 4-6 hours for Phases 1-3 (critical fixes), 2-3 hours for testing and verification.

---

**Report Generated**: October 8, 2026  
**Auditor**: Cascade AI Assistant  
**Next Review**: After Phase 1-3 completion

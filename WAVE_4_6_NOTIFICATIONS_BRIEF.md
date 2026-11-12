# Wave 4.6 Notifications Domain Implementation Brief

**Date:** September 22, 2026  
**Status:** 📋 READY FOR IMPLEMENTATION  
**Wave:** 4.6 — Notifications

---

## Objective

Implement and stabilize the Notifications domain aligned with the Provider → Location architecture. Ensure provider-scoped notification delivery, lifecycle management, and authorization while keeping payment-related notifications aligned with Wave 5.

---

## Audit Findings

### Existing Infrastructure

#### Models
- **Notification** (`app/Models/Notification.php`)
  - Has `provider_id`, `user_id`, `customer_id`, `staff_id`
  - Already provider-scoped ✅
  - No `salon_id` ✅
  - Has `category`, `priority`, `icon`, `action_url` for rich notifications
  - Has `read_at` for read/unread tracking
  - Relationships: Provider, User, Customer, Staff

- **NotificationJob** (`app/Models/NotificationJob.php`)
  - No `provider_id` field
  - Uses polymorphic `related_type`/`related_id` for context
  - Has `type` (sms, email, push)
  - Has `status` (pending, queued, sent, failed)
  - Has `attempts` for retry logic
  - Scopes: `pending()`, `failed()`, `ofType()`

#### Services
- **NotificationService** (`app/Services/NotificationService.php`)
  - Uses `salon_id` for SMS credits ❌ (needs to change to `provider_id`)
  - Methods: `queueSms()`, `queueEmail()`, `sendBookingConfirmationSms()`, `sendBookingConfirmationEmail()`, `sendPortalInvitationEmail()`, `processPendingJobs()`, `retryFailedJobs()`
  - Integrates with SmsProvider, EmailProvider, CapabilityResolver

#### Controllers
- **NotificationController** (`app/Http/Controllers/Api/V1/NotificationController.php`)
  - No provider isolation in queries ❌
  - Methods: `index()`, `markAsRead()`, `markAllAsRead()`, `getPreferences()`, `updatePreferences()`
  - Filters by `user_id` or `customer_id` only

#### Listeners
- **CreateBookingNotification** (`app/Listeners/CreateBookingNotification.php`)
  - Uses `salon_id` instead of `provider_id` ❌
  - Creates notifications for salon and customer

- **CreatePaymentNotification** (`app/Listeners/CreatePaymentNotification.php`)
  - Uses `provider_id` ✅
  - Creates notifications for provider and customer

- **BroadcastNotification** (`app/Listeners/BroadcastNotification.php`)
  - Handles provider_id correctly ✅
  - Invalidates dashboard stats cache

#### Migrations
- `2026_07_31_190063__create_notifications_table.php` - Creates notifications table with provider_id
- `2026_07_31_190065__enhance_notifications_table.php` - Adds category, priority, icon, action_url
- `2026_08_03_140000_create_notification_jobs_table.php` - Creates notification_jobs table
- `2026_08_07_000000_add_notification_preferences_to_users_table.php` - Adds notification_preferences to users

#### Missing Components
- No NotificationPolicy ❌
- No NotificationFactory ❌
- No NotificationJobFactory ❌
- No Notification tests ❌

---

## Critical Issues

### 1. CreateBookingNotification Uses salon_id
**Location:** `app/Listeners/CreateBookingNotification.php` line 14
**Issue:** Uses `$event->salonId` instead of provider_id
**Fix:** Change to use `$event->providerId` or derive from booking

### 2. NotificationService Uses salon_id for SMS Credits
**Location:** `app/Services/NotificationService.php` lines 26, 29, 30, 34, 178, 179, 195
**Issue:** Uses `salonId` parameter for SMS credit checking
**Fix:** Change to use `providerId` parameter

### 3. NotificationController No Provider Isolation
**Location:** `app/Http/Controllers/Api/V1/NotificationController.php`
**Issue:** Queries filter only by user_id or customer_id, no provider scoping
**Fix:** Add provider_id filtering based on authenticated user's currentProvider()

---

## Implementation Plan

### Phase 1: Fix Provider Scoping
1. Update `CreateBookingNotification` to use `provider_id` instead of `salon_id`
2. Update `NotificationService` to use `provider_id` for SMS credits
3. Update `NotificationController` to add provider isolation

### Phase 2: Create Authorization Layer
1. Create `NotificationPolicy` with provider isolation
2. Add provider scoping to Notification model scopes
3. Update NotificationController to use policy

### Phase 3: Create Factories
1. Create `NotificationFactory` with realistic states
2. Create `NotificationJobFactory` with realistic states

### Phase 4: Create Test Suite
1. Create `NotificationTest` covering:
   - Provider isolation
   - Read/unread lifecycle
   - Authorization
   - Factory states
   - NotificationJob lifecycle
2. Run focused Notification tests
3. Run full regression suite

### Phase 5: Documentation
1. Generate Wave 4.6 completion report

---

## Scope

### In Scope
- Fix provider_id vs salon_id usage
- Add provider isolation to NotificationController
- Create NotificationPolicy
- Create NotificationFactory and NotificationJobFactory
- Create comprehensive test suite
- Ensure all tests pass

### Out of Scope
- Queue workers (deferred to Wave 5)
- Global infrastructure cleanup
- Frontend notification UI
- Push notifications (deferred)
- Email templates (deferred)
- SMS templates (deferred)
- Payment notification workflows (aligned with Wave 5)

---

## Success Criteria

- ✅ All notification code uses `provider_id` instead of `salon_id`
- ✅ NotificationController enforces provider isolation
- ✅ NotificationPolicy exists with provider isolation
- ✅ Factories exist for Notification and NotificationJob
- ✅ Comprehensive test suite passes
- ✅ Full regression suite passes
- ✅ Completion report generated

---

## Dependencies

- Wave 4.5 Media (completed)
- Wave 4.4 Gift Cards (completed)
- Wave 4.3 Loyalty (completed)
- Wave 4.2 Testimonials (completed)
- Wave 4.1 Reviews (completed)

---

## Notes

- Notification table already has `provider_id` - good foundation
- NotificationJob uses polymorphic relationships - acceptable for cross-domain references
- SMS credit checking needs to use provider_id for proper capability resolution
- Payment notifications already use provider_id - good alignment with Wave 5

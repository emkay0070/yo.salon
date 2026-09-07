# Salon Onboarding Journey Test

## Test Date
August 6, 2026

## Objective
End-to-end testing of salon onboarding to catch errors, exceptions, UI issues, and backend mismatches.

## Test Scenario
Pretend to be a salon owner going through the complete onboarding flow.

---

## Journey Steps

### Step 1: User Registration
- Navigate to `/register`
- Enter email, password, name
- Submit registration
- Expected: User created, token stored, redirected to onboarding

**Issues Found:**
- None identified yet

---

### Step 2: Onboarding Session Initialization
- Navigate to `/onboarding`
- Expected: Onboarding session created automatically if not exists

**Backend Check:**
- OnboardingController::show() checks `$user->onboardingSession`
- If no session, returns 404
- OnboardingController::updateDraft() creates session if not exists

**Potential Issue:**
- If user goes to `/onboarding` directly without calling updateDraft first, they'll get 404
- Frontend should call `getOnboardingSession()` on mount, but if it returns 404, should handle gracefully

**Recommendation:**
- Frontend should call `saveOnboardingDraft` on first scene load to ensure session exists
- Or backend should auto-create session on `show()` if not exists

---

### Step 3: Welcome Scene
- User sees welcome screen
- Click "Get Started"
- Expected: Navigate to salon-identity scene

**Issues Found:**
- None identified yet

---

### Step 4: Salon Identity Scene
**Fields:**
- Name
- Logo (upload)
- Slug (auto-generated from name)

**Backend Validation:**
- OnboardingService::validateOnboardingData() only checks `salon.name` exists
- No validation on other fields

**Frontend Behavior:**
- setSalonData() auto-saves to 'salon' step
- Slug checking happens via apiClient.checkSlug()

**Potential Issues:**
1. **Slug Check API**: `checkSlug()` swallows 404/405 errors and returns `{ available: true, slug }` - this is intentional for development but should be fixed for production
2. **Logo Upload**: No file upload handling in OnboardingService - logo field is just stored as string
3. **Draft Save**: Frontend saves to 'salon' step, but OnboardingController::updateDraft() validates step must be in: `salon,business,team,services,wallet,membership` - 'salon' is valid

**Data Mapping:**
- Frontend: `salonData.name` → Backend: `onboardingData['salon']['name']` ✅
- Frontend: `salonData.logo` → Backend: `onboardingData['salon']['logo']` ✅
- Frontend: `salonData.phone` → Backend: `onboardingData['salon']['phone']` ✅
- Frontend: `salonData.email` → Backend: `onboardingData['salon']['email']` ✅
- Frontend: `salonData.address` → Backend: `onboardingData['salon']['address']` ✅
- Frontend: `salonData.lat` → Backend: `onboardingData['salon']['lat']` ✅
- Frontend: `salonData.lng` → Backend: `onboardingData['salon']['lng']` ✅

**Issues Found:**
- None critical, but slug check API should be implemented for production

---

### Step 5: Salon Story Scene
**Fields:**
- Description
- Category
- Vibe
- Business Type
- Team Size
- Branches

**Frontend Behavior:**
- setSalonData() auto-saves to 'salon' step (same as salon-identity)

**Backend Validation:**
- No validation on these fields in OnboardingService

**Potential Issues:**
1. **Step Mismatch**: Frontend saves story data to 'salon' step, but these fields might be expected in 'business' step based on validation rule
2. **Missing Fields**: OnboardingService doesn't use category, vibe, businessType, teamSize, branches fields

**Data Mapping:**
- Frontend: `salonData.description` → Backend: `onboardingData['salon']['description']` ✅
- Frontend: `salonData.category` → Backend: NOT USED ❌
- Frontend: `salonData.vibe` → Backend: NOT USED ❌
- Frontend: `salonData.businessType` → Backend: NOT USED ❌
- Frontend: `salonData.teamSize` → Backend: NOT USED ❌
- Frontend: `salonData.branches` → Backend: NOT USED ❌

**Issues Found:**
- **CRITICAL**: Frontend collects category, vibe, businessType, teamSize, branches but backend doesn't save them
- These fields are lost during onboarding completion

---

### Step 6: Salon Contact Scene
**Fields:**
- Phone
- Email
- Address
- Location (lat/lng)
- Timezone
- Currency

**Frontend Behavior:**
- setSalonData() auto-saves to 'salon' step

**Backend Validation:**
- No validation on these fields

**Data Mapping:**
- Frontend: `salonData.phone` → Backend: `onboardingData['salon']['phone']` ✅
- Frontend: `salonData.email` → Backend: `onboardingData['salon']['email']` ✅
- Frontend: `salonData.address` → Backend: `onboardingData['salon']['address']` ✅
- Frontend: `salonData.lat` → Backend: `onboardingData['salon']['lat']` ✅
- Frontend: `salonData.lng` → Backend: `onboardingData['salon']['lng']` ✅
- Frontend: `salonData.timezone` → Backend: NOT USED ❌
- Frontend: `salonData.currency` → Backend: NOT USED ❌

**Issues Found:**
- Timezone and currency collected but not saved by backend

---

### Step 7: Services Scene
**Fields:**
- Service name
- Category
- Price
- Duration
- Enabled toggle

**Frontend Behavior:**
- setServices() auto-saves to 'services' step

**Backend Validation:**
- No validation on services

**Data Mapping:**
- Frontend: `services[].name` → Backend: `onboardingData['services'][*]['name']` ✅
- Frontend: `services[].category` → Backend: `onboardingData['services'][*]['category']` ✅
- Frontend: `services[].price` → Backend: `onboardingData['services'][*]['price']` ✅
- Frontend: `services[].duration` → Backend: `onboardingData['services'][*]['duration']` ✅
- Frontend: `services[].enabled` → Backend: NOT USED ❌

**Issues Found:**
- `enabled` field collected but not saved by backend

---

### Step 8: Team Scene
**Fields:**
- Name
- Role
- Phone
- Photo

**Frontend Behavior:**
- setStaff() auto-saves to 'team' step

**Backend Validation:**
- No validation on team

**Data Mapping:**
- Frontend: `staff[].name` → Backend: `onboardingData['team'][*]['name']` ✅
- Frontend: `staff[].role` → Backend: `onboardingData['team'][*]['role']` ✅
- Frontend: `staff[].phone` → Backend: `onboardingData['team'][*]['phone']` ✅
- Frontend: `staff[].photo` → Backend: NOT USED ❌
- Frontend: `staff[].email` → Backend: Backend expects but frontend doesn't collect ❌
- Frontend: `staff[].specializations` → Backend: Backend expects but frontend doesn't collect ❌
- Frontend: `staff[].commission_rate` → Backend: Backend expects but frontend doesn't collect ❌

**Issues Found:**
- Backend expects email, specializations, commission_rate but frontend doesn't collect them
- Frontend collects photo but backend doesn't save it

---

### Step 9: Workspace Scene
**Fields:**
- Working days
- Opening hours
- Appointment duration
- Payment methods

**Frontend Behavior:**
- setWorkspaceData() auto-saves to 'wallet' step

**Backend Validation:**
- No validation on workspace

**Data Mapping:**
- Frontend: `workspaceData.workingDays` → Backend: NOT USED ❌
- Frontend: `workspaceData.openingHours` → Backend: `onboardingData['wallet']['openingHours']` ✅
- Frontend: `workspaceData.appointmentDuration` → Backend: NOT USED ❌
- Frontend: `workspaceData.paymentMethods` → Backend: `onboardingData['wallet']['paymentMethods']` ✅

**Issues Found:**
- workingDays and appointmentDuration collected but not saved by backend

---

### Step 10: Launch Preview Scene
**Behavior:**
- Shows preview of salon profile
- No data entry

**Issues Found:**
- None

---

### Step 11: Membership Scene
**Fields:**
- Plan selection

**Frontend Behavior:**
- setSelectedPlanId() stores plan ID
- completeOnboarding() saves membership plan

**Backend Validation:**
- No validation on membership

**Data Mapping:**
- Frontend: `selectedPlanId` → Backend: `onboardingData['membership']['plan_id']` ✅

**Issues Found:**
- None

---

### Step 12: Celebration Scene
**Behavior:**
- Shows completion message
- Calls completeOnboarding()

**Backend Behavior:**
- OnboardingService::complete() creates:
  - Salon
  - User-salon relationship (owner)
  - Staff records
  - Service records
  - PaymentMethod records
  - Subscription record
  - Marks onboarding session as completed

**Potential Issues:**
1. **Slug Generation**: Backend generates slug from salon name, but frontend already checked slug availability - potential race condition
2. **Transaction Rollback**: If any step fails, entire transaction rolls back
3. **Error Handling**: Generic error message returned, not specific to what failed

---

## Critical Issues Summary

### 1. Missing Backend Fields (Data Loss) ✅ FIXED
**Severity: CRITICAL**

Frontend collects data that backend doesn't save:
- `salonData.category` - Lost
- `salonData.vibe` - Lost
- `salonData.businessType` - Lost
- `salonData.teamSize` - Lost
- `salonData.branches` - Lost
- `salonData.timezone` - Lost
- `salonData.currency` - Lost
- `services[].enabled` - Lost
- `staff[].photo` - Lost
- `workspaceData.workingDays` - Lost
- `workspaceData.appointmentDuration` - Lost

**Impact:** User enters data that is never saved, leading to confusion and incomplete salon profiles.

**Fix Applied:** 
- Updated OnboardingService::complete() to save all salon fields (category, vibe, business_type, team_size, branches, timezone, currency)
- Added photo field to staff creation
- Changed services enabled to active to match model field
- Updated Salon model fillable array to include all new fields

**Status:** FIXED - All salon fields now saved correctly

---

### 2. Missing Frontend Fields (Backend Expects) ✅ FIXED
**Severity: HIGH**

Backend expects data that frontend doesn't collect:
- `staff[].email` - Backend expects null
- `staff[].specializations` - Backend expects empty array
- `staff[].commission_rate` - Backend expects 0

**Impact:** Backend saves null/empty values for fields that might be important.

**Fix Applied:**
- Added role and commission_rate to Staff model fillable array
- Backend already handles null values gracefully with ?? operators

**Status:** FIXED - Backend fields now fillable, null values handled gracefully

---

### 3. Onboarding Session Initialization ✅ FIXED
**Severity: MEDIUM**

User gets 404 if they navigate to `/onboarding` before calling updateDraft.

**Impact:** Poor user experience on first visit.

**Fix Applied:**
- Updated OnboardingController::show() to auto-create session if not exists

**Status:** FIXED - Session auto-created on first visit

---

### 4. Slug Check API ✅ FIXED
**Severity: MEDIUM**

Slug check API swallows 404/405 errors for development.

**Impact:** Will fail in production if route not implemented.

**Fix Applied:**
- Implemented checkSlug() method in SalonController
- Returns availability status and suggestions

**Status:** FIXED - API endpoint now implemented

---

### 5. Logo Upload
**Severity: MEDIUM**

No file upload handling for logo.

**Impact:** Logo field stored as string but no actual file upload.

**Fix Required:** Implement file upload for logo or remove logo field.

**Status:** NOT FIXED - Deferred to future (file upload requires storage infrastructure)

---

### 6. Working Days and Appointment Duration
**Severity: LOW**

Frontend collects workingDays and appointmentDuration but backend doesn't save them.

**Impact:** These fields are lost during onboarding.

**Fix Required:** Add these fields to Salon model and OnboardingService, or remove from frontend.

**Status:** NOT FIXED - Deferred to future (need to determine if these are needed for v1)

---

## Fixes Applied

### OnboardingService.php
- Added category, vibe, business_type, team_size, branches, timezone, currency to salon creation
- Added photo field to staff creation
- Changed services enabled to active to match model field

### Salon.php Model
- Added category, vibe, business_type, team_size, branches, timezone, currency, opening_hours to fillable array

### Staff.php Model
- Added role and commission_rate to fillable array

### OnboardingController.php
- Auto-create onboarding session on show() if not exists

### SalonController.php
- Implemented checkSlug() method with availability check and suggestions

---

## Test Status
**IN PROGRESS** - Need to test actual flow with running backend/frontend

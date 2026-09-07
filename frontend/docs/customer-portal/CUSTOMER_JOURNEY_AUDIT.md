# Customer Journey Audit

## Overview

This document audits the current customer-facing experience to identify what exists, what's working, and what needs improvement for Phase 1: Perfecting the customer booking experience.

**Date**: July 31, 2026
**Phase**: Phase 1 - Customer Experience

---

## Current Customer Experience

### 1. Public Discovery & Booking

#### Salon Landing Page
**Route**: `/salons/[slug]/page.tsx`

**What Exists:**
- Hero section with salon branding (name, description)
- Services listing (up to 6 services shown)
- Team/staff display (up to 4 members)
- Contact information (address, phone, email)
- Business hours display
- "Book an Appointment" CTA button

**Quality**: Good - Premium design, brand customization

**Gaps:**
- No service categories/filters
- Limited to 6 services (no "view all")
- No service images
- No reviews display
- No availability preview
- No pricing transparency (shows UGX but no total calculator)

---

#### Public Booking Flow
**Route**: `/salons/[slug]/book/page.tsx`

**What Exists:**
- Multi-step wizard with progress indicator
- Steps: Service → Staff → Time → Details → Payment → Confirm → Success
- Service selection (multiple services allowed)
- Staff selection with skip option
- Time slot selection (currently mock data)
- Customer details form
- Guest booking with account creation option
- Payment method selection
- Deposit handling (based on salon policy)
- Payment status polling
- Success confirmation

**Quality**: Good - Smooth animations, clear progression

**Gaps:**
- **Critical**: Time slots are mock data (not real availability)
- No calendar view for date selection
- No service duration calculation
- No total price preview before payment
- No service add-ons or upgrades
- No special requests field
- No appointment reminders setup
- Payment flow could be smoother
- No booking confirmation email/SMS mentioned
- No calendar integration (add to calendar)

---

### 2. Customer Portal

#### Portal Home
**Route**: `/portal/home/page.tsx`

**What Exists:**
- Today's appointment card (with reschedule/cancel)
- Quick actions (Book Now, Discover, Wallet, Profile)
- Continue last service (rebook)
- Active offers (feature-gated)
- Recommended services
- Loyalty progress (feature-gated)
- Recent visits (feature-gated)
- My stylist (feature-gated)
- Stats cards (visits, wallet balance, loyalty points)

**Quality**: Good - Personalized, feature-rich

**Gaps:**
- No appointment countdown
- No upcoming appointments list (only today)
- No booking history
- No quick rebook from history
- No notifications center on home
- No promotional banners

---

#### Portal Discover
**Route**: `/portal/discover/page.tsx`

**What Exists:**
- Featured service hero
- Search (services vs providers)
- Category filtering pills
- Trending services section
- Provider search results
- Magazine-style service cards

**Quality**: Good - Beautiful design, dual search modes

**Gaps:**
- No location-based search
- No price range filtering
- No rating filtering
- No availability indicators
- No "book now" on service cards (just display)
- No provider profiles
- No service detail pages
- No favorites/wishlist
- No recently viewed

---

#### Portal Booking Flow
**Route**: `/portal/bookings/new/page.tsx`

**What Exists:**
- Conversational UI (chat-like interface)
- Steps: Greeting → Service → Specialist → DateTime → Confirm
- Service selection from provider's services
- Specialist selection
- Date/time selection
- Booking confirmation
- Creates booking with provider_id

**Quality**: Good - Friendly, conversational

**Gaps:**
- No service details/descriptions
- No specialist profiles
- No availability preview
- No price display
- No payment method selection
- No special requests
- No add-ons
- No calendar integration

---

#### Portal Bookings List
**Route**: `/portal/bookings/page.tsx`

**What Exists:**
- (Not audited yet - needs review)

---

#### Portal Profile
**Route**: `/portal/profile/page.tsx`

**What Exists:**
- (Not audited yet - needs review)

---

#### Portal Wallet
**Route**: `/portal/wallet/page.tsx`

**What Exists:**
- (Not audited yet - needs review)

---

## Customer Journey Analysis

### Ideal Customer Journey

```
1. Discovery
   ↓
2. Provider/Service Selection
   ↓
3. Service Details
   ↓
4. Specialist Selection (optional)
   ↓
5. Date/Time Selection
   ↓
6. Customer Details
   ↓
7. Payment
   ↓
8. Confirmation
   ↓
9. Appointment
   ↓
10. Review
   ↓
11. Rebook
```

### Current Journey Gaps

| Step | Current Status | Gap |
|------|---------------|-----|
| Discovery | ✅ Public landing + portal discover | No location search, no filters |
| Provider Selection | ✅ Salon landing page | No provider profiles, no comparison |
| Service Details | ❌ Missing | No detail pages, no images, no descriptions |
| Specialist Selection | ✅ Staff selection | No specialist profiles, no portfolios |
| Date/Time Selection | ⚠️ Mock data | **Critical: No real availability** |
| Customer Details | ✅ Form | No special requests, no preferences |
| Payment | ✅ Payment flow | No payment preview, no split payment |
| Confirmation | ✅ Success screen | No calendar integration, no reminders |
| Appointment | ⚠️ Portal home shows today | No appointment details, no countdown |
| Review | ❌ Missing | No review flow, no rating system |
| Rebook | ✅ Continue last service | Limited to last booking only |

---

## Critical Issues

### 1. No Real Availability System
**Impact**: High
**Current**: Mock time slots
**Needed**: Real-time availability based on:
- Provider schedule
- Staff availability
- Existing bookings
- Service duration
- Buffer times

### 2. No Service Detail Pages
**Impact**: High
**Current**: Only service cards with name/price/duration
**Needed**: Full service details:
- Description
- Images
- What to expect
- Duration breakdown
- Add-ons available
- Cancellation policy

### 3. No Specialist Profiles
**Impact**: Medium
**Current**: Name + role only
**Needed**: Full specialist profiles:
- Bio
- Portfolio
- Reviews
- Specialties
- Availability
- Certifications

### 4. No Review System
**Impact**: Medium
**Current**: No customer review flow
**Needed**: Post-appointment review:
- Rating (1-5 stars)
- Written review
- Specialist-specific review
- Service-specific review

### 5. No Calendar Integration
**Impact**: Low
**Current**: No add to calendar
**Needed**: Calendar integration:
- Google Calendar
- Apple Calendar
- Outlook
- Reminder notifications

---

## Feature Prioritization

### Phase 1A: Core Booking Experience (Must Have)

1. **Real Availability System**
   - Implement actual time slot calculation
   - Consider provider schedule
   - Consider staff availability
   - Consider existing bookings
   - Add buffer times

2. **Service Detail Pages**
   - Create `/services/{id}` route
   - Show full description
   - Add service images
   - Show add-ons
   - Display cancellation policy

3. **Booking Confirmation Enhancements**
   - Add calendar integration
   - Send confirmation email/SMS
   - Add appointment reminders
   - Show booking reference number

4. **Payment Preview**
   - Show total before payment
   - Break down costs (service + deposit)
   - Show payment method details

### Phase 1B: Enhanced Discovery (Should Have)

1. **Provider Profiles**
   - Create `/providers/{id}` route
   - Show provider details
   - Display all services
   - Show team members
   - Display reviews

2. **Search & Filter Improvements**
   - Location-based search
   - Price range filter
   - Rating filter
   - Availability filter
   - Category filters

3. **Specialist Profiles**
   - Create `/specialists/{id}` route
   - Show bio and portfolio
   - Display reviews
   - Show availability
   - Book directly

### Phase 1C: Post-Booking Experience (Nice to Have)

1. **Review System**
   - Post-appointment review flow
   - Rating system
   - Written reviews
   - Specialist reviews
   - Service reviews

2. **Booking Management**
   - Full booking history
   - Reschedule flow
   - Cancellation policy
   - Modify booking

3. **Loyalty & Rewards**
   - Points system
   - Tier progression
   - Rewards redemption
   - Referral program

---

## Technical Debt

### Frontend
- Mock time slots need real API integration
- Service detail pages missing
- Provider profiles missing
- Specialist profiles missing
- Review system missing
- Calendar integration missing

### Backend
- Availability calculation logic needed
- Service detail endpoints needed
- Provider profile endpoints needed
- Specialist profile endpoints needed
- Review endpoints needed
- Calendar integration needed
- Email/SMS notification system needed

---

## Next Steps

1. **Audit remaining portal pages** (profile, wallet, bookings list)
2. **Design real availability system** (backend logic)
3. **Create service detail pages** (frontend + backend)
4. **Implement provider profiles** (frontend + backend)
5. **Add review system** (frontend + backend)
6. **Calendar integration** (frontend + backend)
7. **Email/SMS notifications** (backend)

---

## Conclusion

The current customer experience has a solid foundation with beautiful UI and smooth flows. However, there are critical gaps that prevent it from being a "magical" booking experience:

**Critical Path:**
1. Real availability system (highest priority)
2. Service detail pages
3. Booking confirmation enhancements
4. Provider profiles
5. Review system

The architecture is now provider-centric, which is perfect for building these features. The focus should be on making the booking flow feel magical with real data and smooth transitions.

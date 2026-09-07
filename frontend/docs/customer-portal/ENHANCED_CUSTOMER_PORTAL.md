# Enhanced Customer Portal Implementation Plan with Feature Flag & Capability Engine

This plan outlines the comprehensive upgrade of the customer portal from basic pages to a premium 5-10 year customer experience, requiring significant backend infrastructure additions alongside frontend redesign, with a sophisticated Feature Flag and Capability Engine system for progressive feature revelation.

## Architecture: Feature Flag & Capability Engine

### Three-Level Feature Revelation System

**Level 1 — Implemented**
- Backend, database, APIs, UI all exist
- `feature.enabled = false` by default
- Nothing visible until salon/customer qualifies

**Level 2 — Available**
- Salon has unlocked it based on subscription or metrics
- Example: `Subscription = Professional` → Packages visible
- Example: `Customer Count > 200` → Loyalty enabled

**Level 3 — Intelligent**
- Yo.Salon suggests features proactively
- Example: "You've reached 150 customers. Enable Loyalty?"
- Software acts as business advisor

### Database Schema

**feature_flags Table**
```php
- id (UUID)
- salon_id (UUID, nullable for global flags)
- feature_key (string, indexed)
- enabled (boolean)
- enabled_at (timestamp)
- enabled_by (UUID, user_id)
- reason (text)
- metadata (json)
```

**Feature Keys**
```php
wallet, loyalty, gift_cards, packages, membership, offers, reviews,
support, waitlist, referrals, subscriptions, ai_recommendations,
my_stylist, rebook, service_categories, staff_profiles
```

**feature_policies Table**
```php
- id (UUID)
- feature_key (string)
- policy_type (enum: subscription, customer_count, booking_count, payments_enabled, etc.)
- rule_value (json)
- description (text)
- active (boolean)
```

**Example Policies**
```php
Loyalty:
  - subscription: ['>=', 'Professional']
  - customer_count: ['>', 100]

Packages:
  - subscription: ['>=', 'Premium']

Gift Cards:
  - subscription: ['>=', 'Professional']
  - payments_enabled: true

AI Recommendations:
  - booking_count: ['>', 500]
  - customer_history_exists: true
```

### Capability Engine Service

**CapabilityService.php**
```php
class CapabilityService
{
    // Check if salon can use a feature
    public function allows(Salon $salon, string $feature): bool

    // Check if customer qualifies for portal feature
    public function allowsCustomer(Customer $customer, string $feature): bool

    // Get all available features for salon
    public function getAvailableFeatures(Salon $salon): array

    // Enable feature for salon
    public function enableFeature(Salon $salon, string $feature, string $reason): void

    // Disable feature for salon
    public function disableFeature(Salon $salon, string $feature): void

    // Check policy conditions
    public function evaluatePolicy(Salon $salon, array $policy): bool

    // Suggest features based on salon metrics
    public function suggestFeatures(Salon $salon): array
}
```

### Middleware

**FeatureFlagMiddleware**
```php
// Middleware to check feature flags before accessing endpoints
// Returns 403 if feature not enabled for salon
```

**CapabilityMiddleware**
```php
// Middleware to evaluate capability policies
// Auto-enables features when conditions are met
// Returns feature availability in response headers
```

### Frontend Integration

**CapabilityContext**
```php
// React context that receives available features from /portal/me
// Provides: hasFeature('loyalty'), hasFeature('packages'), etc.
// Components conditionally render based on capabilities
```

**Progressive Portal UI**
```php
// New customer (1 visit): Basic booking, discover services
// Growing customer (5+ visits): Points, rewards
// Loyal customer (20+ visits): Membership, packages, gift cards
```

### Implementation Pattern

**Backend:**
```php
if (Capability::allows($salon, 'loyalty')) {
    // Show loyalty features
}
```

**Frontend:**
```php
{hasFeature('loyalty') && <LoyaltySection />}
```

**API Response:**
```php
{
  "available_features": ["wallet", "loyalty", "packages"],
  "suggested_features": ["gift_cards"],
  "customer_tier": "gold"
}
```

## Current Backend Assessment

**Existing Capabilities:**
- Portal authentication (login/register/logout)
- Basic portal home endpoint with upcoming booking, recommended services, favorite stylist
- Customer preferences (preferred staff, notification/booking preferences)
- Favorite services system
- Recommendation service (trending, popular, seasonal, recently added)
- Wallet/loyalty summaries (currently hardcoded to 0)

**Critical Missing Backend Infrastructure:**
- Real wallet balance, packages, gift cards tracking
- Loyalty points system with tier progression
- Customer payment methods
- Customer transaction history
- Service packages system
- Gift cards system
- Membership tiers and benefits
- Customer-specific availability API
- Rebook functionality
- Offers/promotions system
- Reviews system
- Staff profile enhancements
- Service categories with imagery

## Phase 1: Home Page as Relationship Hub

### Backend Work
1. **Create Offers/Promotions System**
   - Migration: `create_offers_table` (id, salon_id, title, description, discount_type, discount_value, start_date, end_date, terms, image, active)
   - Model: `Offer.php`
   - Service: `OfferService.php` (getActiveOffers, getCustomerEligibleOffers)
   - Controller: `OfferController.php`
   - API: `GET /portal/offers`

2. **Implement Real Loyalty System**
   - Migration: `create_loyalty_points_table` (id, customer_id, salon_id, points_earned, points_redeemed, balance, tier, tier_progress, earned_at, redeemed_at, description)
   - Model: `LoyaltyPoint.php`
   - Service: `LoyaltyService.php` (addPoints, redeemPoints, getCustomerBalance, getTierProgress)
   - Update `PortalAuthService::getAuthenticatedUser()` to return real loyalty data
   - API: `GET /portal/loyalty`

3. **Add Recent Visits Endpoint**
   - Service: Add `getRecentVisits()` to `CustomerPortalService.php`
   - Returns last 5 completed bookings with service, staff, date
   - API: `GET /portal/recent-visits`

4. **Continue Last Service**
   - Service: Add `getLastBooking()` to `CustomerPortalService.php`
   - Returns most recent completed booking for quick rebook
   - API: `GET /portal/last-booking`

### Frontend Work
1. **Redesign Home Page** (`frontend/src/app/portal/home/page.tsx`)
   - Personalized greeting with time of day
   - Next appointment card with quick actions (reschedule, cancel)
   - Quick Book button (prominent CTA)
   - Recommended services carousel (horizontal scroll)
   - Continue Last Service card (one-tap rebook)
   - Active Offers section (promotional banners)
   - Loyalty progress bar with tier visualization
   - Recent visits timeline
   - My Stylist spotlight (if preferred stylist exists)

## Phase 2: Customer Booking Experience

### Backend Work
1. **Customer Availability API**
   - Service: `CustomerAvailabilityService.php`
   - Method: `getAvailableSlots(salonId, serviceId, staffId, date)`
   - Returns time slots for customer-friendly booking flow
   - Logic: Check staff availability, service duration, existing bookings
   - API: `GET /portal/availability?service_id=X&staff_id=Y&date=Z`

2. **Customer Booking Flow**
   - Service: `CustomerBookingService.php`
   - Methods: `createBooking()`, `validateBooking()`, `calculatePrice()`
   - Integration with existing `BookingService.php` for business logic
   - API: `POST /portal/bookings`

3. **Rebook Functionality**
   - Service: Add `rebookFromPrevious()` to `CustomerBookingService.php`
   - Copies service + staff from previous booking, asks for new date/time
   - API: `POST /portal/bookings/rebook/{previousBookingId}`

4. **Service Categories Enhancement**
   - Migration: Add `image`, `icon`, `sort_order` to `service_categories_table`
   - Service: Add `getServicesByCategory()` to existing service logic
   - API: `GET /portal/services/categories`

### Frontend Work
1. **Service Selection Page** (`frontend/src/app/portal/bookings/new/service/page.tsx`)
   - Category-based navigation (Haircut, Colour, Spa, Massage)
   - Service cards with imagery, duration, price
   - Filter by category, duration, price
   - "No Preference" option for stylist

2. **Stylist Selection Page** (`frontend/src/app/portal/bookings/new/stylist/page.tsx`)
   - Staff profiles with avatar, specialization, rating
   - "No Preference" option
   - Availability indicators
   - Favorite stylist highlighting

3. **Date/Time Selection Page** (`frontend/src/app/portal/bookings/new/time/page.tsx`)
   - Calendar view (Today, Tomorrow, Choose Date)
   - Available time slots in grid
   - Morning/Afternoon/Evening quick filters
   - Duration consideration

4. **Booking Confirmation Page** (`frontend/src/app/portal/bookings/new/confirm/page.tsx`)
   - Summary of selections
   - Price calculation
   - Payment method selection
   - Confirm button

5. **Rebook Flow** (`frontend/src/app/portal/bookings/rebook/[id]/page.tsx`)
   - Show previous booking details
   - Pre-select service and staff
   - Jump to date/time selection

## Phase 3: Wallet as Relationship Hub

### Backend Work
1. **Customer Wallet System**
   - Migration: `create_customer_wallets_table` (id, customer_id, salon_id, balance, currency)
   - Model: `CustomerWallet.php`
   - Service: `WalletService.php` (getBalance, addFunds, deductFunds, getTransactions)
   - API: `GET /portal/wallet`, `POST /portal/wallet/add-funds`

2. **Service Packages**
   - Migration: `create_service_packages_table` (id, salon_id, name, service_id, quantity, price, expiry_date)
   - Migration: `create_customer_package_purchases_table` (id, customer_id, package_id, remaining_uses, purchase_date, expiry_date)
   - Model: `ServicePackage.php`, `CustomerPackagePurchase.php`
   - Service: `PackageService.php` (getAvailablePackages, purchasePackage, getCustomerPackages)
   - API: `GET /portal/packages`, `POST /portal/packages/purchase`

3. **Gift Cards**
   - Migration: `create_gift_cards_table` (id, code, value, balance, salon_id, purchaser_id, recipient_id, expiry_date, status)
   - Migration: `create_customer_gift_cards_table` (id, customer_id, gift_card_id, received_date)
   - Model: `GiftCard.php`, `CustomerGiftCard.php`
   - Service: `GiftCardService.php` (validateCard, redeemCard, getCustomerGiftCards)

4. **Customer Payment Methods**
   - Migration: `create_customer_payment_methods_table` (id, customer_id, type, provider, last_four, expiry_month, expiry_year, is_default)
   - Model: `CustomerPaymentMethod.php`
   - Service: `CustomerPaymentService.php` (addPaymentMethod, getPaymentMethods, setDefault)
   - API: `GET /portal/payment-methods`, `POST /portal/payment-methods`

5. **Customer Transactions**
   - Migration: Add `customer_id` to existing `transactions_table` if not present
   - Service: Add `getCustomerTransactions()` to `WalletService.php`
   - Returns timeline of all customer financial activity
   - API: `GET /portal/transactions`

6. **Membership Tiers**
   - Migration: `create_membership_tiers_table` (id, salon_id, name, points_required, benefits_json, color, icon)
   - Service: Add `getCustomerTier()` to `LoyaltyService.php`
   - Returns tier with benefits (priority booking, discounts, perks)

### Frontend Work
1. **Redesign Wallet Page** (`frontend/src/app/portal/wallet/page.tsx`)
   - Hero card: Salon name, member since, tier badge with gradient
   - Balance section with available credit
   - Packages carousel (remaining uses, expiry)
   - Gift cards section (value, expiry)
   - Membership card with benefits list
   - Loyalty points with progress bar to next tier
   - Payment methods (saved cards, mobile money)
   - Quick actions: Add Funds, View History

2. **Packages Page** (`frontend/src/app/portal/wallet/packages/page.tsx`)
   - Available packages to purchase
   - Active packages with usage progress
   - Package details and benefits

3. **Transactions Page** (`frontend/src/app/portal/wallet/transactions/page.tsx`)
   - Beautiful timeline (not table)
   - Grouped by date (Today, Yesterday, This Week)
   - Transaction type icons (booking, package purchase, top-up, redemption)
   - Amount and status indicators

4. **Payment Methods Page** (`frontend/src/app/portal/wallet/payment-methods/page.tsx`)
   - List of saved payment methods
   - Add new payment method
   - Set default method
   - Delete method

## Phase 4: Discover as Netflix-like Experience

### Backend Work
1. **Service Imagery**
   - Migration: Add `image_url`, `gallery_json` to `services_table`
   - Service: Update service queries to include imagery
   - API: `GET /portal/services/{id}` (detailed service view)

2. **Staff Profiles**
   - Migration: Add `bio`, `portfolio_json`, `rating`, `review_count` to `staff_table`
   - Service: `StaffProfileService.php` (getStaffProfile, getStaffServices, getStaffReviews)
   - API: `GET /portal/staff/{id}`

3. **Reviews System**
   - Migration: `create_reviews_table` (id, customer_id, salon_id, service_id, staff_id, rating, comment, created_at, approved)
   - Model: `Review.php`
   - Service: `ReviewService.php` (createReview, getServiceReviews, getStaffReviews)
   - API: `GET /portal/services/{id}/reviews`, `POST /portal/reviews`

4. **Discover Categories**
   - Service: Enhance `PortalRecommendationService.php`
   - Methods already exist: `getTrendingServices()`, `getPopularServices()`, `getSeasonalServices()`, `getRecentlyAddedServices()`
   - Add API endpoints for each category
   - API: `GET /portal/discover/trending`, `GET /portal/discover/popular`, etc.

### Frontend Work
1. **Redesign Discover Page** (`frontend/src/app/portal/discover/page.tsx`)
   - Netflix-style horizontal carousels
   - Sections: Trending, Popular, Seasonal, New, Recommended
   - Large hero banner for featured service
   - Smooth horizontal scrolling
   - Service cards with beautiful imagery

2. **Service Details Page** (`frontend/src/app/portal/discover/services/[id]/page.tsx`)
   - Large hero image
   - Service name, description, duration, price
   - Available stylists for this service
   - Reviews section with ratings
   - Book Now CTA
   - Add to Favorites

3. **Staff Profile Page** (`frontend/src/app/portal/discover/staff/[id]/page.tsx`)
   - Staff photo, name, specialization
   - Bio and experience
   - Rating and review count
   - Portfolio gallery
   - Services offered
   - Availability calendar
   - Book with this stylist

## Phase 5: My Stylist & Profile Enhancements

### Backend Work
1. **My Stylist Feature**
   - Service: Add `getStylistRelationship()` to `CustomerPortalService.php`
   - Returns: stylist info, last appointment, booking frequency
   - API: `GET /portal/my-stylist`

2. **Notification Preferences**
   - Migration: Add notification channels to `customer_preferences_table` (email, sms, push, preferences_json)
   - Service: Enhance `updatePreferences()` in `CustomerPortalService.php`
   - API: `PUT /portal/preferences/notifications`

3. **Privacy Settings**
   - Migration: `create_customer_privacy_settings_table` (id, customer_id, profile_visibility, data_sharing, marketing_consent)
   - Model: `CustomerPrivacySetting.php`
   - Service: `PrivacyService.php`
   - API: `GET /portal/privacy`, `PUT /portal/privacy`

4. **Support/Ticketing**
   - Migration: `create_support_tickets_table` (id, customer_id, salon_id, subject, message, status, priority, created_at)
   - Model: `SupportTicket.php`
   - Service: `SupportService.php`
   - API: `GET /portal/support`, `POST /portal/support`

### Frontend Work
1. **My Stylist Section** (add to Home page or separate)
   - Stylist card with photo, name, rating
   - "Your preferred stylist" badge
   - Last appointment info
   - One-tap "Book with [Name]" button
   - Stylist availability indicator

2. **Enhanced Profile Page** (`frontend/src/app/portal/profile/page.tsx`)
   - Personal info (editable)
   - Preferences (notification settings, booking preferences)
   - Privacy settings
   - Support/ticketing
   - Account security (change password, 2FA)
   - Delete account option

3. **Notifications Settings Page** (`frontend/src/app/portal/profile/notifications/page.tsx`)
   - Toggle switches for email, SMS, push
   - Notification types (booking reminders, offers, loyalty updates)
   - Quiet hours

4. **Support Page** (`frontend/src/app/portal/profile/support/page.tsx`)
   - FAQ section
   - Contact form
   - Ticket history
   - Live chat (future)

## Implementation Order Priority

0. **Phase 0** (Capability Engine & Feature Flags) - Foundational infrastructure for progressive revelation
1. **Phase 1** (Home as relationship hub) - Foundation for customer engagement
2. **Phase 2** (Customer booking experience) - Core customer journey
3. **Phase 3** (Wallet as relationship hub) - Retention and monetization
4. **Phase 4** (Discover as Netflix-like) - Inspiration and upselling
5. **Phase 5** (My Stylist & Profile) - Personalization and account management

## Phase 0: Capability Engine & Feature Flags

### Backend Work

1. **Database Migrations**
   - Migration: `create_feature_flags_table`
   - Migration: `create_feature_policies_table`
   - Migration: `add_customer_visit_count_to_customer_salon_table` (for customer capability evaluation)

2. **Models**
   - Model: `FeatureFlag.php`
   - Model: `FeaturePolicy.php`

3. **Capability Engine Service**
   - Service: `CapabilityService.php`
     - `allows(Salon $salon, string $feature): bool`
     - `allowsCustomer(Customer $customer, string $feature): bool`
     - `getAvailableFeatures(Salon $salon): array`
     - `enableFeature(Salon $salon, string $feature, string $reason): void`
     - `disableFeature(Salon $salon, string $feature): void`
     - `evaluatePolicy(Salon $salon, array $policy): bool`
     - `suggestFeatures(Salon $salon): array`

4. **Middleware**
   - Middleware: `FeatureFlagMiddleware.php`
   - Middleware: `CapabilityMiddleware.php`

5. **Controller**
   - Controller: `FeatureFlagController.php`
   - API: `GET /admin/features`, `POST /admin/features/enable`, `POST /admin/features/disable`

6. **Update Portal Auth Service**
   - Modify `PortalAuthService::getAuthenticatedUser()` to include available_features and suggested_features
   - Integrate with CapabilityService to determine customer-level feature access

### Frontend Work

1. **Capability Context**
   - Context: `CapabilityContext.tsx`
   - Provides: `hasFeature()`, `getAvailableFeatures()`, `getSuggestedFeatures()`
   - Integrates with PortalAuthContext

2. **Feature Guard Components**
   - Component: `FeatureGuard.tsx` (wraps children, renders only if feature available)
   - Component: `ProgressiveSection.tsx` (shows different content based on customer tier)

3. **Update Portal Auth Context**
   - Add available_features and suggested_features to auth state
   - Pass to CapabilityContext

4. **Admin UI for Feature Flags** (salon dashboard)
   - Page to view available features
   - Enable/disable features
   - View suggested features based on metrics
   - Configure feature policies

## Database Migrations Summary

**New Tables Required (Phase 0):**
- feature_flags
- feature_policies

**New Tables Required (Phases 1-5):**
- offers
- loyalty_points
- customer_wallets
- service_packages
- customer_package_purchases
- gift_cards
- customer_gift_cards
- customer_payment_methods
- membership_tiers
- reviews
- customer_privacy_settings
- support_tickets

**Table Modifications Required:**
- services: add image_url, gallery_json
- staff: add bio, portfolio_json, rating, review_count
- service_categories: add image, icon, sort_order
- customer_preferences: expand notification_preferences
- transactions: ensure customer_id relationship

## API Endpoints Summary

**New Portal Endpoints:**
- GET /portal/offers
- GET /portal/loyalty
- GET /portal/recent-visits
- GET /portal/last-booking
- GET /portal/availability
- POST /portal/bookings
- POST /portal/bookings/rebook/{id}
- GET /portal/services/categories
- GET /portal/services/{id}
- GET /portal/staff/{id}
- GET /portal/discover/trending
- GET /portal/discover/popular
- GET /portal/discover/seasonal
- GET /portal/discover/new
- GET /portal/services/{id}/reviews
- POST /portal/reviews
- GET /portal/wallet
- POST /portal/wallet/add-funds
- GET /portal/packages
- POST /portal/packages/purchase
- GET /portal/payment-methods
- POST /portal/payment-methods
- GET /portal/transactions
- GET /portal/my-stylist
- PUT /portal/preferences/notifications
- GET /portal/privacy
- PUT /portal/privacy
- GET /portal/support
- POST /portal/support

## Notes

- All new portal endpoints require `auth:portal` middleware
- Most require `portal.context` middleware for salon resolution
- Reuse existing business logic (BookingService, etc.) where possible
- Customer-facing APIs should be separate from salon-facing APIs for security and UX differences
- Implement proper error handling and validation for all new endpoints
- Consider rate limiting for public endpoints
- Image uploads will need storage configuration (S3, local, etc.)

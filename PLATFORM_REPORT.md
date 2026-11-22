# Yo.Salon — Platform Report (Code-Verified)

**Generated from source inspection, not documentation.**
**Repository:** `emkay0070/yo.salon`
**Branch:** `main` — commit `e53c160` (pushed to origin)
**Working tree:** clean

---

## 1. Executive Summary — Where the Project Stands

Yo.Salon is a **multi-tenant salon/spa/beauty business management platform** — a vertical SaaS for beauty/wellness providers. It is **actively under heavy development**, in a **refactor/verification phase** where a legacy "salon" data model is being migrated to a newer "provider → location → specialist" model.

Key indicators of current state (verified in code, not docs):

| Observation | Evidence |
|-------------|----------|
| **Laravel backend + Next.js (App Router) frontend** | `backend/` is Laravel (Eloquent, controllers, migrations); `frontend/src/app` uses Next.js App Router with route groups |
| **~253 database migrations** | `backend/database/migrations/` — heavy schema churn; legacy (`salon_*`) and new (`provider/location/customer_provider`) schemas coexist |
| **Dual-schema migration in progress** | Old `salons`, `salon_users`, `customer_salon` tables deleted/renamed; new `providers`, `locations`, `customer_providers`, `provider_users` introduced |
| **Large audit/report trail** | Dozens of `*_AUDIT.md`, `*_REPORT.md`, `*_VERIFICATION*.md` files at root and `backend/` — indicates an ongoing verification/remediation sprint |
| **Three authenticated user contexts** | `auth:sanctum` (staff/admin), `auth:portal` (customer), `auth:specialist` (specialist) — all present in `api.php` |
| **Stale route references** | `api.php` references controllers marked `// TODO: Controller does not exist` (e.g. `SalonCapabilityController`, `PublicSalonWebsiteController`) |
| **Not clearly "production stable"** | Heavy migration + rename activity + TODO comments suggest pre-launch or mid-refactor |

---

## 2. Tech Stack

### Backend (`backend/`)
- **Framework:** Laravel (PHP) — Eloquent ORM, Sanctum API token auth, route middleware groups
- **Database:** Migrations-driven (253 migration files); models use `HasUuids` (UUID primary keys) and `SoftDeletes`
- **Auth guards:** `sanctum`, `portal`, `specialist` (custom guards)

### Frontend (`frontend/`)
- **Framework:** Next.js (App Router), TypeScript
- **Route groups:** `(business)`, `(customer)`, `(specialist)`, `[slug]`, plus top-level `book`, `booking`, `login`, `register`, `providers`, `invite`, `onboarding`, `welcome`
- **API client:** Single `ApiClient` class (`src/lib/api-client.ts`, ~1,174 lines) wrapping axios with auth-token switching, URL-segment validation, and `X-Location-Id` context header
- **Auth state:** localStorage-based tokens (`auth_token`, `specialist_auth_token`, `location_id`)
- **Note:** `frontend/AGENTS.md` warns of breaking Next.js version changes — conventions may differ from standard Next.js

---

## 3. Domain Model (from `backend/app/Models/`)

The platform centers on **Providers** (businesses) → **Locations** (branches) → **Specialists/Staff** (practitioners) → **Customers** (clients).

### Core entities

| Model | Purpose |
|-------|---------|
| `Provider` | Business tenant. Types: `salon`, `independent_specialist`, `spa`, `beauty_school`, `mobile_team`, `supplier`. Has many Locations, Specialists, Services, Bookings, Reviews. One Subscription, BrandExperience, ProviderBookingConfig. |
| `Location` | Physical branch. Opening hours, timezone, `is_primary`, `accepting_online_bookings`, `schedule_mode`. Belongs to a Provider. |
| `Specialist` | Practitioner. Bookings, assignments, career events, breaks, notes, assessments. Belongs to a Provider. |
| `Staff` | Unified payroll/team member (staff + employed + independent specialists). |
| `Customer` | End-client. Supports **guest/walk-in** creation scoped per-provider; promotable later. Has PortalAccount, bookings, preferences, favorites, timeline. |
| `PortalAccount` | Customer login (email/password). **Not salon-scoped** — one account relates to multiple providers (Uber-style). |
| `Booking` | Central business event. Fields: location/provider/customer/staff/specialist/service, date/time, status, `operational_state`, payment amounts, `rescheduled_from_booking_id`, floor tracking (`arrived_at`, `waiting_since`, `queue_position`), grace period. Has many BookingService, PaymentRequest, Transaction; one BookingResolution. |
| `BookingService` | Junction between Booking and Service (many-to-many via custom `BookingServiceBelongsToMany` relation). |
| `Service` | Sellable service offered by a provider. |
| `SpecialistAssignment` | Links specialist to provider/location; `status`, `is_primary`, `schedule_mode`. |
| `Subscription` / `Plan` | SaaS subscription per provider. |
| `ProviderBookingConfig` | Per-provider booking rules/config. |
| `BrandExperience` | Per-provider theming/branding. |
| `Transaction` | Payment-rail event (who paid, when, how). Distinct from the double-entry ledger. |
| `PaymentRequest` | Payment request tied to a booking. |
| `Settlement` | Payout settlement records. |
| `Ledger` (via `LedgerController`) | Double-entry journal — financial truth, separate from `transactions`. |
| `Offer`, `LoyaltyPoint`, `GiftCard`, `GiftCardTransaction`, `Invoice` | Commerce/loyalty domain. |
| `Review`, `Testimonial`, `Media`, `Gallery` | Content/social proof. |
| `FeatureFlag`, `FeaturePolicy` | Feature gating. |
| `Notification`, `NotificationJob` | Notification system. |
| `Invitation`, `CustomerWorkspaceInvitation` | Staff/specialist/customer invitations. |
| `ScheduleException`, `LocationSchedule`, `AssignmentSchedule` | Scheduling + exceptions (holidays, closures). |
| `IntelligenceRun/Score/Insight/Forecast` | AI/analytics domain. |
| `ProfessionalAssessment`, `SpecialistNote`, `SpecialistCareerEvent` | Specialist-client clinical notes domain. |

### Migration activity
Old `salon_*` tables (`salons`, `salon_users`, `customer_salon`, `salon_capacities`, `salon_service`, `salon_schedules`) are being deleted/renamed. New tables include `providers`, `locations`, `customer_providers` (pivot), `provider_users`, `provider_user_locations`, `resources`, `resource_allocations`, `resource_capabilities`, `professions`, `craft_taxonomy`, `business_categories`, `service_templates`, `gift_cards`, `gift_card_transactions`, `booking_resolutions`, `booking_reminders`, `specialist_breaks`. Legacy seeders renamed to `Legacy_*` prefix.

---

## 4. Multi-Tenancy & Identity

The platform evolved from a **single-salon** model to a **provider-with-multiple-locations** model:

- `Provider` owns `Location`s. A customer belongs to many providers via `customer_providers` pivot (with `visits`, `joined_at`).
- `PortalAccount` is **not provider-scoped** — one login works across multiple salons.
- Auth guards separate concerns: `auth:sanctum` (staff/admin), `auth:portal` (customer), `auth:specialist` (specialist).
- Middleware chains: `provider.context`, `location.context`, `portal.context` resolve scope from route params.
- Frontend `ApiClient` switches between `auth_token` and `specialist_auth_token` based on URL path (`/specialist-portal/`).

---

## 5. Booking Lifecycle (Core Product)

Orchestrated by `BookingService` (`backend/app/Services/BookingService.php`, ~12 KB) and `BookingOrchestrator`.

### Stages

1. **Availability** — `AvailabilityEngine` (~48 KB, largest service) computes slots per provider/location/specialist/date. Public endpoints: `/providers/{id}/availability`, `/slots/lock`, `/slots/lock/{lockId}`.
2. **Booking creation** — `POST /v1/bookings` (auth) and `POST /v1/bookings/with-account` (public, Journey 4: booking + optional account creation). `BookingService` resolves/creates the customer, checks conflicts (`BookingConflictService`), validates time, allocates resources, creates a payment request.
3. **Payment** — `BookingPaymentService` initializes/verifies/refunds salon (B2C) payments. `PaymentRoutingService` chooses rail (Flutterwave / MTN Momo / Airtel / cash).
4. **Floor operations** (check-in → service completion) via `FloorController`:
   - `arrive` (confirmed → arrived), `wait` (arrived → waiting), `startSession` / `completeSession` per `booking_service`
   - Queue (`/floor/queue`), live (`/floor/live`), snapshot (`/floor/snapshot`)
   - Specialist breaks (`startBreak`, `endBreak`)
5. **Post-service** — `BookingResolution`, `BookingReminder`, reviews, assessments, notes.

### Supporting booking services
`BookingConfigurationService`, `BookingPolicyResolver`, `BookingRulesValidator`, `CancellationRulesEngine`, `ReminderService`, `CustomerBookingService`, `CustomerAvailabilityService`, `ScheduleMigrationService`.

---

## 6. Payments & Finance (Dual-Track)

The code clearly separates **customer-facing salon payments** from **platform B2B payments**.

### Salon (B2C) — customer pays the business
- `BookingPaymentController`: `initializeBookingPayment`, `verifySalonPayment`, `recordManualPayment`, `refundSalonPayment`, `getBookingPaymentStatus`
- Routes: `/v1/payments/salon/{initialize,verify,manual,refund}`, `/v1/bookings/{id}/status`
- Providers: `FlutterwaveProvider`, `AirtelProvider`, `MTNMomoService`, `PaymentManager`, `PaymentProviderInterface`
- Webhooks: Flutterwave, platform, salon, MTN, disbursement (MTN/Airtel)

### Platform (B2B) — provider pays the platform (subscription)
- `PlatformPaymentController`: initialize/verify/refund subscription payments, cash payments, invoice status
- Routes: `/v1/payments/platform/{initialize,verify,refund,cash/initialize,cash/confirm}`
- `PlatformPaymentService`, `Invoice`, `InvoicePayment`

### Payouts & settlements (provider gets paid)
- `SettlementController`: `/v1/settlements`
- `Compensation` domain: policies, earnings, periods (open/close), adjustments, payables, payout readiness
- `PayoutReadinessService`, `PayoutReadinessDTO`

### Financial truth (ledger)
- `LedgerController`: `/v1/providers/{provider}/ledger` — double-entry journal, explicitly distinct from `transactions` (payment-rail events)

### Other finance
`FeeEngine`, `BillingService`, `Offer`, `LoyaltyPoint`, `GiftCardService`, `WalletController`.

---

## 7. Specialist Portal

Dedicated authenticated experience (`auth:specialist`) for individual practitioners:

- **Auth:** login, register, logout
- **Onboarding:** identity, craft, verification, work preferences, availability, complete
- **Dashboard:** stats, context, calendar, earnings
- **Clients:** list, invitations (send/accept/decline/pending stats), assessments, notes
- **Craft:** taxonomy, services (attach/create/update), expertise CRUD
- **Goals:** CRUD + activate/pause/complete
- **Career/journey:** career events, journey data
- **Availability:** get/update
- **Payment profiles:** CRUD (specialist payout details)
- **Capabilities:** subscription feature check
- **Verification:** status/submit
- **Bookings:** list, update status (Phase 3), start/complete/cancel sessions
- **Appointments needing attention** (no-show polling)

Frontend: `frontend/src/app/(specialist)/specialist-portal/`.

---

## 8. Customer Portal

Authenticated customer experience (`auth:portal`):

- **Auth:** portal create/login, invitation accept
- **Profile:** me, grooming profile, recent visits, last booking
- **Bookings:** list, show, cancel, reschedule, upcoming, history, rebook, activities
- **Availability:** dates, staff
- **Specialists:** follow/unfollow, favorite, rating, relationship, followed/favorites
- **Reviews:** create, my reviews, specialist reviews
- **Offers & Loyalty:** summary, history, redeem, balance
- **Packages:** list, purchase, my packages
- **Gift cards:** validate, redeem, purchase, purchased, redeemed, send
- **Wallet:** transactions, promo apply (loyalty points only)
- **Collections & Timeline:** CRUD + photo upload
- **Assessments/Notes:** pending assessments, review, mark viewed, timeline, recommendations
- **Service recommendations:** my recommendations, specialist recommendations

Frontend: `frontend/src/app/(customer)/portal/`.

---

## 9. Business Operations (Owner / Manager / Receptionist)

`(business)` route group and protected API routes:

- **Dashboard:** analytics, live stats, pulse (operational center)
- **Bookings:** all, by provider, by customer, needs-resolution, resolve
- **Customers / Services / Staff / Specialists / Providers:** CRUD + scoping
- **Locations:** schedules, exceptions, branch-services, health, booking-config
- **Booking rules:** validate, cancellation policy, no-show, reschedule, requirements
- **Booking orchestrator:** reserve
- **Floor:** arrive/wait/start/complete, queue, live, snapshot
- **Payments:** methods (test/verify credentials), payment requests, transactions, summary, ledger, settlements
- **Membership:** plans, change/cancel/resume/trial, usage, invoices, timeline
- **Add-ons:** purchase
- **Media/Gallery:** upload, CRUD
- **Testimonials:** CRUD
- **Analytics:** index, intelligence
- **Copilot:** AI chat (`/v1/analytics/copilot-chat`)
- **Feature flags:** enable/disable/seed/policies/suggestions
- **Offers / Loyalty (admin):** CRUD / award
- **Brand experience:** show/update
- **Discover:** featured/trending (admin set/refresh)
- **Seeders:** admin seeder management (run, discover, batch)
- **Notifications:** index, read, preferences
- **Profiles:** CRUD
- **Compensation:** policies, earnings, periods, adjustments, payables, payout

Frontend: `frontend/src/app/(business)/`.

---

## 10. Intelligence & Analytics

- `IntelligenceEngine` with analyzers: `ChurnAnalyzer`, `RevenueAnalyzer`, `StaffAnalyzer`
- `RuleEngine`, `BriefingBuilder`, `ForecastService`
- `IntelligenceSnapshotService`, `FinanceAnalyticsService`, `OperationalAnalyticsService`
- Models: `IntelligenceRun`, `IntelligenceScore`, `IntelligenceInsight`, `IntelligenceForecast`
- API: `/v1/analytics`, `/v1/analytics/intelligence`, `/v1/analytics/copilot-chat`
- Frontend components: `ChurnRadar`, `RevenuePipeline`, `SignalsBoard`, `StaffLeaderboard`

---

## 11. API Surface (`backend/routes/api.php`)

~795 lines, one monolithic route file. Groups:

- **Public:** provider/specialist/service listing, availability, slug availability, webhooks, public booking (`/bookings/with-account`), customer lookup/find-details, public payment methods, auth register/login, portal create/login/invite accept, specialist-portal login/register, reviews, reference data, business categories, professions, service templates, add-ons, search, discover, testimonials.
- **`auth:portal`:** customer portal context/home/bookings/wallet/packages/gift-cards/loyalty/collections/timeline/reviews/offers/specialists/assessments.
- **`auth:sanctum`:** provider/staff/admin operations (providers, locations, services, staff, specialists, bookings, customers, payments, transactions, ledger, settlements, membership, media, gallery, testimonials, analytics, offers, feature flags, seeders, schedule migration, floor ops).
- **`auth:specialist`:** full specialist portal CRUD.
- **`auth.any`:** media upload (universal).

Stale references: `SalonCapabilityController`, `PublicSalonWebsiteController`, `SalonController`, `SalonSpecialistController` marked `// TODO: Controller does not exist`.

---

## 12. Frontend Structure

| Route group | Purpose |
|-------------|---------|
| `(business)` | Owner/manager/receptionist: analytics, bookings, calendar, customers, dashboard, payments, pulse, services, settings, staff |
| `(customer)` | Client + portal: `/client/wallet`, `/portal/*` |
| `(specialist)` | Specialist portal: `/specialist-portal/*` |
| `[slug]` | Public salon/provider website (business + specialist sub-routes) |
| `book` / `booking` | Public booking flow |
| `login` / `register` / `forgot-password` / `invite` / `onboarding` | Auth & onboarding |
| `providers` / `specialists` | Discovery / listing |
| `welcome` | Landing |

Component domains: `booking`, `compensation`, `craft`, `dashboard`, `discovery`, `intelligence`, `journey`, `onboarding`, `payments`, `portal`, `provider`, `providers`, `public-provider`, `settings`, `shared`, `specialist`, `ui`, `admin`, `analytics`, `Notifications`.

Contexts: `BrandContext`, `OnboardingContext`, `PortalAuthContext`, `PortalBrandContext`, `RoleContext`, `SpecialistAuthContext`, `TenantBrandContext`, `CategoryContext`.

Hooks: `usePlanFeatures`, `useReverb`, `useProviderBranding`, `useSalonBranding` (legacy), `timezone`.

Services: `api-client`, `portal-api-client`, `PublicProviderExperienceResolver`, specialist API workspace.

Middleware: `middleware.ts` (Next.js route protection).

---

## 13. Observations / Risks (Code-Verified)

1. **Schema refactor mid-flight.** Legacy `salon_*` tables and new `provider/location` tables coexist; seeders duplicated as `Legacy_*`. Risk of inconsistency during transition.
2. **Stale route references.** `api.php` references non-existent controllers (`SalonCapabilityController`, `PublicSalonWebsiteController`, etc.) — will 404 if hit.
3. **Monolithic route file.** `api.php` (~795 lines) mixes all domains; harder to maintain as surface grows.
4. **Guest/walk-in model** is well-designed (`Customer::createGuest`) — suitable for walk-in salons.
5. **Multi-token auth** in frontend adds complexity but cleanly separates portal vs specialist concerns.
6. **Heavy audit/report artifact volume.** Dozens of audit `.md` files indicate an active verification/remediation sprint, possibly pre-launch.
7. **Next.js version warning.** `frontend/AGENTS.md` flags breaking Next.js changes.

---

## 14. Bottom Line

Yo.Salon is a **feature-complete-in-scope, pre-to-early-launch salon SaaS** covering the full beauty-business lifecycle: multi-location provider management, specialist onboarding/portals, customer portals with bookings/loyalty/gift-cards, floor operations, dual-track payments (customer + platform), compensation/settlements, and AI analytics.

It is currently in an **active refactor/verification phase** (migrating salon→provider/location schema, with extensive audit artifacts). The codebase is substantial (~250 migrations, ~100+ models, ~50+ services, large Next.js frontend) but contains stale route references and schema-duplication artifacts that should be cleaned before a production launch.
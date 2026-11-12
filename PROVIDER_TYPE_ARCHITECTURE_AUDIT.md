# Provider Type Architecture Audit

## Executive Summary

**Current State**: The Yo.Salon platform has a multi-purpose architecture at the database level but is salon-centric at the application layer.

**Key Finding**: The `Provider` enum exists with 5 types (`salon`, `independent_specialist`, `spa`, `beauty_school`, `mobile_team`), but only `salon` and `independent_specialist` are functionally supported. The other types would currently be treated as salons in most application logic.

**Impact**: Spa, beauty_school, and mobile_team CAN fit within this architecture, but require:
1. Terminology abstraction (UI/frontend)
2. Business logic differentiation (backend services)
3. Route abstraction (API layer)

---

## Provider Type Enum Definition

**File**: `backend/app/Models/Provider.php`

```php
const TYPE_SALON = 'salon';
const TYPE_SPECIALIST = 'independent_specialist';
const TYPE_SUPPLIER = 'supplier';
const TYPE_SPA = 'spa';
const TYPE_BEAUTY_SCHOOL = 'beauty_school';
const TYPE_MOBILE_TEAM = 'mobile_team';
```

**Database**: `providers` table (migration: `2026_07_31_190000_create_providers_table.php`)

---

## Architecture Matrix

### 1. Database Layer

| Entity | Has provider_id? | Has salon_id? | Provider Type Aware? |
|--------|-----------------|---------------|----------------------|
| Provider | N/A | N/A | ✅ YES (enum field) |
| Salon | ✅ YES | N/A | ❌ NO (no type field) |
| Booking | ✅ YES | ✅ YES | ⚠️ PARTIAL (has both) |
| Service | ✅ YES | ❌ NO | ✅ YES (uses provider_id) |
| Specialist | ❌ NO | ❌ NO | ❌ NO (uses assignments) |
| SpecialistAssignment | ✅ YES | ✅ YES | ⚠️ PARTIAL (has both) |
| CustomerSalon | ❌ NO | ✅ YES | ❌ NO (salon-specific) |
| Subscription | ✅ YES | ❌ NO | ✅ YES (uses provider_id) |
| PaymentMethod | ❌ NO | ✅ YES | ❌ NO (salon-specific) |
| Invoice | ✅ YES | ❌ NO | ✅ YES (uses provider_id) |
| Review | ❌ NO | ❌ NO | ❌ NO (polymorphic) |

**Finding**: Database layer is partially provider-aware. Core entities (Service, Subscription, Invoice) use provider_id. But customer-facing entities (CustomerSalon, PaymentMethod) are salon-specific.

---

### 2. API Routes Layer

**File**: `backend/routes/api.php`

| Route Pattern | Count | Provider Type Aware? | Issue |
|--------------|-------|----------------------|-------|
| `/v1/salons/*` | 30+ | ❌ NO | Hardcoded "salon" in URL |
| `/v1/providers/*` | 2 | ✅ YES | Only index/show |
| `/v1/specialists/*` | 10+ | ⚠️ PARTIAL | Global specialist search |
| `/v1/specialist-portal/*` | 20+ | ✅ YES | Provider-agnostic |

**Critical Issue**: Public discovery and booking routes use `/salons/{id}` pattern. This means:
- A spa would be accessed as `/salons/{spa-slug}`
- A beauty school would be accessed as `/salons/{school-slug}`
- URL structure is salon-specific

---

### 3. Backend Services Layer

| Service | Salon References | Provider Type Aware? | Impact |
|---------|-----------------|----------------------|--------|
| AvailabilityEngine | ✅ YES | ⚠️ PARTIAL | Recently fixed for independent_specialist |
| SubscriptionService | ⚠️ LEGACY | ✅ YES | Has getSubscriptionBySalon() legacy method |
| PaymentRulesEngine | ✅ YES | ❌ NO | All methods take salon_id |
| PortalRecommendationService | ✅ YES | ❌ NO | All methods take salon_id |
| NotificationService | ✅ YES | ❌ NO | SMS/email templates use "salon" |
| LoyaltyService | ✅ YES | ❌ NO | Points tied to salon_id |
| OperationalAnalyticsService | ✅ YES | ❌ NO | All queries use salon_id |
| FinanceAnalyticsService | ✅ YES | ❌ NO | Revenue by salon |
| UsageService | ✅ YES | ❌ NO | Usage tracked by salon_id |
| WebsiteConfigurationResolver | ✅ YES | ❌ NO | Resolve for Salon only |

**Finding**: Most services are salon-centric. They would need provider_type-aware logic to handle spa/beauty_school/mobile_team differently.

---

### 4. Domain Models Layer

| Model | Salon Dependencies | Provider Dependencies | Type Aware? |
|-------|-------------------|---------------------|-------------|
| Booking | salon_id, customer_salon_id | provider_id | ⚠️ PARTIAL |
| Service | ❌ NO | provider_id | ✅ YES |
| Specialist | ❌ NO | ❌ NO | ❌ NO (via assignments) |
| Customer | salons() relationship | ❌ NO | ❌ NO |
| Salon | provider_id | provider() | ⚠️ PARTIAL |
| Provider | ❌ NO | N/A | ✅ YES |
| SpecialistAssignment | salon_id | provider_id | ⚠️ PARTIAL |
| AssignmentSchedule | assignment_id | ❌ NO | ⚠️ PARTIAL (via assignment) |
| SalonSchedule | salon_id | ❌ NO | ❌ NO |

**Finding**: Models are mixed. Some are provider-aware (Service), some are salon-specific (SalonSchedule, CustomerSalon).

---

### 5. Frontend Layer

**File**: `frontend/src/`

| Area | Salon References | Provider Type Aware? |
|------|------------------|----------------------|
| API Client | `salon_id` throughout | ❌ NO |
| Routes | `salonRoutes()` helper | ❌ NO |
| Hooks | `useSalonBranding()` | ❌ NO |
| Components | "Salon" in many component names | ❌ NO |
| Middleware | Tenant logic assumes salon | ❌ NO |
| Contexts | BrandContext (salon-specific) | ❌ NO |
| Services | PublicSalonExperienceResolver | ❌ NO |

**Finding**: Frontend is heavily salon-specific. Terminology is hardcoded throughout.

---

## Provider Type Behavior Analysis

### salon (CURRENTLY FULLY SUPPORTED)

**Characteristics**:
- Has physical location(s)
- Has multiple staff/specialists
- Has operating hours at branch level
- Has service catalog
- Accepts customer bookings
- Has salon-specific branding

**Current Support**: ✅ FULL

**Dependencies**:
- Salon model
- SalonSchedule
- SalonBookingConfig
- Staff model
- Branch-level service catalog (SalonService)

---

### independent_specialist (CURRENTLY PARTIALLY SUPPORTED)

**Characteristics**:
- Single specialist (owner)
- May have own workspace (Provider with type=independent_specialist)
- May work at salons via assignments
- Schedule managed via AssignmentSchedule
- Service catalog at provider level
- Accepts customer bookings directly

**Current Support**: ⚠️ PARTIAL

**What Works**:
- Provider model exists
- AssignmentSchedule exists
- SpecialistPortal exists
- AvailabilityEngine recently fixed

**What Doesn't Work**:
- Discovery treats as salon
- Booking flow uses salon routes
- UI terminology is salon-specific
- Some services still check for SalonSchedule

**Dependencies**:
- Provider model (type=independent_specialist)
- SpecialistAssignment
- AssignmentSchedule
- Service (provider-level)
- SpecialistPortal

---

### spa (NOT FUNCTIONALLY SUPPORTED)

**Characteristics**:
- Similar to salon but with spa-specific services
- May have different operating hours logic
- May have different booking policies (e.g., longer appointments)
- May have different pricing models
- May have different staff roles (e.g., massage therapists)

**Current Support**: ❌ NONE

**Would Be Treated As**: salon

**Required Changes**:
- Service category filtering (spa-specific services)
- Booking policy adjustments (longer durations, different cancellation windows)
- Staff role taxonomy (massage therapist vs stylist)
- Operating hours logic (spa may have different day patterns)
- UI terminology (spa vs salon)

**Dependencies**:
- Provider model (type=spa)
- Same as salon (Salon, SalonSchedule, etc.)
- CraftTaxonomy for spa-specific services

---

### beauty_school (NOT FUNCTIONALLY SUPPORTED)

**Characteristics**:
- Educational institution
- May offer training sessions, not just services
- May have students as staff (apprentices)
- May have different pricing (educational discounts)
- May have different availability (class schedules vs appointments)
- May require instructor-student relationships

**Current Support**: ❌ NONE

**Would Be Treated As**: salon

**Required Changes**:
- Service type differentiation (classes vs services)
- Staff role taxonomy (students, instructors, mentors)
- Booking logic (class enrollment vs appointment booking)
- Pricing models (educational pricing)
- Schedule logic (class schedules with fixed times)
- Progress tracking (student advancement)
- Certification management

**Dependencies**:
- Provider model (type=beauty_school)
- Potentially new models: Class, Enrollment, Curriculum
- Same base models (Service, Specialist) but with different usage patterns

---

### mobile_team (NOT FUNCTIONALLY SUPPORTED)

**Characteristics**:
- No fixed location
- Travels to customers
- Location-based availability (geographic coverage)
- May have different pricing (travel fees)
- May have different booking logic (location selection)
- May have different staff (mobile specialists)

**Current Support**: ❌ NONE

**Would Be Treated As**: salon

**Required Changes**:
- Geographic coverage areas
- Location-based availability (not time-based only)
- Travel fee calculation
- Customer location capture in booking
- Mobile staff management
- Route optimization (for team scheduling)

**Dependencies**:
- Provider model (type=mobile_team)
- Potentially new models: CoverageArea, TravelFee
- Same base models but with location-aware logic

---

## Critical Architectural Issues

### 1. URL Structure is Salon-Specific

**Issue**: All public discovery and booking routes use `/salons/{id}`.

**Impact**: 
- A spa would have URL: `/salons/serenity-spa`
- A beauty school would have URL: `/salons/glamour-academy`
- This is semantically incorrect and confusing

**Required Change**: 
- Abstract routes to `/providers/{id}` or `/workspaces/{id}`
- Or use type-specific routes: `/spas/{id}`, `/schools/{id}`, `/mobile/{id}`

---

### 2. Customer Relationships are Salon-Specific

**Issue**: `CustomerSalon` pivot table ties customers to salons, not providers.

**Impact**:
- Customer loyalty is tracked per salon
- Customer preferences are salon-specific
- Customer wallet is salon-specific
- Independent specialists can't have customer relationships

**Required Change**:
- Introduce `CustomerProvider` pivot table
- Migrate existing CustomerSalon relationships
- Make customer relationships provider-agnostic

---

### 3. Payment Methods are Salon-Specific

**Issue**: `PaymentMethod` model has `salon_id`, not `provider_id`.

**Impact**:
- Independent specialists can't have their own payment methods
- Spa/beauty school would inherit salon payment logic

**Required Change**:
- Add `provider_id` to PaymentMethod
- Make payment methods provider-scoped

---

### 4. Operating Hours Logic is Branch-Centric

**Issue**: `SalonSchedule` and `ProviderSchedule` exist, but logic assumes branch-level hours.

**Impact**:
- Independent specialists use AssignmentSchedule (correct)
- But spa/beauty school/mobile_team would need different hour logic
- Mobile teams need geographic availability, not time availability

**Required Change**:
- Provider-type-aware schedule resolution
- Geographic availability for mobile teams
- Class schedules for beauty schools

---

### 5. UI Terminology is Hardcoded

**Issue**: Frontend uses "salon" throughout - components, hooks, routes, contexts.

**Impact**:
- Spa would be called "salon" in UI
- Beauty school would be called "salon" in UI
- Mobile team would be called "salon" in UI
- Confusing for end users

**Required Change**:
- Implement CategoryContext for terminology
- Replace hardcoded "salon" with category-aware terms
- Update all UI components to use dynamic terminology

---

## Safe Migration Strategy

### Phase 1: Foundation (No Breaking Changes)

**Goal**: Establish provider-type-aware infrastructure without changing existing behavior.

**Steps**:

1. **Create ProviderType Service**
   - New service: `ProviderTypeService`
   - Methods: `getCapabilities($type)`, `getScheduleStrategy($type)`, `getBookingStrategy($type)`
   - Returns configuration based on provider type
   - Default: returns salon behavior for all types

2. **Introduce ProviderType Middleware**
   - Middleware to inject provider type into requests
   - Adds `provider_type` to request context
   - Controllers can access type without querying database

3. **Create ProviderType-Aware Resolvers**
   - New resolvers that check provider type before delegating
   - Example: `ScheduleResolver` that checks type before choosing schedule source
   - Maintain backward compatibility by defaulting to salon behavior

4. **Add Provider Type to API Responses**
   - Include `provider_type` in all provider/salon responses
   - Frontend can use this for UI adaptation
   - No breaking changes - just additive field

**Timeline**: 1-2 weeks

**Risk**: LOW - additive changes only

---

### Phase 2: Abstraction Layer (Minimal Breaking Changes)

**Goal**: Abstract salon-specific concepts to provider-agnostic concepts.

**Steps**:

1. **Introduce Workspace Terminology**
   - Use "workspace" or "provider" in internal code
   - Keep "salon" in public-facing URLs for now (backward compat)
   - Document the terminology mapping

2. **Create Provider-Aware Customer Relationships**
   - Add `CustomerProvider` pivot table
   - Migrate existing `CustomerSalon` to `CustomerProvider`
   - Keep `CustomerSalon` for backward compatibility
   - New code uses `CustomerProvider`, old code uses `CustomerSalon`

3. **Abstract Payment Methods to Provider**
   - Add `provider_id` to PaymentMethod
   - Keep `salon_id` for backward compatibility
   - New code uses `provider_id`, old code uses `salon_id`
   - Migration script to populate provider_id from salon_id

4. **Create Provider-Aware Route Helpers**
   - New helper: `providerRoutes($slug, $type)` 
   - Returns type-specific routes: `/spas/{slug}`, `/schools/{slug}`, etc.
   - Keep `salonRoutes()` for backward compatibility
   - New code uses `providerRoutes()`, old code uses `salonRoutes()`

**Timeline**: 2-3 weeks

**Risk**: MEDIUM - introduces new patterns but maintains old ones

---

### Phase 3: Provider Type Logic (Targeted Breaking Changes)

**Goal**: Implement provider-type-specific business logic.

**Steps**:

1. **Implement Spa-Specific Logic**
   - Service category filtering for spa services
   - Booking policy adjustments (longer appointments)
   - Staff role taxonomy (massage therapists)
   - Operating hours logic (spa-specific patterns)

2. **Implement Beauty School Logic**
   - Service type differentiation (classes vs services)
   - Staff role taxonomy (students, instructors)
   - Booking logic (class enrollment)
   - Pricing models (educational discounts)
   - Schedule logic (class schedules)

3. **Implement Mobile Team Logic**
   - Geographic coverage areas
   - Location-based availability
   - Travel fee calculation
   - Customer location capture
   - Route optimization (if needed)

4. **Update Services to Use ProviderTypeService**
   - Refactor services to check provider type
   - Delegate to type-specific strategies
   - Maintain backward compatibility for salon

**Timeline**: 3-4 weeks

**Risk**: MEDIUM - targeted changes, well-tested

---

### Phase 4: URL & UI Migration (Breaking Changes)

**Goal**: Update public-facing URLs and UI terminology.

**Steps**:

1. **Migrate Public Routes**
   - Introduce `/providers/{slug}` routes
   - Keep `/salons/{slug}` as redirect for backward compatibility
   - Update frontend to use new routes
   - Monitor traffic, eventually deprecate old routes

2. **Update Frontend Terminology**
   - Implement CategoryContext fully
   - Replace hardcoded "salon" with dynamic terms
   - Update all UI components
   - Test all user flows

3. **Update Email/SMS Templates**
   - Make templates provider-type-aware
   - Use dynamic terminology
   - Test all notification flows

4. **Deprecate Salon-Specific Patterns**
   - Remove deprecated routes after transition period
   - Remove deprecated helper methods
   - Remove deprecated database columns
   - Update documentation

**Timeline**: 4-6 weeks

**Risk**: HIGH - public-facing changes, requires careful communication

---

## Refined Architecture: Provider Type → Capabilities → Workspace

**Critical Insight**: Don't think "different dashboard skins." Think "different operational workspaces built on a shared platform foundation."

### Architectural Model

```
Provider
   │
   ├── type (enum)
   │
   └── ProviderTypeDefinition
          │
          ├── capabilities
          ├── terminology
          ├── operational model
          ├── dashboard configuration
          └── experience configuration
```

**Resolution Pattern**:
```
provider.type = salon
        ↓
Salon Provider Definition
        ↓
Salon Workspace

provider.type = spa
        ↓
Spa Provider Definition
        ↓
Spa Workspace

provider.type = beauty_school
        ↓
Beauty School Provider Definition
        ↓
School Workspace
```

### Workspace = Provider Type × Role × Capabilities

**NOT merely Role**.

```
                 YO.SALON PLATFORM
                       │
              Provider Workspace
                       │
          ┌────────────┴────────────┐
          │                         │
     Provider Type              User Role
          │                         │
     ┌────┼────┐              ┌─────┼─────┐
   Salon Spa School          Owner Manager Specialist
          │                         │
          └──────────┬──────────────┘
                     │
              Workspace
```

### Shared Platform vs Divergent Experience

**SHARED** (Platform Foundation):
- Authentication
- Identity
- Provider
- Subscriptions
- Billing
- Notifications
- Media
- Branding
- Permissions
- Customers
- Specialists
- Services
- Bookings
- Analytics infrastructure

**DIVERGENT** (Operational Experience):
- Terminology
- Capabilities
- Operational model
- Workspace layout
- Dashboard metrics
- Quick actions
- Schedule logic
- Booking flow

---

## Stress Points Identified

### 1. Dangerous Default Fallbacks

**Current Anti-Pattern** (DON'T DO THIS):
```typescript
switch (providerType) {
    case 'salon': return SalonOwnerDashboard;
    case 'spa': return SpaOwnerDashboard;
    default: return SalonOwnerDashboard; // DANGEROUS
}
```

**Problem**: Silently degrading unknown provider types to Salon dashboard is architectural leakage.

**Correct Pattern**:
```typescript
switch (providerType) {
    case 'salon': return SalonOwnerDashboard;
    case 'spa': return SpaOwnerDashboard;
    case 'beauty_school': return SchoolOwnerDashboard;
    case 'mobile_team': return MobileOwnerDashboard;
    default: throw new UnsupportedProviderTypeError(providerType);
}
```

**Unknown/unsupported provider type should be EXPLICIT, not silently degraded.**

---

### 2. Blind salon_id Migration is Dangerous

**Problem**: Not all `salon_id` references should become `provider_id`.

**Examples**:

**Genuinely Provider-Capability** (should migrate):
- `Service.provider_id` ✅ Already correct
- `Subscription.provider_id` ✅ Already correct
- `Invoice.provider_id` ✅ Already correct
- `PaymentMethod` (needs provider_id)

**Genuinely Salon-Specific** (should remain):
- `SalonSchedule` (branch-level hours)
- `SalonBookingConfig` (branch-specific policies)
- `Salon` physical location data

**Needs Domain Decision**:
- `CustomerSalon` → Should this be `CustomerProvider`?
- `Booking.salon_id` → Is this branch context or provider context?

**Don't migrate blindly. Make domain decisions.**

---

### 3. Beauty Clinic is Not Yet Defined

**Problem**: `beauty_clinic` exists in enum but operational requirements are unknown.

**Range of Possibilities**:
- Aesthetic/beauty clinic (similar to spa)
- Medical beauty clinic (requires compliance, patient records, medical workflows)

**Don't build imaginary clinic functionality.**

**Only implement beauty_clinic when actual operational requirements are defined.**

---

### 4. Dashboard is Not Just UI

**Problem**: Treating different dashboards as "skin variations" misses the point.

**Reality**: Different provider types have fundamentally different operating models.

**Example**:
- Salon: "Today's bookings" is dominant
- Beauty School: "Today's classes" is dominant
- Mobile Team: "Where are teams?" is dominant

**This is not cosmetic. This is operational.**

---

## Refined Implementation Plan

### Phase 1 — Provider Type Foundation (No Breaking Changes)

**Goal**: Establish the Provider Type → Capabilities → Terminology → Operational Model foundation without implementing every provider yet.

**Deliverables**:

1. **ProviderTypeDefinition Model**
   ```php
   class ProviderTypeDefinition
   {
       public string $type;
       public array $capabilities;
       public array $terminology;
       public array $operationalModel;
       public array $dashboardConfiguration;
       public array $experienceConfiguration;
   }
   ```

2. **ProviderTypeService**
   ```php
   class ProviderTypeService
   {
       public function getDefinition(string $type): ProviderTypeDefinition;
       public function getCapabilities(string $type): array;
       public function getTerminology(string $type): array;
       public function getOperationalModel(string $type): array;
       public function isSupported(string $type): bool;
   }
   ```

3. **Define Salon Type**
   ```php
   'salon' => [
       'capabilities' => ['appointments', 'walk_ins', 'staff', 'retail', 'payments'],
       'terminology' => [
           'customer' => 'customer',
           'staff' => 'staff',
           'specialist' => 'stylist',
           'service' => 'service',
           'booking' => 'booking',
           'walk_in' => 'walk-in',
       ],
       'operational_model' => [
           'primary_unit' => 'booking',
           'schedule_source' => 'salon_schedule',
           'availability_mode' => 'staff_based',
       ],
   ]
   ```

4. **Define Independent Specialist Type**
   ```php
   'independent_specialist' => [
       'capabilities' => ['appointments', 'direct_booking', 'craft'],
       'terminology' => [
           'customer' => 'client',
           'staff' => null, // single specialist
           'specialist' => 'specialist',
           'service' => 'service',
           'booking' => 'appointment',
       ],
       'operational_model' => [
           'primary_unit' => 'appointment',
           'schedule_source' => 'assignment_schedule',
           'availability_mode' => 'specialist_based',
       ],
   ]
   ```

5. **Define Spa Type** (definition only, no implementation)
   ```php
   'spa' => [
       'capabilities' => ['appointments', 'rooms', 'therapists', 'packages', 'retail'],
       'terminology' => [
           'customer' => 'guest',
           'staff' => 'therapist',
           'specialist' => 'therapist',
           'service' => 'treatment',
           'booking' => 'booking',
           'room' => 'treatment room',
       ],
       'operational_model' => [
           'primary_unit' => 'treatment',
           'schedule_source' => 'room_schedule',
           'availability_mode' => 'room_therapist_based',
       ],
   ]
   ```

6. **Define Beauty School Type** (definition only, no implementation)
   ```php
   'beauty_school' => [
       'capabilities' => ['enrollment', 'classes', 'attendance', 'curriculum', 'assessments', 'certification'],
       'terminology' => [
           'customer' => 'student',
           'staff' => 'instructor',
           'specialist' => 'instructor',
           'service' => 'course',
           'booking' => 'class_enrollment',
           'class' => 'class',
           'assessment' => 'assessment',
           'certificate' => 'certificate',
       ],
       'operational_model' => [
           'primary_unit' => 'class',
           'schedule_source' => 'class_schedule',
           'availability_mode' => 'instructor_based',
       ],
   ]
   ```

7. **Define Mobile Team Type** (definition only, no implementation)
   ```php
   'mobile_team' => [
       'capabilities' => ['dispatch', 'geographic_coverage', 'travel', 'mobile_appointments'],
       'terminology' => [
           'customer' => 'customer',
           'staff' => 'team',
           'specialist' => 'specialist',
           'service' => 'service',
           'booking' => 'mobile_appointment',
           'location' => 'service_area',
       ],
       'operational_model' => [
           'primary_unit' => 'mobile_appointment',
           'schedule_source' => 'route_schedule',
           'availability_mode' => 'geographic_based',
       ],
   ]
   ```

**Timeline**: 1-2 weeks

**Risk**: LOW - additive only, no breaking changes

---

### Phase 2 — Make Existing Salon Workspace Consume Provider Type

**Goal**: Don't redesign Salon. Make the current dashboard explicitly become "Provider Workspace → Provider Type = salon → Salon configuration."

**Deliverables**:

1. **Update DashboardController to use ProviderTypeService**
   ```php
   public function liveStats(): JsonResponse
   {
       $provider = auth()->user()->currentProvider();
       $providerType = $provider->type;

       $definition = $this->providerTypeService->getDefinition($providerType);

       if (!$this->providerTypeService->isSupported($providerType)) {
           return response()->json(['error' => 'Unsupported provider type'], 400);
       }

       $stats = match($providerType) {
           'salon' => $this->salonDashboardService->getStats($provider),
           'independent_specialist' => $this->specialistDashboardService->getStats($provider),
           default => throw new UnsupportedProviderTypeError($providerType),
       };

       return response()->json([
           'stats' => $stats,
           'terminology' => $definition->terminology,
           'capabilities' => $definition->capabilities,
       ]);
   }
   ```

2. **Update Frontend to use ProviderTypeContext**
   ```typescript
   // ProviderTypeContext.tsx
   export function ProviderTypeContext({ children }) {
       const { providerType } = useCategory();
       const definition = useProviderTypeDefinition(providerType);

       return (
           <ProviderTypeContext.Provider value={definition}>
               {children}
           </ProviderTypeContext.Provider>
       );
   }
   ```

3. **Make OwnerDashboard consume ProviderTypeDefinition**
   ```typescript
   export default function OwnerDashboard({ userName }: OwnerDashboardProps) {
       const { salonId } = useRole();
       const { terminology, capabilities } = useProviderType();

       // Use terminology instead of hardcoded strings
       const customerTerm = terminology.customer || 'customer';
       const bookingTerm = terminology.booking || 'booking';

       // Use capabilities to show/hide features
       const showWalkIns = capabilities.includes('walk_ins');
       const showRetail = capabilities.includes('retail');
   }
   ```

4. **Add provider_type to all API responses**
   - SalonController responses
   - ProviderController responses
   - Dashboard responses
   - Booking responses

**Timeline**: 1-2 weeks

**Risk**: LOW - refactoring existing code, no breaking changes

**Success Criteria**: Salon dashboard works exactly as before, but now explicitly consumes ProviderTypeDefinition.

---

### Phase 3 — Build Spa Workspace (First Genuinely Different Workspace)

**Goal**: Use Spa as the first experiment because it's close enough to Salon to reuse infrastructure, but different enough to prove the architecture.

**Deliverables**:

1. **Spa-Specific Models** (if needed)
   - `TreatmentRoom` model
   - `TreatmentPackage` model
   - `RoomSchedule` model

2. **SpaDashboardService**
   ```php
   class SpaDashboardService
   {
       public function getStats(Provider $provider): array
       {
           return [
               'treatments_today' => $this->getTreatmentsToday($provider),
               'rooms_occupied' => $this->getRoomsOccupied($provider),
               'therapist_utilization' => $this->getTherapistUtilization($provider),
               'package_sales' => $this->getPackageSales($provider),
               'retail_revenue' => $this->getRetailRevenue($provider),
           ];
       }
   }
   ```

3. **SpaOwnerDashboard Component**
   ```typescript
   export default function SpaOwnerDashboard({ userName }: Props) {
       const { terminology } = useProviderType();

       return (
           <div>
               <h1>Today's Treatments</h1>
               <h1>Room Occupancy</h1>
               <h1>Therapist Availability</h1>
               <h1>Treatment Package Sales</h1>
           </div>
       );
   }
   ```

4. **Spa-Specific Booking Flow**
   - Room selection in addition to therapist selection
   - Treatment package selection
   - Room availability checking

5. **Spa-Specific Schedule Logic**
   - Room-based availability
   - Therapist + room combined availability
   - Treatment duration by room type

**Timeline**: 3-4 weeks

**Risk**: MEDIUM - new functionality, but building on proven foundation

**Success Criteria**: Spa workspace is genuinely different from Salon workspace, proves the architecture works.

---

### Phase 4 — Build Beauty School Workspace (Stress Test)

**Goal**: This is the real stress test because "booking" is no longer the dominant operational concept.

**Deliverables**:

1. **School-Specific Models**
   - `Class` model
   - `Enrollment` model
   - `Curriculum` model
   - `Assessment` model
   - `Certificate` model
   - `StudentProgress` model

2. **SchoolDashboardService**
   ```php
   class SchoolDashboardService
   {
       public function getStats(Provider $provider): array
       {
           return [
               'active_students' => $this->getActiveStudents($provider),
               'classes_today' => $this->getClassesToday($provider),
               'attendance_rate' => $this->getAttendanceRate($provider),
               'assessments_pending' => $this->getAssessmentsPending($provider),
               'certifications_issued' => $this->getCertificationsIssued($provider),
           ];
       }
   }
   ```

3. **SchoolOwnerDashboard Component**
   ```typescript
   export default function SchoolOwnerDashboard({ userName }: Props) {
       return (
           <div>
               <h1>Today's Classes</h1>
               <h1>Active Students</h1>
               <h1>Attendance Rate</h1>
               <h1>Assessments</h1>
               <h1>Certifications</h1>
           </div>
       );
   }
   ```

4. **School-Specific Operations**
   - Class enrollment (not booking)
   - Attendance tracking
   - Assessment recording
   - Certificate generation
   - Curriculum management

5. **School-Specific Schedule Logic**
   - Class schedules (fixed times)
   - Instructor availability
   - Classroom capacity

**Timeline**: 4-6 weeks

**Risk**: HIGH - fundamentally different operational model

**Success Criteria**: Beauty school workspace works with "class" as primary unit, not "booking". Architecture survives this stress test.

---

### Phase 5 — Build Mobile Team Workspace

**Goal**: Test geographically operational provider.

**Deliverables**:

1. **Mobile-Specific Models**
   - `CoverageArea` model
   - `TravelRoute` model
   - `TeamLocation` model

2. **MobileDashboardService**
   ```php
   class MobileDashboardService
   {
       public function getStats(Provider $provider): array
       {
           return [
               'active_teams' => $this->getActiveTeams($provider),
               'teams_on_route' => $this->getTeamsOnRoute($provider),
               'average_travel_time' => $this->getAverageTravelTime($provider),
               'coverage_areas' => $this->getCoverageAreas($provider),
               'unassigned_jobs' => $this->getUnassignedJobs($provider),
           ];
       }
   }
   ```

3. **MobileOwnerDashboard Component**
   ```typescript
   export default function MobileOwnerDashboard({ userName }: Props) {
       return (
           <div>
               <h1>Team Locations</h1>
               <h1>Active Routes</h1>
               <h1>Coverage Areas</h1>
               <h1>Travel Time</h1>
           </div>
       );
   }
   ```

4. **Mobile-Specific Operations**
   - Team dispatch
   - Location tracking
   - Route optimization
   - Geographic availability
   - Travel fee calculation

**Timeline**: 3-4 weeks

**Risk**: MEDIUM - geographic complexity

**Success Criteria**: Mobile workspace handles geographic operations correctly.

---

### Phase 6 — Define Beauty Clinic Domain (Before Implementation)

**Goal**: Only implement beauty_clinic when actual operational requirements are defined.

**Deliverables**:

1. **Stakeholder Interviews**
   - What type of beauty clinic? (aesthetic vs medical)
   - What are the actual operational requirements?
   - What compliance requirements exist?
   - What patient record requirements exist?

2. **Domain Definition**
   - Define actual capabilities needed
   - Define terminology
   - Define operational model
   - Define compliance requirements

3. **Decision Point**
   - If aesthetic clinic: May reuse spa infrastructure
   - If medical clinic: May require entirely new domain models

**Timeline**: 2-4 weeks (discovery only)

**Risk**: LOW - discovery only, no implementation

**Success Criteria**: Clear understanding of what beauty_clinic actually requires before building.

---

## Domain Decisions Required

Before any migration, make these domain decisions:

### 1. CustomerSalon → CustomerProvider?

**Question**: Is the customer relationship with the Provider or the physical Salon?

**Analysis**:
- For salon: Customer visits a specific branch
- For independent specialist: Customer follows the specialist
- For spa: Customer visits the spa location
- For beauty school: Student enrolls in the school
- For mobile team: Customer receives service at their location

**Decision**: Likely needs to be `CustomerProvider` with optional `branch_id` for multi-location providers.

### 2. Booking.salon_id → Branch Context or Provider Context?

**Question**: Is `booking.salon_id` the physical location (branch) or the provider?

**Analysis**:
- For salon: salon_id = branch location
- For independent specialist: salon_id = their workspace (if any)
- For spa: salon_id = spa location
- For mobile team: salon_id = null (no fixed location)

**Decision**: Rename to `branch_id` for clarity. Make it nullable. Keep `provider_id` as the primary relationship.

### 3. SalonSchedule → ProviderSchedule or BranchSchedule?

**Question**: Is schedule at the provider level or branch level?

**Analysis**:
- For salon: Branch-level schedules (different hours per branch)
- For independent specialist: Provider-level schedule
- For spa: May have room-level schedules
- For beauty school: Class schedules (not time-based)

**Decision**: Keep `SalonSchedule` as `BranchSchedule`. Add `ProviderSchedule` for provider-level hours. Make schedule resolution provider-type-aware.

### 4. PaymentMethod.salon_id → provider_id?

**Question**: Are payment methods per branch or per provider?

**Analysis**:
- For salon: May have branch-specific payment methods
- For independent specialist: Provider-level payment methods
- For spa: Provider-level payment methods

**Decision**: Add `provider_id` to PaymentMethod. Keep `salon_id` for branch-specific overrides. Make payment method resolution provider-type-aware.

---

## Conclusion

**Can Spa/Beauty School/Mobile Team Fit?**

✅ **YES**, the architecture CAN support these provider types.

**BUT**, they are currently treated as salons in most of the application. To properly support them:

1. **Database layer** is mostly ready (Provider enum exists, models have provider_id)
2. **API layer** needs route abstraction (currently salon-specific)
3. **Service layer** needs provider-type-aware logic (currently salon-centric)
4. **Frontend layer** needs terminology abstraction (currently hardcoded "salon")

**Safest Approach**: Incremental migration with backward compatibility at each phase. Start with foundation work (Phase 1), then gradually introduce type-specific logic (Phase 2-3), and finally update public-facing URLs/UI (Phase 4).

**Key Principle**: Don't break existing salon functionality while building multi-provider support. Use abstraction layers and backward compatibility to ensure smooth transition.

**Critical Insight**: We're no longer talking about "How do we make the word Salon disappear from the UI?" We're talking about:

> **"How does Yo.Salon become a platform where the provider's nature determines how the business is experienced and operated?"**

That's a much bigger and more coherent architecture.

**Next Steps**:
1. Start with Phase 1 (Provider Type Foundation) - define the ProviderTypeDefinition, ProviderTypeService, and type definitions for all provider types
2. Then Phase 2 - make existing Salon workspace consume the Provider Type foundation
3. Then Phase 3 - build Spa as the first genuinely different workspace to prove the architecture
4. Then Phase 4 - Beauty School as the stress test
5. Then Phase 5 - Mobile Team
6. Then Phase 6 - Define Beauty Clinic domain before implementation

**Don't rush into creating five dashboard implementations.** First establish the foundation, then prove it with the existing Salon, then introduce Spa as the first genuinely different workspace.

1. **Database layer** is mostly ready (Provider enum exists, models have provider_id)
2. **API layer** needs route abstraction (currently salon-specific)
3. **Service layer** needs provider-type-aware logic (currently salon-centric)
4. **Frontend layer** needs terminology abstraction (currently hardcoded "salon")

**Safest Approach**: Incremental migration with backward compatibility at each phase. Start with foundation work (Phase 1), then gradually introduce type-specific logic (Phase 2-3), and finally update public-facing URLs/UI (Phase 4).

**Key Principle**: Don't break existing salon functionality while building multi-provider support. Use abstraction layers and backward compatibility to ensure smooth transition.

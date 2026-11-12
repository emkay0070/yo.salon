# Provider Type Design Review

## Executive Summary

This review refines the Provider Type architecture based on inspection findings. It classifies proposed fields, analyzes hard-coded usage, investigates TYPE_SUPPLIER, and defines the Phase 1 implementation boundary.

**Key Finding**: Provider type is becoming a first-class domain concept, but many proposed fields in the inspection report are premature assumptions. The registry should start with stable, validated semantics only.

---

## A. Proposed Final ProviderTypeDefinition Fields

### Definitely Belongs (Core Semantics)

| Field | Purpose | Why It Belongs | Confidence |
|-------|---------|----------------|------------|
| `type` | Canonical type identifier | Source of truth from database enum | Confirmed |
| `displayName` | Human-readable name | Used in UI, API responses, error messages | Confirmed |
| `isIndependent` | Whether provider is single-owner independent | Distinguishes independent_specialist from multi-staff providers | Confirmed |
| `isOwnerManaged` | Whether owner is automatically considered manager | Independent specialists are owner-managed by definition | Confirmed |

### Needs Validation (Requires Domain Analysis)

| Field | Purpose | Why It Needs Validation | Confidence |
|-------|---------|------------------------|------------|
| `specialistLabel` | Terminology for specialist role | BusinessCategory already has terminology. Need to decide if ProviderType should duplicate or reference it | Needs Validation |
| `specialistPlural` | Plural form of specialist label | Same as above | Needs Validation |
| `customerLabel` | Terminology for customer role | Same as above | Needs Validation |
| `customerPlural` | Plural form of customer label | Same as above | Needs Validation |
| `serviceLabel` | Terminology for service | BusinessCategory has `default_appointment_label`, not service label. Need to validate if this is needed | Needs Validation |
| `bookingLabel` | Terminology for booking | BusinessCategory has `default_appointment_label`. Need to validate relationship | Needs Validation |
| `capabilities` | Array of enabled capabilities | Need to validate against actual Yo.Salon capabilities. Don't invent capabilities that don't exist yet | Needs Validation |
| `scheduleSource` | Which schedule model to use | Currently only salon_schedule and assignment_schedule are implemented. Others are assumptions | Needs Validation |

### Premature / Future Operational Concepts (DO NOT Include in Phase 1)

| Field | Reason |
|-------|--------|
| `bookingMode` | Assumes different booking modes (appointment vs class vs enrollment). Not yet implemented. |
| `locationMode` | Assumes different location models (branch vs workspace vs facility vs campus vs geographic). Not yet implemented. |
| `availabilityMode` | Assumes different availability modes (staff_based vs room_therapist_based vs instructor_based vs geographic_based). Not yet implemented. |
| `room_schedule` | Assumes spa has room schedules. Not implemented. |
| `route_schedule` | Assumes mobile teams have route schedules. Not implemented. |
| `class_schedule` | Assumes beauty schools have class schedules. Not implemented. |
| `geographic_dispatch` | Assumes mobile teams have geographic dispatch. Not implemented. |
| `curriculum` | Assumes beauty schools have curriculum. Not implemented. |
| `assessments` | Assumes beauty schools have assessments. Not implemented. |
| `certification` | Assumes beauty schools have certification. Not implemented. |

**Recommendation**: Start with minimal core fields (type, displayName, isIndependent, isOwnerManaged). Add terminology and capabilities only after validating against existing architecture. Do NOT include any future operational concepts.

---

## B. Provider-Type Behavior Matrix

### salon

| Aspect | Value |
|--------|-------|
| **Operational Meaning** | Multi-staff beauty business with physical location(s) |
| **Terminology** | Uses BusinessCategory terminology (stylist/barber/beauty professional, client/customer, appointment/booking) |
| **Known Capabilities** | Staff, branches, services, bookings, inventory, retail |
| **Known Behavioral Differences** | Uses SalonSchedule for branch-level hours. Uses Staff model for specialists. |
| **Unknown/Future Behavior** | None currently unknown. This is the baseline. |

### independent_specialist

| Aspect | Value |
|--------|-------|
| **Operational Meaning** | Single specialist operating independently (may work at salons via assignments) |
| **Terminology** | Uses BusinessCategory terminology (specialist, client, appointment) |
| **Known Capabilities** | Services, bookings, craft, direct_booking |
| **Known Behavioral Differences** | Uses AssignmentSchedule instead of SalonSchedule. No Staff model (specialist is the provider). Owner-managed by definition. |
| **Unknown/Future Behavior** | None currently unknown. Schedule logic already implemented. |

### spa

| Aspect | Value |
|--------|-------|
| **Operational Meaning** | Wellness facility offering treatments (massage, facials, body treatments) |
| **Terminology** | BusinessCategory has: therapist, guest, treatment |
| **Known Capabilities** | Unknown. Currently treated as salon. |
| **Known Behavioral Differences** | None currently implemented. Would need room-based availability, treatment packages, therapist-specific logic. |
| **Unknown/Future Behavior** | Room schedules, treatment packages, therapist availability, retail. These are future requirements, not current implementation. |

### beauty_school

| Aspect | Value |
|--------|-------|
| **Operational Meaning** | Educational institution for beauty professionals |
| **Terminology** | No BusinessCategory exists. Would need: instructor, student, course, class, enrollment |
| **Known Capabilities** | Unknown. Currently treated as salon. |
| **Known Behavioral Differences** | None currently implemented. Would need class-based scheduling instead of booking-based. |
| **Unknown/Future Behavior** | Class schedules, curriculum, assessments, certifications, student enrollment. These are future requirements, not current implementation. |

### mobile_team

| Aspect | Value |
|--------|-------|
| **Operational Meaning** | Mobile beauty service traveling to customer locations |
| **Terminology** | BusinessCategory has: mobile specialist, client, appointment |
| **Known Capabilities** | Unknown. Currently treated as salon. |
| **Known Behavioral Differences** | None currently implemented. Would need geographic availability, travel logic, team dispatch. |
| **Unknown/Future Behavior** | Geographic coverage, route optimization, team location tracking, travel fees. These are future requirements, not current implementation. |

**Key Insight**: Only `salon` and `independent_specialist` have known behavioral differences. The other three types are currently treated as salons with no differentiated behavior.

---

## C. Hard-Coded Usage Classification

### AvailabilityEngine.php (2 occurrences)

**File**: `backend/app/Services/AvailabilityEngine.php`

**Lines**: 382, 746

**Current Behavior**:
```php
$provider = \App\Models\Provider::find($salon->provider_id);
$isIndependentSpecialist = $provider && $provider->type === \App\Models\Provider::TYPE_SPECIALIST;

if ($isIndependentSpecialist) {
    // Use assignment schedules instead of provider schedules
}
```

**Classification**: Provider type behavior

**Should Registry Handle It?**: YES

**Reason**: This is a legitimate provider-type behavioral difference. Independent specialists use AssignmentSchedule, salons use SalonSchedule. This is exactly the kind of logic the registry should centralize.

**Proposed Registry Method**: `getScheduleSource()` returns `'salon_schedule'` or `'assignment_schedule'`

---

### CustomerSpecialistService.php (1 occurrence)

**File**: `backend/app/Services/CustomerSpecialistService.php`

**Line**: 75

**Current Behavior**:
```php
if ($provider->type === \App\Models\Provider::TYPE_SPECIALIST) {
    return CustomerSpecialist::ORIGIN_INDEPENDENT;
}
```

**Classification**: Provider type behavior

**Should Registry Handle It?**: YES

**Reason**: Provider type affects customer-specialist origin classification. This is a legitimate provider-type semantic.

**Proposed Registry Method**: `getCustomerOrigin()` returns `'salon'` or `'independent'`

---

### SpecialistPortalController.php (3 occurrences)

**File**: `backend/app/Http/Controllers/SpecialistPortalController.php`

**Lines**: 127, 852, 917

**Current Behavior**:
```php
$isOwner = in_array($assignment->role, ['OWNER', 'MANAGER']) || $provider?->type === 'independent_specialist';
```

**Classification**: Provider type behavior + business state

**Should Registry Handle It?**: PARTIALLY

**Reason**: This mixes two concepts:
1. Assignment role (business state): OWNER/MANAGER
2. Provider type (provider type): independent_specialist

The logic is: "If specialist is OWNER/MANAGER OR provider is independent_specialist, then they have owner permissions."

The independent_specialist check is provider-type behavior. The OWNER/MANAGER check is business state.

**Proposed Registry Method**: `isOwnerManaged()` returns boolean for independent_specialist

**Implementation**: Keep the role check in the controller, use registry for the type check:
```php
$isOwner = in_array($assignment->role, ['OWNER', 'MANAGER']) 
    || ProviderTypeRegistry::for($provider->type)->isOwnerManaged();
```

---

### SpecialistCraftService.php (1 occurrence)

**File**: `backend/app/Domain/Specialist/Services/SpecialistCraftService.php`

**Line**: 145

**Current Behavior**:
```php
$isOwner = in_array($row->assignment_role, ['OWNER', 'MANAGER']) || $row->provider_type === 'independent_specialist';
```

**Classification**: Provider type behavior + business state

**Should Registry Handle It?**: PARTIALLY

**Reason**: Same as SpecialistPortalController. Mixes role check with type check.

**Proposed Registry Method**: `isOwnerManaged()` returns boolean

**Implementation**: Same pattern as SpecialistPortalController.

---

### IndependentWorkspaceProvisioner.php (2 occurrences)

**File**: `backend/app/Domain/Specialist/Services/IndependentWorkspaceProvisioner.php`

**Lines**: 27, 42

**Current Behavior**:
```php
->whereHas('provider', function ($query) {
    $query->where('type', Provider::TYPE_SPECIALIST);
})

Provider::firstOrCreate(
    ['email' => $specialist->email, 'type' => Provider::TYPE_SPECIALIST],
```

**Classification**: Provider type identity + provisioning logic

**Should Registry Handle It?**: YES

**Reason**: This is checking if a provider is an independent specialist to provision independent workspace. This is provider-type-specific provisioning logic.

**Proposed Registry Method**: `isIndependent()` returns boolean

**Implementation**: Use registry to check type instead of hard-coded constant.

---

### Product.php / TYPE_SUPPLIER (1 occurrence)

**File**: `backend/app/Domain/Commerce/Catalog/Product.php`

**Line**: 191

**Current Behavior**:
```php
public function isSupplierOwned(): bool
{
    return $this->owner_type === \App\Models\Provider::class 
        && $this->owner?->type === \App\Models\Provider::TYPE_SUPPLIER;
}
```

**Classification**: Commerce/Catalog domain, NOT provider type

**Should Registry Handle It?**: NO

**Reason**: See TYPE_SUPPLIER analysis below. This is about product ownership in the Commerce domain, not operational provider types.

**Action**: Do NOT touch in Phase 1. This is a separate architectural concern.

---

### TeamController.php (1 occurrence)

**File**: `backend/app/Http/Controllers/Api/V1/TeamController.php`

**Line**: 78

**Current Behavior**:
```php
$subtype = isset($assignment->staff_id) ? 'employed_specialist' : 'independent_specialist';
```

**Classification**: Subtype classification

**Should Registry Handle It?**: NO

**Reason**: This is classifying specialists as "employed" vs "independent" based on whether they have a staff_id. This is a subtype classification within the team context, not a provider type check.

The term "independent_specialist" here is confusingly named - it's not checking `provider->type`, it's checking if the specialist is employed (has staff_id) or not.

**Action**: Do NOT move to registry. This is team/subtype logic, not provider type logic. Consider renaming the subtype to avoid confusion.

---

### Tests (8 occurrences)

**File**: `backend/tests/Feature/CustomerWorkspaceBoundaryTest.php`

**Current Behavior**: Hard-coded strings in test data:
```php
$independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);
$salonProvider = Provider::factory()->create(['type' => 'salon']);
```

**Classification**: Test data

**Should Registry Handle It?**: NO

**Reason**: Tests use hard-coded strings for test data. This is fine. Tests should use the actual database enum values.

**Action**: Do NOT change in Phase 1. Can update to use constants later if desired, but not required.

---

## D. TYPE_SUPPLIER Analysis

### What It Is

`TYPE_SUPPLIER` is a constant in `Provider.php`:
```php
const TYPE_SUPPLIER = 'supplier';
```

However, `supplier` is NOT a valid value in the `providers.type` database enum:
```php
$table->enum('type', ['salon', 'independent_specialist', 'spa', 'beauty_school', 'mobile_team'])->default('salon');
```

### Where It Is Used

Only one location uses `TYPE_SUPPLIER`:
- `Product.php` line 191: Checks if product is supplier-owned

### What It Represents

From `Product.php` documentation:
```php
/**
 * Products have polymorphic ownership:
 * - Platform (global catalog)
 * - Supplier (supplier products)
 * - Salon (private formulas)
 */
```

From `Brand.php` documentation:
```php
/**
 * CRITICAL DISTINCTION: A brand is NOT a supplier.
 * 
 * Example:
 * - Brand: American Crew (the product brand)
 * - Supplier: Beauty Wholesale Uganda (the distributor)
 * 
 * WHY SEPARATE?
 * - Multiple suppliers can distribute the same brand
 * - A supplier can distribute multiple brands
 * - Brand-specific analytics and marketing
 * - Brand-specific customer preferences
 */
```

### Domain Model Analysis

**Product Ownership**:
- `owner_type` can be: `Platform::class`, `Provider::class`, `Salon::class`
- If `owner_type === Provider::class` AND `owner->type === 'supplier'`, then product is supplier-owned
- Supplier-owned products are uploaded by suppliers and can be browsed/ordered by salons

**Key Insight**: In the Commerce/Catalog domain, "supplier" represents a product distributor, NOT an operational provider type like salon/spa.

### Is It a Provider?

**NO**. The constant name is misleading. "Supplier" in this context is:
- A product distributor in the Commerce/Catalog domain
- Used for product ownership classification
- NOT an operational business type that needs bookings, schedules, specialists, etc.

### What It Should Be

**Option 1**: Remove `TYPE_SUPPLIER` from Provider model entirely. It doesn't belong there.

**Option 2**: Keep it as a legacy constant but document that it's for Commerce domain only, not operational provider types.

**Option 3**: Create a separate `Supplier` model in the Commerce domain for product suppliers.

**Recommendation**: Option 1 or 2. Do NOT add `supplier` to the `providers.type` enum. It's a different domain concept.

### Action for Phase 1

**DO NOT touch TYPE_SUPPLIER.** It's a separate architectural concern. Leave it as-is until we decide on the Commerce/Catalog domain model.

---

## E. Registry Implementation Recommendation

### Proposed Static Registry

```php
class ProviderTypeRegistry
{
    private static array $definitions = [];

    public static function register(string $type, ProviderTypeDefinition $definition): void
    {
        self::$definitions[$type] = $definition;
    }

    public static function for(string $type): ProviderTypeDefinition
    {
        if (!isset(self::$definitions[$type])) {
            throw new UnsupportedProviderTypeError($type);
        }
        return self::$definitions[$type];
    }

    public static function initialize(): void
    {
        self::register('salon', new ProviderTypeDefinition(...));
        self::register('independent_specialist', new ProviderTypeDefinition(...));
        // ...
    }
}
```

### Laravel Service/Container-Based Alternative

```php
// In ServiceProvider
$this->app->singleton(ProviderTypeRegistry::class, function () {
    $registry = new ProviderTypeRegistry();
    $registry->register('salon', new ProviderTypeDefinition(...));
    $registry->register('independent_specialist', new ProviderTypeDefinition(...));
    return $registry;
});

// Usage
$registry = app(ProviderTypeRegistry::class);
$definition = $registry->for('salon');
```

### Comparison

| Aspect | Static Registry | Container-Based |
|--------|----------------|------------------|
| **Simplicity** | Simple, no DI needed | Requires DI, more boilerplate |
| **Testability** | Harder to mock (static state) | Easier to mock (container) |
| **Lifecycle** | Manual initialization | Automatic via service provider |
| **State Mutability** | Mutable static state (risky) | Immutable instance (safer) |
| **Laravel Integration** | Feels non-Laravel | Feels more Laravel-native |

### Recommendation

**Use Laravel service/container-based approach.**

**Reasons**:
1. **Testability**: Easier to mock in tests without static state issues
2. **Immutability**: Instance-based registry is safer than mutable static state
3. **Laravel Integration**: Feels more natural in Laravel ecosystem
4. **No Complexity**: The additional boilerplate is minimal (just a service provider)

**Implementation**:
```php
// backend/app/Domain/Provider/ProviderTypeRegistry.php
class ProviderTypeRegistry
{
    private array $definitions = [];

    public function register(string $type, ProviderTypeDefinition $definition): void
    {
        $this->definitions[$type] = $definition;
    }

    public function for(string $type): ProviderTypeDefinition
    {
        if (!isset($this->definitions[$type])) {
            throw new UnsupportedProviderTypeError($type);
        }
        return $this->definitions[$type];
    }

    public function isSupported(string $type): bool
    {
        return isset($this->definitions[$type]);
    }
}

// backend/app/Providers/ProviderTypeServiceProvider.php
class ProviderTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderTypeRegistry::class, function () {
            $registry = new ProviderTypeRegistry();
            $registry->register('salon', new ProviderTypeDefinition(...));
            $registry->register('independent_specialist', new ProviderTypeDefinition(...));
            return $registry;
        });
    }
}

// Usage
$registry = app(ProviderTypeRegistry::class);
$definition = $registry->for($provider->type);
```

---

## F. Factory Recommendation

### Current Factory

```php
'type' => $this->faker->randomElement([Provider::TYPE_SALON, Provider::TYPE_SPECIALIST]),
```

### Problem with Randomizing All Types

If we change to:
```php
'type' => $this->faker->randomElement([
    Provider::TYPE_SALON, 
    Provider::TYPE_SPECIALIST,
    Provider::TYPE_SPA,
    Provider::TYPE_BEAUTY_SCHOOL,
    Provider::TYPE_MOBILE_TEAM,
]),
```

**Issue**: Existing tests that don't specify provider type will now randomly get spa/beauty_school/mobile_team, which may cause unexpected behavior since those types have no differentiated logic yet.

### Recommendation

**Keep `salon` as the default. Add explicit state methods for other types.**

```php
// Default: salon
public function definition(): array
{
    return [
        'type' => Provider::TYPE_SALON,
        // ...
    ];
}

// Explicit state for independent specialist
public function independentSpecialist(): self
{
    return $this->state(fn () => ['type' => Provider::TYPE_SPECIALIST]);
}

// Explicit state for spa
public function spa(): self
{
    return $this->state(fn () => ['type' => Provider::TYPE_SPA]);
}

// Explicit state for beauty school
public function beautySchool(): self
{
    return $this->state(fn () => ['type' => Provider::TYPE_BEAUTY_SCHOOL]);
}

// Explicit state for mobile team
public function mobileTeam(): self
{
    return $this->state(fn () => ['type' => Provider::TYPE_MOBILE_TEAM]);
}
```

**Usage**:
```php
// Default (salon)
$provider = Provider::factory()->create();

// Explicit independent specialist
$provider = Provider::factory()->independentSpecialist()->create();

// Explicit spa
$provider = Provider::factory()->spa()->create();
```

**Benefits**:
- Existing tests remain deterministic (default is salon)
- New tests can explicitly choose provider type
- No unexpected behavior from random types

---

## G. Phase 1 Boundary: What NOT to Touch

### DO NOT Touch in Phase 1

#### 1. TYPE_SUPPLIER
- Do NOT add `supplier` to `providers.type` enum
- Do NOT remove `TYPE_SUPPLIER` constant from Provider model
- Do NOT modify Product.php `isSupplierOwned()` method
- Leave as-is until Commerce/Catalog domain is analyzed

#### 2. BusinessCategory
- Do NOT merge BusinessCategory with ProviderType
- Do NOT change BusinessCategory terminology fields
- Do NOT modify CategoryContext in frontend
- Keep them separate (industry vertical vs operational model)

#### 3. Future Operational Concepts
- Do NOT add `room_schedule`, `route_schedule`, `class_schedule` to registry
- Do NOT add `bookingMode`, `locationMode`, `availabilityMode` to registry
- Do NOT add `curriculum`, `assessments`, `certification` to registry
- Do NOT add `geographic_dispatch` to registry
- These are future requirements, not current implementation

#### 4. TeamController Subtype Logic
- Do NOT move TeamController subtype classification to ProviderTypeRegistry
- The `'independent_specialist'` string there is a subtype, not a provider type
- Consider renaming the subtype to avoid confusion, but not in Phase 1

#### 5. Test Data
- Do NOT change test data hard-coded strings
- Tests using `['type' => 'independent_specialist']` are fine
- Can update to use constants later, but not required in Phase 1

#### 6. Salon-Specific Models
- Do NOT migrate `SalonSchedule` to generic model
- Do NOT change `SalonBookingConfig` to provider-level
- Do NOT modify `CustomerSalon` pivot table
- These require domain decisions, not provider type foundation

#### 7. Payment Methods
- Do NOT add `provider_id` to PaymentMethod in Phase 1
- Do NOT change payment method logic
- This requires domain decision about branch vs provider payment methods

#### 8. Booking.salon_id
- Do NOT rename `booking.salon_id` to `branch_id` in Phase 1
- Do NOT make it nullable in Phase 1
- This requires domain decision about branch context vs provider context

#### 9. Frontend UI
- Do NOT modify frontend components in Phase 1
- Do NOT change CategoryContext
- Do NOT update dashboard components
- Phase 1 is backend foundation only

#### 10. API Routes
- Do NOT change API routes in Phase 1
- Do NOT add `/providers/` routes
- Do NOT modify `/salons/` routes
- Phase 1 is foundation, not API changes

### Phase 1 Scope (What TO Do)

**DO** implement in Phase 1:

1. Create `ProviderTypeDefinition` DTO with minimal fields:
   - `type`
   - `displayName`
   - `isIndependent`
   - `isOwnerManaged`

2. Create `ProviderTypeRegistry` as Laravel service:
   - `register()`
   - `for()`
   - `isSupported()`

3. Create `UnsupportedProviderTypeError` exception

4. Create `ProviderTypeServiceProvider`:
   - Register all 5 provider types with minimal definitions
   - Register as singleton in container

5. Update `Provider.php` model:
   - Add missing constants (TYPE_SPA, TYPE_BEAUTY_SCHOOL, TYPE_MOBILE_TEAM)
   - Add `getTypeDefinition()` helper method (optional, can defer)

6. Update `ProviderFactory.php`:
   - Keep default as `salon`
   - Add explicit state methods for other types

7. Register service provider in `config/app.php`

8. Create unit tests for registry

**DO NOT** implement in Phase 1:

- Terminology fields (specialistLabel, customerLabel, etc.)
- Capabilities array
- Schedule source logic
- Any refactoring of existing code
- Any domain model changes
- Any frontend changes
- Any API changes

---

## H. Success Criteria for Phase 1

- [ ] ProviderTypeRegistry exists as Laravel service
- [ ] All 5 provider types registered with minimal definitions
- [ ] Registry throws explicit error for unsupported types
- [ ] Provider model has all constants
- [ ] Factory has explicit state methods
- [ ] Unit tests pass for registry
- [ ] No existing code is broken
- [ ] No existing tests fail
- [ ] No domain models changed
- [ ] No API routes changed
- [ ] No frontend code changed

---

## I. Next Steps After Phase 1

After Phase 1 is approved and implemented:

1. **Phase 2**: Add terminology fields to ProviderTypeDefinition (validate against BusinessCategory)
2. **Phase 2**: Refactor AvailabilityEngine to use registry
3. **Phase 2**: Refactor CustomerSpecialistService to use registry
4. **Phase 2**: Refactor SpecialistPortalController to use registry
5. **Phase 2**: Refactor SpecialistCraftService to use registry
6. **Phase 2**: Refactor IndependentWorkspaceProvisioner to use registry
7. **Phase 3**: Add capabilities field (validate against actual Yo.Salon capabilities)
8. **Phase 3**: Add schedule source field (validate against existing schedule models)
9. **Phase 4**: Decide on TYPE_SUPPLIER (Commerce domain analysis)
10. **Phase 5**: Domain decisions for SalonSchedule, CustomerSalon, PaymentMethod, Booking.salon_id

---

## Conclusion

The Provider Type foundation should start with **minimal, validated semantics only**. Many proposed fields in the inspection report are premature assumptions about future functionality that doesn't exist yet.

**Key Principles**:
1. Don't invent functionality that doesn't exist
2. Don't assume spa/beauty_school/mobile_team have specific behaviors
3. Validate terminology against BusinessCategory before duplicating
4. Keep Phase 1 scoped to foundation only
5. Use Laravel service container instead of static registry
6. Keep factory deterministic with explicit state methods
7. Clearly define what NOT to touch in Phase 1

**Phase 1 is about establishing the authoritative definition, not refactoring existing code.**

# Provider Type Inspection Report

## Executive Summary

**Finding**: Provider type exists at the database level and is partially used in the codebase, but there is NO authoritative Provider Type Definition/Registry. Type checks are scattered throughout the codebase using hard-coded string comparisons.

**Recommendation**: Create a centralized ProviderTypeRegistry to establish a single source of truth for provider type definitions (capabilities, terminology, operational model).

---

## 1. Where Provider Type Currently Exists

### Database Layer

**File**: `backend/database/migrations/2026_07_31_190000_create_providers_table.php`

```php
$table->enum('type', ['salon', 'independent_specialist', 'spa', 'beauty_school', 'mobile_team'])->default('salon');
```

**Canonical Provider Types**:
- `salon` (default)
- `independent_specialist`
- `spa`
- `beauty_school`
- `mobile_team`

**Note**: The database enum is the canonical source of truth for valid provider types.

---

### Model Layer

**File**: `backend/app/Models/Provider.php`

```php
const TYPE_SALON = 'salon';
const TYPE_SPECIALIST = 'independent_specialist';
const TYPE_SUPPLIER = 'supplier';
```

**Issue**: 
- `TYPE_SUPPLIER` exists in the model but NOT in the database enum
- Missing constants for `spa`, `beauty_school`, `mobile_team`
- Incomplete constant coverage

---

### Factory Layer

**File**: `backend/database/factories/ProviderFactory.php`

```php
'type' => $this->faker->randomElement([Provider::TYPE_SALON, Provider::TYPE_SPECIALIST]),
```

**Issue**: Only generates `salon` or `independent_specialist`, never `spa`, `beauty_school`, or `mobile_team`.

---

### API Validation Layer

**File**: `backend/app/Http/Controllers/ProviderController.php`

```php
'type' => 'required|in:salon,independent_specialist,spa,beauty_school,mobile_team',
```

**Status**: ✅ Correctly validates against database enum values.

---

## 2. Where Provider Types Are Hard-Coded

### Backend Services

**File**: `backend/app/Services/AvailabilityEngine.php`

```php
$provider = \App\Models\Provider::find($salon->provider_id);
$isIndependentSpecialist = $provider && $provider->type === \App\Models\Provider::TYPE_SPECIALIST;

if ($isIndependentSpecialist) {
    // Use assignment schedules instead of provider schedules
}
```

**Pattern**: Direct string comparison using `Provider::TYPE_SPECIALIST` constant.

**Occurrences**: 2 locations (lines 382, 746)

---

**File**: `backend/app/Services/CustomerSpecialistService.php`

```php
if ($provider->type === \App\Models\Provider::TYPE_SPECIALIST) {
    return CustomerSpecialist::ORIGIN_INDEPENDENT;
}
```

**Pattern**: Direct string comparison using `Provider::TYPE_SPECIALIST` constant.

**Occurrences**: 1 location (line 75)

---

**File**: `backend/app/Http/Controllers/SpecialistPortalController.php`

```php
$isOwner = in_array($assignment->role, ['OWNER', 'MANAGER']) || $provider?->type === 'independent_specialist';
```

**Pattern**: Direct string comparison using hard-coded string `'independent_specialist'`.

**Occurrences**: 3 locations (lines 127, 852, 917)

**Issue**: Inconsistent - sometimes uses constant, sometimes hard-coded string.

---

**File**: `backend/app/Domain/Specialist/Services/SpecialistCraftService.php`

```php
$isOwner = in_array($row->assignment_role, ['OWNER', 'MANAGER']) || $row->provider_type === 'independent_specialist';
```

**Pattern**: Direct string comparison using hard-coded string `'independent_specialist'`.

**Occurrences**: 1 location (line 145)

**Issue**: Uses `provider_type` from query result, not model constant.

---

**File**: `backend/app/Domain/Specialist/Services/IndependentWorkspaceProvisioner.php`

```php
->whereHas('provider', function ($query) {
    $query->where('type', Provider::TYPE_SPECIALIST);
})

Provider::firstOrCreate(
    ['email' => $specialist->email, 'type' => Provider::TYPE_SPECIALIST],
```

**Pattern**: Direct string comparison using `Provider::TYPE_SPECIALIST` constant.

**Occurrences**: 2 locations (lines 27, 42)

---

**File**: `backend/app/Domain/Commerce/Catalog/Product.php`

```php
return $this->owner_type === \App\Models\Provider::class 
    && $this->owner?->type === \App\Models\Provider::TYPE_SUPPLIER;
```

**Pattern**: Direct string comparison using `Provider::TYPE_SUPPLIER` constant.

**Occurrences**: 1 location (line 191)

**Issue**: Uses `TYPE_SUPPLIER` which doesn't exist in database enum.

---

**File**: `backend/app/Http/Controllers/Api/V1/TeamController.php`

```php
$subtype = isset($assignment->staff_id) ? 'employed_specialist' : 'independent_specialist';
```

**Pattern**: Hard-coded string `'independent_specialist'` for subtype classification.

**Occurrences**: 1 location (line 78)

---

### Tests

**File**: `backend/tests/Feature/CustomerWorkspaceBoundaryTest.php`

```php
$independentProvider = Provider::factory()->create(['type' => 'independent_specialist']);
$salonProvider = Provider::factory()->create(['type' => 'salon']);
```

**Pattern**: Hard-coded strings in test data.

**Occurrences**: 8 locations throughout the test file.

---

## 3. Whether Provider Type Definition/Registry Already Exists

**Answer**: NO.

There is NO ProviderTypeDefinition model, NO ProviderTypeRegistry service, and NO centralized provider type configuration.

### Partially Related: BusinessCategory System

**File**: `backend/app/Models/BusinessCategory.php```php
protected $fillable = [
    'slug',
    'name',
    'short_name',
    'description',
    'icon',
    'is_active',
    'sort_order',
    'default_experience_family_id',
    'default_specialist_label',
    'default_specialist_plural',
    'default_appointment_label',
    'default_customer_term',
];
```

**File**: `backend/database/seeders/BusinessCategorySeeder.php`

```php
'spa' => [
    'default_specialist_label' => 'Therapist',
    'default_specialist_plural' => 'Therapists',
    'default_appointment_label' => 'Treatment',
    'default_customer_term' => 'Guest',
],
```

**Analysis**:
- BusinessCategory has terminology fields (specialist_label, customer_term, appointment_label)
- This is similar to what a ProviderTypeDefinition would need
- BUT BusinessCategory is for onboarding categorization (hair_salon, barbershop, spa, etc.)
- Provider.type is for operational type (salon, independent_specialist, spa, beauty_school, mobile_team)
- These are DIFFERENT concepts

**Relationship**:
- A Provider with type='spa' might have BusinessCategory='spa'
- A Provider with type='salon' might have BusinessCategory='hair_salon' or 'beauty_salon'
- BusinessCategory is about industry vertical
- Provider.type is about operational model

**Conclusion**: BusinessCategory terminology system is NOT a substitute for ProviderTypeDefinition. They serve different purposes.

---

### Frontend: CategoryContext

**File**: `frontend/src/contexts/CategoryContext.tsx`

```typescript
interface CategoryContextType {
  terminology: {
    specialist: string;
    specialistPlural: string;
    appointment: string;
    appointmentPlural: string;
    customer: string;
    customerPlural: string;
    business: string;
  };
}
```

**Analysis**:
- CategoryContext provides terminology from BusinessCategory
- NOT from Provider.type
- This is for onboarding/industry terminology
- NOT for provider-type-specific operational terminology

**Conclusion**: CategoryContext is NOT a ProviderTypeDefinition. It's BusinessCategory terminology.

---

## 4. Which Existing Code Can Consume New Definition Safely

### Safe to Consume (Low Risk)

These locations check provider type for operational logic and would benefit from a centralized definition:

1. **AvailabilityEngine.php** (2 locations)
   - Current: Checks `TYPE_SPECIALIST` to determine schedule source
   - Safe: Can use `ProviderTypeRegistry::for($provider->type)->getScheduleSource()`
   - Benefit: Centralized schedule logic, easier to add new types

2. **CustomerSpecialistService.php** (1 location)
   - Current: Checks `TYPE_SPECIALIST` to determine origin
   - Safe: Can use `ProviderTypeRegistry::for($provider->type)->getCustomerOrigin()`
   - Benefit: Centralized origin logic

3. **SpecialistPortalController.php** (3 locations)
   - Current: Checks `'independent_specialist'` to determine ownership
   - Safe: Can use `ProviderTypeRegistry::for($provider->type)->isOwnerManaged()`
   - Benefit: Centralized ownership logic

4. **SpecialistCraftService.php** (1 location)
   - Current: Checks `'independent_specialist'` to determine ownership
   - Safe: Can use `ProviderTypeRegistry::for($provider->type)->isOwnerManaged()`
   - Benefit: Consistent ownership logic

5. **IndependentWorkspaceProvisioner.php** (2 locations)
   - Current: Checks `TYPE_SPECIALIST` for workspace provisioning
   - Safe: Can use `ProviderTypeRegistry::for($provider->type)->requiresIndependentWorkspace()`
   - Benefit: Centralized workspace logic

### Requires Careful Analysis (Medium Risk)

6. **Product.php** (1 location)
   - Current: Checks `TYPE_SUPPLIER` for supplier ownership
   - Issue: `TYPE_SUPPLIER` doesn't exist in database enum
   - Safe: Can use `ProviderTypeRegistry::for($provider->type)->isSupplier()`
   - BUT: Need to decide if `supplier` should be a valid provider type

7. **TeamController.php** (1 location)
   - Current: Uses `'independent_specialist'` as subtype classification
   - Safe: Can use `ProviderTypeRegistry::for($provider->type)->getSubtype()`
   - Question: Is this subtype logic or provider type logic?

### Not Safe to Consume Yet (High Risk)

8. **Tests** (8 locations)
   - Current: Hard-coded strings in test data
   - Safe: Can use ProviderTypeRegistry constants
   - BUT: Should update tests AFTER registry is implemented and tested

---

## 5. Proposed Files/Classes to Add or Modify

### Files to ADD

#### 1. ProviderTypeDefinition (Data Transfer Object)

**Path**: `backend/app/Domain/Provider/ProviderTypeDefinition.php`

**Purpose**: Define the structure of a provider type definition.

```php
namespace App\Domain\Provider;

class ProviderTypeDefinition
{
    public function __construct(
        public readonly string $type,
        public readonly string $displayName,
        public readonly string $specialistLabel,
        public readonly string $specialistPlural,
        public readonly string $customerLabel,
        public readonly string $customerPlural,
        public readonly string $serviceLabel,
        public readonly string $bookingLabel,
        public readonly string $bookingMode,
        public readonly string $locationMode,
        public readonly array $capabilities,
        public readonly string $scheduleSource,
        public readonly string $availabilityMode,
        public readonly bool $isIndependent,
        public readonly bool $isOwnerManaged,
    ) {}
}
```

---

#### 2. ProviderTypeRegistry (Service)

**Path**: `backend/app/Domain/Provider/ProviderTypeRegistry.php`

**Purpose**: Central registry of provider type definitions.

```php
namespace App\Domain\Provider;

use Illuminate\Support\Facades\App;

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

    public static function isSupported(string $type): bool
    {
        return isset(self::$definitions[$type]);
    }

    public static function all(): array
    {
        return self::$definitions;
    }

    public static function initialize(): void
    {
        // Register all provider types
        self::register('salon', self::salonDefinition());
        self::register('independent_specialist', self::independentSpecialistDefinition());
        self::register('spa', self::spaDefinition());
        self::register('beauty_school', self::beautySchoolDefinition());
        self::register('mobile_team', self::mobileTeamDefinition());
    }

    private static function salonDefinition(): ProviderTypeDefinition
    {
        return new ProviderTypeDefinition(
            type: 'salon',
            displayName: 'Salon',
            specialistLabel: 'Stylist',
            specialistPlural: 'Stylists',
            customerLabel: 'Client',
            customerPlural: 'Clients',
            serviceLabel: 'Service',
            bookingLabel: 'Appointment',
            bookingMode: 'appointment',
            locationMode: 'branch',
            capabilities: ['staff', 'branches', 'services', 'bookings', 'inventory', 'retail'],
            scheduleSource: 'salon_schedule',
            availabilityMode: 'staff_based',
            isIndependent: false,
            isOwnerManaged: false,
        );
    }

    private static function independentSpecialistDefinition(): ProviderTypeDefinition
    {
        return new ProviderTypeDefinition(
            type: 'independent_specialist',
            displayName: 'Independent Specialist',
            specialistLabel: 'Specialist',
            specialistPlural: 'Specialists',
            customerLabel: 'Client',
            customerPlural: 'Clients',
            serviceLabel: 'Service',
            bookingLabel: 'Appointment',
            bookingMode: 'appointment',
            locationMode: 'workspace',
            capabilities: ['services', 'bookings', 'craft', 'direct_booking'],
            scheduleSource: 'assignment_schedule',
            availabilityMode: 'specialist_based',
            isIndependent: true,
            isOwnerManaged: true,
        );
    }

    private static function spaDefinition(): ProviderTypeDefinition
    {
        return new ProviderTypeDefinition(
            type: 'spa',
            displayName: 'Spa',
            specialistLabel: 'Therapist',
            specialistPlural: 'Therapists',
            customerLabel: 'Guest',
            customerPlural: 'Guests',
            serviceLabel: 'Treatment',
            bookingLabel: 'Booking',
            bookingMode: 'appointment',
            locationMode: 'facility',
            capabilities: ['rooms', 'therapists', 'treatments', 'packages', 'bookings', 'retail'],
            scheduleSource: 'room_schedule',
            availabilityMode: 'room_therapist_based',
            isIndependent: false,
            isOwnerManaged: false,
        );
    }

    private static function beautySchoolDefinition(): ProviderTypeDefinition
    {
        return new ProviderTypeDefinition(
            type: 'beauty_school',
            displayName: 'Beauty School',
            specialistLabel: 'Instructor',
            specialistPlural: 'Instructors',
            customerLabel: 'Student',
            customerPlural: 'Students',
            serviceLabel: 'Course',
            bookingLabel: 'Enrollment',
            bookingMode: 'class',
            locationMode: 'campus',
            capabilities: ['instructors', 'courses', 'classes', 'students', 'curriculum', 'assessments', 'certification'],
            scheduleSource: 'class_schedule',
            availabilityMode: 'instructor_based',
            isIndependent: false,
            isOwnerManaged: false,
        );
    }

    private static function mobileTeamDefinition(): ProviderTypeDefinition
    {
        return new ProviderTypeDefinition(
            type: 'mobile_team',
            displayName: 'Mobile Team',
            specialistLabel: 'Specialist',
            specialistPlural: 'Specialists',
            customerLabel: 'Customer',
            customerPlural: 'Customers',
            serviceLabel: 'Service',
            bookingLabel: 'Mobile Appointment',
            bookingMode: 'appointment',
            locationMode: 'geographic',
            capabilities: ['teams', 'dispatch', 'geographic_coverage', 'travel', 'mobile_appointments'],
            scheduleSource: 'route_schedule',
            availabilityMode: 'geographic_based',
            isIndependent: false,
            isOwnerManaged: false,
        );
    }
}
```

---

#### 3. UnsupportedProviderTypeError (Exception)

**Path**: `backend/app/Domain/Provider/Exceptions/UnsupportedProviderTypeError.php`

**Purpose**: Explicit error for unsupported provider types (no silent fallback).

```php
namespace App\Domain\Provider\Exceptions;

use Exception;

class UnsupportedProviderTypeError extends Exception
{
    public function __construct(string $type)
    {
        parent::__construct("Provider type '{$type}' is not supported.");
    }
}
```

---

#### 4. ProviderTypeServiceProvider (Laravel Service Provider)

**Path**: `backend/app/Providers/ProviderTypeServiceProvider.php`

**Purpose**: Initialize the registry on application boot.

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Provider\ProviderTypeRegistry;

class ProviderTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ProviderTypeRegistry::initialize();
    }
}
```

---

#### 5. ProviderTypeRegistryTest (Test)

**Path**: `backend/tests/Unit/Domain/Provider/ProviderTypeRegistryTest.php`

**Purpose**: Test the registry functionality.

```php
namespace Tests\Unit\Domain\Provider;

use App\Domain\Provider\ProviderTypeRegistry;
use App\Domain\Provider\Exceptions\UnsupportedProviderTypeError;
use PHPUnit\Framework\TestCase;

class ProviderTypeRegistryTest extends TestCase
{
    public function test_it_returns_definition_for_supported_type()
    {
        $definition = ProviderTypeRegistry::for('salon');
        
        $this->assertEquals('salon', $definition->type);
        $this->assertEquals('Salon', $definition->displayName);
        $this->assertEquals('Stylist', $definition->specialistLabel);
    }

    public function it_throws_exception_for_unsupported_type()
    {
        $this->expectException(UnsupportedProviderTypeError::class);
        
        ProviderTypeRegistry::for('unknown_type');
    }

    public function test_it_checks_if_type_is_supported()
    {
        $this->assertTrue(ProviderTypeRegistry::isSupported('salon'));
        $this->assertFalse(ProviderTypeRegistry::isSupported('unknown_type'));
    }

    public function test_it_returns_all_definitions()
    {
        $all = ProviderTypeRegistry::all();
        
        $this->assertArrayHasKey('salon', $all);
        $this->assertArrayHasKey('independent_specialist', $all);
        $this->assertArrayHasKey('spa', $all);
        $this->assertArrayHasKey('beauty_school', $all);
        $this->assertArrayHasKey('mobile_team', $all);
    }
}
```

---

### Files to MODIFY

#### 1. Provider.php (Model)

**Path**: `backend/app/Models/Provider.php`

**Changes**:
- Add missing constants for `spa`, `beauty_school`, `mobile_team`
- Remove or document `TYPE_SUPPLIER` (decide if supplier should be a valid type)
- Add helper method to get type definition

```php
const TYPE_SALON = 'salon';
const TYPE_SPECIALIST = 'independent_specialist';
const TYPE_SPA = 'spa';
const TYPE_BEAUTY_SCHOOL = 'beauty_school';
const TYPE_MOBILE_TEAM = 'mobile_team';
// TODO: Decide if TYPE_SUPPLIER should be a valid provider type
// const TYPE_SUPPLIER = 'supplier';

public function getTypeDefinition(): \App\Domain\Provider\ProviderTypeDefinition
{
    return \App\Domain\Provider\ProviderTypeRegistry::for($this->type);
}
```

---

#### 2. ProviderFactory.php

**Path**: `backend/database/factories/ProviderFactory.php`

**Changes**:
- Update to use all provider type constants

```php
'type' => $this->faker->randomElement([
    Provider::TYPE_SALON, 
    Provider::TYPE_SPECIALIST,
    Provider::TYPE_SPA,
    Provider::TYPE_BEAUTY_SCHOOL,
    Provider::TYPE_MOBILE_TEAM,
]),
```

---

#### 3. config/app.php

**Path**: `backend/config/app.php`

**Changes**:
- Register ProviderTypeServiceProvider

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    App\Providers\ProviderTypeServiceProvider::class,
])->toArray(),
```

---

## 6. Implementation Order

### Step 1: Create Foundation (No Breaking Changes)

1. Create `ProviderTypeDefinition.php`
2. Create `UnsupportedProviderTypeError.php`
3. Create `ProviderTypeRegistry.php` with all type definitions
4. Create `ProviderTypeServiceProvider.php`
5. Register service provider in `config/app.php`
6. Create `ProviderTypeRegistryTest.php`
7. Run tests to verify registry works

**Risk**: LOW - additive only, no existing code touched

---

### Step 2: Update Model (No Breaking Changes)

1. Update `Provider.php` to add missing constants
2. Add `getTypeDefinition()` helper method
3. Update `ProviderFactory.php` to use all constants

**Risk**: LOW - additive only, backward compatible

---

### Step 3: Refactor Existing Code (Low Risk First)

1. Refactor `AvailabilityEngine.php` to use registry
2. Refactor `CustomerSpecialistService.php` to use registry
3. Refactor `SpecialistPortalController.php` to use registry
4. Refactor `SpecialistCraftService.php` to use registry
5. Refactor `IndependentWorkspaceProvisioner.php` to use registry

**Risk**: LOW - replacing hard-coded checks with registry calls

---

### Step 4: Decide on TYPE_SUPPLIER

1. Analyze if `supplier` should be a valid provider type
2. If yes: Add to database enum, add constant, add registry definition
3. If no: Remove from model, update Product.php to handle differently

**Risk**: LOW - decision point, not implementation

---

### Step 5: Update Tests

1. Update test files to use registry constants
2. Add tests for registry functionality

**Risk**: LOW - test updates only

---

## 7. Key Decisions Required

### Decision 1: Should `supplier` be a valid provider type?

**Current State**:
- `TYPE_SUPPLIER` exists in Provider model
- NOT in database enum
- Used in Product.php to check supplier ownership

**Options**:
1. **Yes**: Add to database enum, add constant, add registry definition
2. **No**: Remove from model, update Product.php to use different logic

**Recommendation**: Analyze supplier use cases before deciding. If suppliers are just for inventory/commerce, they may not need full provider type status.

---

### Decision 2: Should ProviderTypeRegistry be database-backed or code-based?

**Current Proposal**: Code-based (definitions in PHP)

**Alternative**: Database-backed (provider_types table with definitions)

**Trade-offs**:
- **Code-based**: Faster, version-controlled, requires deployment for changes
- **Database-backed**: Runtime configurable, requires UI for management, more complex

**Recommendation**: Start with code-based. Can migrate to database-backed if runtime configuration becomes necessary.

---

### Decision 3: Should BusinessCategory terminology be merged into ProviderType?

**Current State**:
- BusinessCategory has terminology fields (specialist_label, customer_term, etc.)
- ProviderType would have similar fields
- They serve different purposes (industry vertical vs operational model)

**Options**:
1. **Keep separate**: BusinessCategory for onboarding, ProviderType for operations
2. **Merge**: ProviderType becomes the single source of terminology

**Recommendation**: Keep separate for now. They serve different purposes. Can revisit if terminology becomes redundant.

---

## 8. Success Criteria

After Phase 1 (Foundation):

- [ ] ProviderTypeRegistry exists and is initialized
- [ ] All 5 provider types have definitions
- [ ] Registry throws explicit error for unsupported types
- [ ] Tests pass for registry functionality
- [ ] No existing code is broken

After Phase 2 (Model Update):

- [ ] Provider model has all constants
- [ ] Provider has `getTypeDefinition()` helper
- [ ] Factory generates all provider types

After Phase 3 (Refactoring):

- [ ] AvailabilityEngine uses registry
- [ ] CustomerSpecialistService uses registry
- [ ] SpecialistPortalController uses registry
- [ ] SpecialistCraftService uses registry
- [ ] IndependentWorkspaceProvisioner uses registry
- [ ] No hard-coded string comparisons remain
- [ ] All existing tests pass

---

## 9. Risks and Mitigations

### Risk 1: Breaking existing functionality

**Mitigation**: 
- Additive approach first (create registry without using it)
- Refactor incrementally (one service at a time)
- Run tests after each change
- Keep hard-coded checks as fallback during transition

### Risk 2: Missing provider type in registry

**Mitigation**:
- Registry throws explicit error (no silent fallback)
- Test all database enum values are in registry
- Add validation on provider creation/update

### Risk 3: Inconsistent terminology between BusinessCategory and ProviderType

**Mitigation**:
- Document the difference (industry vertical vs operational model)
- Keep them separate for now
- Revisit if redundancy becomes problematic

---

## 10. Next Steps

**DO NOT implement yet.** This report is for review and approval.

**Awaiting user decision on**:
1. Proposed file structure
2. ProviderTypeDefinition fields
3. Registry implementation approach
4. Refactoring order
5. Decision on TYPE_SUPPLIER
6. Decision on code-based vs database-backed registry

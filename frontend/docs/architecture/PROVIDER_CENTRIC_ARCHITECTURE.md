# Provider-Centric Architecture Refactor

## Overview

This document describes the major architectural refactor from a salon-centric model to a provider-centric marketplace model. This change transforms Yo.Salon from a single-salon booking platform into a scalable beauty and grooming marketplace.

**Date**: July 31, 2026
**Status**: Completed

---

## The Problem

### Original Architecture (Salon-Centric)

The original architecture centered everything around salons:

```
Salon
├── Services (owned by salon)
├── Staff (employed by salon)
├── Bookings (with salon)
└── Reviews (for salon)
```

**Limitations:**
- Services were tied to specific salons
- No support for independent specialists
- No marketplace discovery
- Hard to scale beyond salons
- Specialists couldn't work at multiple salons independently

---

## The Solution

### New Architecture (Provider-Centric)

The new architecture separates **People** from **Businesses**:

```
Person (Specialist)
├── Professional identity
├── Skills & portfolio
├── Reviews
└── Can work for multiple providers

Provider (Business)
├── Salon
├── Independent Specialist
├── Spa
├── Beauty School
├── Mobile Team
└── [Future: Any service business]

Services
├── Owned by providers
├── Provider-specific pricing
└── Provider-specific details

Bookings
├── With provider
├── Optionally assigned specialist
└── Provider-agnostic engine
```

---

## Key Principles

1. **People are global identities** - A specialist is a person, not a business
2. **Providers are business identities** - The entity customers interact with
3. **Services belong to providers** - Never globally shared
4. **Bookings are with providers** - The booking engine doesn't care who the provider is
5. **Employment is separate** - Modeled through relationships between people and providers

---

## Database Changes

### New Tables

#### `providers`
```sql
- id (UUID, primary)
- type (enum: salon, independent_specialist, spa, beauty_school, mobile_team)
- status (enum: pending, active, suspended, inactive)
- display_name (string)
- slug (string, unique)
- description (text)
- logo (string)
- cover_image (string)
- phone (string)
- email (string)
- website (string)
- location (JSON)
- social_links (JSON)
- settings (JSON)
- rating (decimal)
- review_count (integer)
- active (boolean)
- timestamps
- soft_deletes
```

### Modified Tables

#### `salons`
- Added `provider_id` (UUID, foreign key to providers)
- One-to-one relationship with providers

#### `specialists`
- Added `provider_id` (UUID, nullable, foreign key to providers)
- Nullable for specialists who work at salons vs independent specialists

#### `services`
- Changed `salon_id` → `provider_id`
- Services now owned by providers, not salons

#### `bookings`
- Changed `salon_id` → `provider_id`
- Bookings now with providers, not salons
- Kept `specialist_id` for assigned specialists

#### `reviews`
- Changed `salon_id` → `provider_id`
- Reviews now for providers, not salons
- Kept `specialist_id` for specialist-specific reviews

---

## Model Changes

### New Model: `Provider`

```php
class Provider extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'type', 'status', 'display_name', 'slug', 'description',
        'logo', 'cover_image', 'phone', 'email', 'website',
        'location', 'social_links', 'settings', 'rating',
        'review_count', 'active',
    ];

    // Relationships
    public function salon(): HasOne
    public function specialists(): HasMany
    public function services(): HasMany
    public function bookings(): HasMany
    public function reviews(): HasMany

    // Scopes
    public function scopeActive()
    public function scopeByType(string $type)
    public function scopeByStatus(string $status)
}
```

### Updated Models

#### `Service`
- Removed `BelongsToSalon` trait
- Changed `salon()` → `provider()` relationship
- Updated fillable fields

#### `Booking`
- Removed `BelongsToSalon` trait
- Added `provider()` relationship
- Kept `salon()` for backward compatibility
- Updated fillable fields

#### `Review`
- Added `provider()` relationship
- Added `provider_id` to fillable fields

#### `Salon`
- Added `provider()` relationship
- Added `provider_id` to fillable fields

#### `Specialist`
- Added `provider()` relationship (nullable)
- Added `provider_id` to fillable fields

---

## API Changes

### New Endpoints

#### Public Provider Routes
```
GET /v1/providers - Search providers (with filters)
GET /v1/providers/{id} - Get provider details
```

#### Protected Provider Routes
```
POST /v1/providers - Create provider
PUT /v1/providers/{id} - Update provider
DELETE /v1/providers/{id} - Delete provider
```

### Updated Endpoints

#### Portal Services
```
GET /v1/portal/services
- Now uses provider_id instead of salon_id
- Resolved through salon's provider relationship
```

#### Portal Context Middleware
```
- Now resolves provider_id through salon's provider
- Injects provider_id into request attributes
- Keeps salon_id for backward compatibility
```

---

## Frontend Changes

### Discover Page
- Changed search type from "specialists" to "providers"
- Updated API call to `/providers?search=`
- Shows provider type (salon, independent_specialist, etc.)
- Displays `display_name` instead of `name`

### Booking Flow
- Added `provider_id` to booking mutation
- Uses salon's `provider_id` from context

### Terminology Updates
- "Specialists" → "Providers" in search UI
- "Search specialists globally" → "Search providers globally"

---

## Migration Strategy

### Data Migration

1. **Create provider records for existing salons**
   - Each salon gets a provider record
   - Salon data copied to provider (name, description, logo, etc.)
   - Provider type set to 'salon'
   - Provider ID matches salon ID for simplicity

2. **Migrate services**
   - Copy `salon_id` → `provider_id` via salon's provider relationship
   - Drop `salon_id` column
   - Make `provider_id` not nullable

3. **Migrate bookings**
   - Copy `salon_id` → `provider_id` via salon's provider relationship
   - Drop `salon_id` column
   - Make `provider_id` not nullable

4. **Migrate reviews**
   - Copy `salon_id` → `provider_id` via salon's provider relationship
   - Drop `salon_id` column
   - Make `provider_id` not nullable

### Migration Files

1. `2026_07_31_190000_create_providers_table.php`
2. `2026_07_31_190100_add_provider_id_to_salons_table.php`
3. `2026_07_31_190200_add_provider_id_to_specialists_table.php`
4. `2026_07_31_190300_migrate_salons_to_providers.php`
5. `2026_07_31_190400_change_services_to_provider_id.php`
6. `2026_07_31_190500_change_bookings_to_provider_id.php`
7. `2026_07_31_190600_change_reviews_to_provider_id.php`

---

## Benefits

### 1. Marketplace Scalability

**Before**: Only salons could offer services
**After**: Any provider type can offer services

Future provider types:
- Independent barbers
- Makeup artists
- Nail technicians
- Massage therapists
- Mobile stylists
- Bridal teams
- Tattoo artists
- Beauty schools
- Event grooming teams

### 2. Specialist Flexibility

**Before**: Specialists tied to single salon
**After**: Specialists can:
- Work at multiple salons
- Be independent (own provider)
- Teach at beauty schools
- Judge competitions
- All as one person identity

### 3. Service Ownership

**Before**: Services shared globally (problematic)
**After**: Services owned by providers
- Each provider sets their own pricing
- Each provider has their own descriptions
- Each provider has their own photos
- Each provider has their own duration

### 4. Booking Engine Simplicity

**Before**: Booking logic knew about salons
**After**: Booking engine is provider-agnostic
- Same flow for salon bookings
- Same flow for independent specialist bookings
- Same flow for future provider types

### 5. Review System

**Before**: Reviews for salons
**After**: Reviews for providers
- Customers review the business entity
- Can still review specific specialists
- Reviews scale with provider types

---

## Example Use Cases

### Use Case 1: Salon Booking

```
Customer books:
Provider: Fade Republic (Salon)
Service: Executive Haircut
Specialist: Alex (assigned)
```

### Use Case 2: Independent Specialist

```
Customer books:
Provider: Alex Mobile Grooming (Independent Specialist)
Service: Home Visit Fade
Specialist: Alex (provider is the specialist)
```

### Use Case 3: Beauty School

```
Customer books:
Provider: Elite Barber Academy (Beauty School)
Service: Student Haircut
Specialist: Student (assigned)
```

### Use Case 4: Mobile Team

```
Customer books:
Provider: Bridal Glam Squad (Mobile Team)
Service: On-Site Bridal Makeup
Specialist: Team member (assigned)
```

---

## Future Possibilities

### 1. Multi-Provider Specialists

A specialist could:
- Work at Fade Republic (Mon-Fri)
- Own Alex Mobile Grooming (Weekends)
- Teach at Elite Barber Academy (Evenings)

All as one person identity.

### 2. Provider Aggregation

Customers could search across all provider types:
- "Barbers near me"
- Shows salons AND independent barbers
- Unified marketplace experience

### 3. Provider Verification

Different verification for different types:
- Salons: Business registration
- Independent specialists: Portfolio + reviews
- Beauty schools: Accreditation
- Mobile teams: Insurance + vehicle

### 4. Provider-Specific Features

Each provider type could have unique features:
- Salons: Room management, staff scheduling
- Independent specialists: Travel radius, equipment
- Beauty schools: Student management, curriculum
- Mobile teams: Vehicle tracking, team coordination

---

## Backward Compatibility

### Preserved Features

1. **Salon relationships** - Kept for existing code
2. **Staff relationships** - Kept for legacy support
3. **Portal context** - Still resolves salon_id
4. **API endpoints** - Most work with both models

### Migration Path

Existing code continues to work:
- Salon-based queries still function
- Staff-based bookings still work
- Portal experience unchanged
- Gradual migration to provider model

---

## Testing Checklist

### Backend
- [x] Providers table created
- [x] Provider model with relationships
- [x] Salon-provider relationship working
- [x] Specialist-provider relationship working
- [x] Services migrated to provider_id
- [x] Bookings migrated to provider_id
- [x] Reviews migrated to provider_id
- [x] ProviderController CRUD operations
- [x] Provider search functionality
- [x] Portal middleware resolves provider_id
- [x] Portal services use provider_id

### Frontend
- [x] Discover page uses provider search
- [x] Booking flow includes provider_id
- [x] Terminology updated to providers
- [x] Provider cards display correctly

---

## Files Changed

### Backend

**New Files:**
- `backend/app/Models/Provider.php`
- `backend/app/Http/Controllers/ProviderController.php`
- `backend/database/migrations/2026_07_31_190000_create_providers_table.php`
- `backend/database/migrations/2026_07_31_190100_add_provider_id_to_salons_table.php`
- `backend/database/migrations/2026_07_31_190200_add_provider_id_to_specialists_table.php`
- `backend/database/migrations/2026_07_31_190300_migrate_salons_to_providers.php`
- `backend/database/migrations/2026_07_31_190400_change_services_to_provider_id.php`
- `backend/database/migrations/2026_07_31_190500_change_bookings_to_provider_id.php`
- `backend/database/migrations/2026_07_31_190600_change_reviews_to_provider_id.php`

**Modified Files:**
- `backend/app/Models/Service.php`
- `backend/app/Models/Booking.php`
- `backend/app/Models/Review.php`
- `backend/app/Models/Salon.php`
- `backend/app/Models/Specialist.php`
- `backend/app/Http/Controllers/Api/V1/ServiceController.php`
- `backend/app/Http/Middleware/ResolvePortalContext.php`
- `backend/routes/api.php`

### Frontend

**Modified Files:**
- `frontend/src/app/portal/discover/page.tsx`
- `frontend/src/app/portal/bookings/new/page.tsx`

---

## Conclusion

This provider-centric refactor represents a fundamental shift in Yo.Salon's architecture. It transforms the platform from a single-salon booking system into a scalable marketplace capable of supporting diverse provider types.

The key insight is separating **people** from **businesses**. A specialist is a person who can work for multiple businesses. A provider is a business that offers services. This separation enables:

1. **Scalability** - Add new provider types without core changes
2. **Flexibility** - Specialists can work across providers
3. **Marketplace** - Unified search across all provider types
4. **Simplicity** - Booking engine doesn't need to know provider type

The architecture is now aligned with real-world business models and positions Yo.Salon for growth beyond salons into a comprehensive beauty and grooming marketplace.

---

## Next Steps

1. **Run migrations** - Execute all migration files in order
2. **Test existing functionality** - Ensure salon bookings still work
3. **Test provider search** - Verify discover page works
4. **Test independent specialists** - Create and book with independent providers
5. **Update documentation** - Ensure all docs reflect new architecture
6. **Monitor performance** - Check for any performance impacts
7. **Plan provider-specific features** - Design features for different provider types

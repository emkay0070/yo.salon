# Grooming Intelligence System Documentation

## Overview

The Grooming Intelligence System is a foundational platform architecture that enables personalized customer experiences through structured data collection, validation, and intelligent recommendations. This system serves as the single source of truth for all standardized option lists across the Yo.Salon platform.

## Architecture

### ReferenceData System

The ReferenceData system provides a flexible, scalable foundation for managing platform-wide definitions and options.

#### Database Schema

**Table: `reference_data`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `category` | string | Category identifier (e.g., hair_type, skin_type) |
| `key` | string | Unique key within category |
| `label` | string | Human-readable display label |
| `value` | string | Stored value (defaults to label) |
| `description` | text | Optional description |
| `icon` | string | Optional icon identifier |
| `color` | string | Optional color code |
| `sort_order` | integer | Display order |
| `is_active` | boolean | Whether the option is active |
| `is_system` | boolean | Whether this is a platform default |
| `parent_id` | uuid | Optional parent for hierarchical data |
| `metadata` | jsonb | Additional flexible data |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |

**Indexes:**
- `category`
- `key`
- `[category, key]`
- `[category, is_active]`

#### Model: ReferenceData

```php
class ReferenceData extends Model
{
    protected $fillable = [
        'category', 'key', 'label', 'value', 'description',
        'icon', 'color', 'sort_order', 'is_active', 'is_system',
        'parent_id', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    // Scopes
    public function scopeByCategory($query, string $category)
    public function scopeActive($query)
    public function scopeSystem($query)

    // Relationships
    public function parent()
    public function children()
}
```

#### API Endpoints

**Public Routes:**

```
GET /v1/reference-data
GET /v1/reference-data/{category}
POST /v1/reference-data/categories
```

**Controller: ReferenceDataController**

- `index()` - Get all reference data with optional category filter
- `getByCategory(string $category)` - Get data for specific category
- `getCategories(Request $request)` - Get multiple categories at once

**Example Request:**

```bash
POST /v1/reference-data/categories
{
  "categories": ["hair_type", "skin_type", "beard_style"]
}
```

**Example Response:**

```json
{
  "data": {
    "hair_type": [
      {
        "id": "uuid",
        "category": "hair_type",
        "key": "straight",
        "label": "Straight",
        "value": "Straight",
        "sort_order": 1,
        "is_active": true,
        "is_system": true
      }
    ],
    "skin_type": [...],
    "beard_style": [...]
  }
}
```

#### Seeded Categories

**Grooming Profile Categories:**

1. **hair_type** - Hair texture classification
   - Straight, Wavy, Curly, Coily, Kinky, Other

2. **hair_style** - Preferred haircut styles
   - Fade, Crew Cut, Pompadour, Undercut, Quiff, Side Part, Buzz Cut, Long, Other

3. **hair_concern** - Hair and scalp concerns
   - Dry, Oily, Dandruff, Thinning, Frizzy, Color-treated, Damaged, Split Ends, Heat Damage, Chemical Damage, Itchy Scalp, Psoriasis, Alopecia, None

4. **beard_style** - Beard styling preferences
   - Clean Shaven, Stubble, Goatee, Full Beard, Van Dyke, Circle Beard, Other

5. **beard_product** - Beard product preferences
   - Beard Oil, Beard Balm, Beard Wax, Beard Butter, None, Other

6. **skin_type** - Skin classification
   - Oily, Dry, Combination, Normal, Sensitive, Acne-prone, Other

7. **allergy** - Common product allergies
   - Nuts, Fragrance, Latex, Sulfates, Parabens, Alcohol, Dyes, Essential Oils, None, Other

## Customer Grooming Profiles

### Database Schema

**Table: `customer_grooming_profiles`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `customer_id` | uuid | Foreign key to customers |
| `hair_type` | string | Hair texture |
| `preferred_hair_style` | string | Preferred haircut |
| `hair_concerns` | jsonb | Array of hair concerns |
| `beard_style` | string | Beard style preference |
| `beard_products` | string | Beard product preference |
| `skin_type` | string | Skin classification |
| `allergies` | jsonb | Array of allergies |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |

**Foreign Key:** `customer_id` → `customers(id)` (CASCADE DELETE)

### Model: CustomerGroomingProfile

```php
class CustomerGroomingProfile extends Model
{
    protected $fillable = [
        'customer_id',
        'hair_type',
        'preferred_hair_style',
        'hair_concerns',
        'beard_style',
        'beard_products',
        'skin_type',
        'allergies',
    ];

    protected $casts = [
        'hair_concerns' => 'array',
        'allergies' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
```

### API Endpoints

**Portal Routes (Authenticated):**

```
GET /v1/portal/grooming-profile
PUT /v1/portal/grooming-profile
```

**Controller: PortalAccountController**

- `getGroomingProfile()` - Retrieve customer's grooming profile
- `updateGroomingProfile(Request $request)` - Update customer's grooming profile with validation

**Validation:**

All grooming profile fields are validated against ReferenceData to ensure only valid options are accepted:

```php
// Example validation for hair_type
$validHairTypes = ReferenceData::byCategory('hair_type')->active()->pluck('value')->toArray();
if (!in_array($validated['hair_type'], $validHairTypes)) {
    return response()->json(['message' => 'Invalid hair type'], 422);
}
```

## Customer Profile Enhancements

### Database Schema Updates

**Table: `customers`** (Added columns)

| Column | Type | Description |
|--------|------|-------------|
| `birthday` | date | Customer's birthday (nullable) |
| `address` | string | Customer's address (nullable, max 500 chars) |

### API Endpoints

**Portal Routes (Authenticated):**

```
PUT /v1/portal/profile
```

**Controller: PortalAccountController**

- `updateProfile(Request $request)` - Update customer basic profile
  - Validates: name, phone, email, birthday, address
  - Updates both Customer and PortalAccount models

## Frontend Implementation

### Profile Page: `frontend/src/app/portal/profile/page.tsx`

#### Components

1. **IdentityContent**
   - Displays and edits customer personal information
   - Fields: Name, Email, Phone, Birthday, Address
   - Edit mode with form validation
   - Auto-reload on save

2. **GroomingProfilesContent**
   - Displays and edits grooming profile data
   - Three independent sections: Hair, Beard, Skin
   - Each section has its own edit button
   - Fetches options from ReferenceData API
   - Uses checkboxes for multi-select fields (concerns, allergies)

3. **PreferencesContent**
   - Displays loyalty status and notification preferences
   - Wallet section hidden (payments not active)

4. **SecurityContent**
   - Displays security settings and verification status

#### Reference Data Integration

```typescript
const { data: referenceData } = useQuery({
  queryKey: ['reference-data'],
  queryFn: () => portalApiClient.post('/reference-data/categories', {
    categories: ['hair_type', 'hair_style', 'hair_concern', 'beard_style', 'beard_product', 'skin_type', 'allergy']
  }),
});

const hairTypes = referenceData?.data?.hair_type || [];
```

#### Form Rendering

**Dropdowns (single select):**
```typescript
<select value={groomingData.hair_type} onChange={...}>
  <option value="">Select hair type</option>
  {hairTypes.map((type: any) => (
    <option key={type.key} value={type.value}>{type.label}</option>
  ))}
</select>
```

**Checkboxes (multi-select):**
```typescript
{hairConcerns.map((concern: any) => (
  <label key={concern.key}>
    <input
      type="checkbox"
      checked={groomingData.hair_concerns?.includes(concern.value)}
      onChange={(e) => {
        if (e.target.checked) {
          setGroomingData({ ...groomingData, hair_concerns: [...groomingData.hair_concerns, concern.value] });
        } else {
          setGroomingData({ ...groomingData, hair_concerns: groomingData.hair_concerns.filter((c) => c !== concern.value) });
        }
      }}
    />
    {concern.label}
  </label>
))}
```

## Multi-Layer Grooming Profile Roadmap

The current implementation is **Layer 1 (Customer Input)**. The full Grooming Intelligence System envisions six layers:

### Layer 1 — Customer Input ✅ (Implemented)
- Hair Type
- Skin Type
- Beard Preference
- Allergies
- Preferred Fragrance
- Preferred Appointment Time

### Layer 2 — Specialist Assessment (Future)
Professional-only fields:
- Hair Density (Low/Medium/High)
- Scalp Condition (Healthy/Dry/Oily/Inflamed/Sensitive)
- Hairline (Normal/Receding/Widow's Peak)
- Beard Growth Pattern (Patchy/Full/Sparse)
- Skin Observations (Acne/Pigmentation/Sensitive/Scarring)

### Layer 3 — Visit History (Future)
Automatically generated:
- Favorite haircut (from booking history)
- Favorite barber (from booking history)
- Preferred appointment day (from booking patterns)
- Preferred appointment time (from booking patterns)

### Layer 4 — Behavioral Intelligence (Future)
AI-derived insights:
- Haircut frequency (e.g., every 45 days)
- Average spend
- Product purchase patterns
- Tipping behavior
- Booking patterns (last minute vs planned)
- Cancellation frequency
- Loyalty score

### Layer 5 — Specialist Notes (Future)
Free-form observations:
- Customer preferences
- Style goals
- Special occasions
- Product reactions
- Personal notes

### Layer 6 — AI Knowledge (Future)
Generated recommendations:
- Product recommendations based on profile
- Treatment schedules
- Specialist matching
- Service suggestions
- Rebooking timing

## Future Salon Customization

The ReferenceData system supports salon-specific overrides:

### Proposed Schema Extension

**Table: `salon_reference_data`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `salon_id` | uuid | Foreign key to salons |
| `definition_id` | uuid | Optional reference to platform definition |
| `category` | string | Category identifier |
| `key` | string | Unique key |
| `label` | string | Custom label |
| `sort_order` | integer | Display order |
| `is_active` | boolean | Whether active for this salon |
| `metadata` | jsonb | Additional data |

**Priority Logic:**
```
Salon Definition → Platform Definition
```

This allows:
- Platform ships with 20 beard styles
- Salon disables 5, adds 2 custom styles
- No platform update required

## Benefits

### For Customers
- Personalized experience
- Better product recommendations
- Appropriate specialist matching
- Consistent service across visits

### For Salons
- Better customer understanding
- Targeted marketing
- Improved service quality
- Data-driven decisions

### For the Platform
- Structured data for AI
- Analytics and insights
- Scalable architecture
- Easy customization

## Usage Examples

### Product Recommendations

```php
// Find products suitable for customer's skin type
$products = Product::where('suitable_for', 'LIKE', '%' . $customer->groomingProfile->skin_type . '%')
    ->whereJsonDoesntContain('allergens', $customer->groomingProfile->allergies)
    ->get();
```

### Specialist Matching

```php
// Find specialists who specialize in customer's hair type
$specialists = Specialist::whereJsonContains('specialties', $customer->groomingProfile->hair_type)
    ->where('salon_id', $customer->salon_id)
    ->get();
```

### Analytics

```php
// Most common hair concerns
$commonConcerns = CustomerGroomingProfile::selectRaw('jsonb_array_elements(hair_concerns) as concern')
    ->selectRaw('COUNT(*) as count')
    ->groupBy('concern')
    ->orderByDesc('count')
    ->limit(10)
    ->get();
```

## Migration History

### 2026_08_02_065118 - Add birthday and address to customers
- Added `birthday` (date, nullable)
- Added `address` (string, nullable)

### 2026_08_02_065455 - Create customer_grooming_profiles
- Created grooming profile table
- Added foreign key to customers

### 2026_08_02_072336 - Create reference_data
- Created platform definitions table
- Supports categories, keys, values, metadata
- Enables salon customization

## API Reference

### Reference Data Endpoints

#### GET /v1/reference-data
Get all reference data

**Query Parameters:**
- `category` (optional) - Filter by category
- `active` (optional, default: true) - Only active options

#### GET /v1/reference-data/{category}
Get reference data for specific category

**Response:**
```json
{
  "category": "hair_type",
  "data": [...]
}
```

#### POST /v1/reference-data/categories
Get multiple categories at once

**Request Body:**
```json
{
  "categories": ["hair_type", "skin_type"]
}
```

**Response:**
```json
{
  "data": {
    "hair_type": [...],
    "skin_type": [...]
  }
}
```

### Grooming Profile Endpoints

#### GET /v1/portal/grooming-profile
Get customer's grooming profile

**Authentication:** Required (portal)

**Response:**
```json
{
  "grooming_profile": {
    "hair_type": "Curly",
    "preferred_hair_style": "Fade",
    "hair_concerns": ["Dry", "Frizzy"],
    ...
  }
}
```

#### PUT /v1/portal/grooming-profile
Update customer's grooming profile

**Authentication:** Required (portal)

**Request Body:**
```json
{
  "hair_type": "Curly",
  "preferred_hair_style": "Fade",
  "hair_concerns": ["Dry", "Frizzy"],
  "beard_style": "Full Beard",
  "beard_products": "Beard Oil",
  "skin_type": "Dry",
  "allergies": ["Fragrance"]
}
```

**Validation:**
- All values validated against ReferenceData
- Returns 422 if invalid value provided

**Response:**
```json
{
  "message": "Grooming profile updated successfully",
  "grooming_profile": {...}
}
```

### Customer Profile Endpoints

#### PUT /v1/portal/profile
Update customer basic profile

**Authentication:** Required (portal)

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "birthday": "1990-01-15",
  "address": "123 Main St, City"
}
```

**Response:**
```json
{
  "message": "Profile updated successfully",
  "customer": {...}
}
```

## Technical Notes

### UUID Usage
All primary keys use UUIDs for distributed system compatibility and security.

### JSONB Fields
- `hair_concerns` and `allergies` stored as JSONB arrays
- Enables efficient querying and indexing
- Casts to array in Laravel model

### Validation Strategy
- Frontend: ReferenceData provides valid options
- Backend: Validates against ReferenceData before saving
- Ensures data integrity across the system

### Performance Considerations
- Indexed on `[category, key]` for fast lookups
- Indexed on `[category, is_active]` for filtering
- ReferenceData cached in frontend via React Query

## Security Considerations

- Reference data is public (no authentication required)
- Grooming profile endpoints require portal authentication
- Customer profile updates require portal authentication
- All data validated before database storage
- SQL injection protected via Eloquent ORM

## Testing Recommendations

### Unit Tests
- ReferenceData model scopes
- GroomingProfile validation
- Profile update logic

### Integration Tests
- ReferenceData API endpoints
- Grooming profile CRUD operations
- Profile update with ReferenceData validation

### E2E Tests
- Complete profile editing flow
- Grooming profile editing flow
- Reference data fetching and rendering

## Maintenance

### Adding New Categories
1. Add entries to `ReferenceDataSeeder`
2. Run seeder: `php artisan db:seed --class=ReferenceDataSeeder`
3. Update frontend to fetch new category
4. Update validation if needed

### Modifying Options
1. Update in database (via seeder or admin panel)
2. Changes reflect immediately in API
3. Frontend automatically uses new options

### Deprecating Options
1. Set `is_active = false` in database
2. Option removed from API responses
3. Existing data preserved (no cascade delete)

## Conclusion

The Grooming Intelligence System provides a robust foundation for personalized customer experiences. The ReferenceData architecture ensures scalability, maintainability, and future extensibility while the multi-layer grooming profile vision enables increasingly sophisticated personalization as the platform grows.

This system is not just about dropdowns—it's about building the data infrastructure necessary for AI-powered recommendations, specialist matching, and truly personalized grooming experiences.

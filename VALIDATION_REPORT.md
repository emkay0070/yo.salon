# Validation Report: Ownership Relationships & Settings Impact

## Database Schema Validation

### Salon.logo Ownership

**Schema:**
```php
// salons table (original migration)
$table->string('logo')->nullable();
```

**Model Relationship:**
```php
// Salon.php
public function logoMedia(): BelongsTo
{
    return $this->belongsTo(Media::class, 'logo');
}

public function getLogoUrlAttribute(): ?string
{
    if ($this->logo && \Illuminate\Support\Str::isUuid($this->logo)) {
        return $this->logoMedia?->url;
    }
    if ($this->logo) {
        return asset('storage/' . $->logo);
    }
    return null;
}
```

**Ownership Pattern:**
- Salon.logo can store: UUID (Media reference) OR legacy path string
- Supports backward compatibility with old path-based storage
- New uploads should use Media UUIDs

### BrandExperience.logo Ownership

**Schema:**
```php
// brand_experiences table
$table->string('logo')->nullable();
```

**Model Relationship:**
```php
// BrandExperience.php
public function logoMedia(): BelongsTo
{
    return $this->belongsTo(Media::class, 'logo');
}

public function getLogoUrlAttribute(): ?string
{
    if ($this->logo && \Illuminate\Support\Str::isUuid($this->logo)) {
        return $this->logoMedia?->url;
    }
    if ($this->logo) {
        return asset('storage/' . $this->logo);
    }
    return null;
}
```

**Ownership Pattern:**
- BrandExperience.logo can store: UUID (Media reference) OR legacy path string
- Same pattern as Salon.logo
- New uploads should use Media UUIDs

### Media Polymorphic Relationships

**Schema:**
```php
// media table
$table->uuid('provider_id')->nullable();
$table->uuid('salon_id')->nullable();
$table->bigInteger('uploaded_by')->nullable(); // legacy

// Polymorphic uploader
$table->string('uploader_type')->nullable();
$table->string('uploader_id')->nullable();

// Polymorphic attachable (authoritative ownership)
$table->string('attachable_type')->nullable();
$table->string('attachable_id')->nullable();

// Visibility
$table->enum('visibility', ['public', 'authenticated', 'private'])->default('public');
```

**Ownership Pattern:**
- **uploader_type/uploader_id**: Who uploaded the file (User, SpecialistAccount, PortalAccount)
- **attachable_type/attachable_id**: What entity owns the file (Salon, BrandExperience, Service, Specialist, etc.)
- **attachable is the authoritative ownership anchor** for authorization
- **visibility**: Who can access (public = branding/profile photos, private = IDs/docs)

**Canonical Media Reference Pattern:**
```php
// For BrandExperience.logo:
Media record with:
- attachable_type = 'App\Models\BrandExperience'
- attachable_id = brand_experience UUID
- visibility = 'public'
- salon_id = salon UUID (for scoping)

// BrandExperience.logo = Media UUID
```

---

## Experience Settings Consumption

### Where Experience Settings Are Applied

**CSS Variables Set by BrandContext:**
```javascript
--brand-border-radius
--brand-shadow-sm
--brand-shadow-md
--brand-shadow-lg
--brand-animation-speed
--brand-spring-stiffness
--brand-spring-damping
--brand-spring-easing
```

### Consumption Analysis

**Dashboard Components (Specialist Portal):**
- **NOT using experience settings**
- Dashboard uses custom tokens (bg-surface, text-text-primary)
- Dashboard does NOT use --brand-border-radius or --brand-shadow-*

**Salon-Facing Components (Destination/Discovery):**
- **HEAVILY using experience settings**
- Components using --brand-border-radius:
  - BookCTA.tsx
  - Gallery.tsx
  - LocationCard.tsx
  - Reviews.tsx
  - ShareFavoriteActions.tsx
  - SpecialistDetailsDrawer.tsx
  - HeroCard.tsx
  - RelatedItemsCarousel.tsx
  - SpecialistGrid.tsx
  - ServiceGrid.tsx
  - FAQAccordion.tsx
- Components using --brand-shadow-*:
  - Same components as above
- Components using --brand-primary/--brand-secondary:
  - All destination components for gradients/accents

**Conclusion:**
- Experience settings are **ONLY consumed by salon-facing pages** (destination, discovery, landing pages)
- Dashboard (specialist portal) does NOT use experience settings
- This is **intentional and correct** - salon-facing experience should match salon's brand, dashboard should be consistent platform UI

---

## Settings Page Structure

### Current Settings Tabs (slug-based: /[slug]/settings)

**Tabs:**
1. **My Profile** - User's personal account
   - Name, email, phone
   - Profile photo upload
   - Notification preferences

2. **Salon Profile** - Business information
   - Name, description, phone, email
   - Website, address, city, country
   - **Logo upload** (DUPLICATE with Branding)

3. **Notifications** - Notification settings
   - Email bookings, SMS reminders, push updates, weekly reports

4. **Appearance** - Theme selection
   - ThemeSwitcher component
   - Global UI preference (dark/light)

5. **Operating Hours** - Business hours
   - Schedule configuration

6. **Holidays & Closures** - Schedule exceptions
   - Holiday/closure management

7. **Booking Rules** - Booking policies
   - Deposit settings, cancellation rules

8. **Payment Methods** - Payment configuration
   - Stripe, payment methods

9. **Staff Roster** - Staff management
   - Specialist assignments

10. **Branding** - Visual identity
    - Experience family selection
    - Brand colors (primary, secondary, accent)
    - Fonts (heading, body)
    - **Logo** (DUPLICATE with Salon Profile)
    - Background image
    - Custom domain
    - White-label settings

11. **Membership** - Subscription
    - Plan details, billing

12. **Add-ons & Credits** - Additional services
    - SMS credits, featured days

### Settings Page Issues

**1. Logo Duplication**
- **Salon Profile tab** has logo upload (uploads to Salon.logo)
- **Branding tab** has logo field (saves to BrandExperience.logo)
- **Conflict:** Which is the source of truth?
- **Current behavior:** Both exist independently

**2. Tab Organization Confusion**
- "Appearance" tab contains ThemeSwitcher (global user preference)
- "Branding" tab contains salon-specific visual identity
- **Unclear:** Users may confuse global theme with salon branding

**3. Experience Family Location**
- Experience family selection is in Branding tab
- This is **correct** - it's part of salon's visual identity
- But naming overlap with Theme presets causes confusion

---

## Proven Ownership Relationships

### Logo Ownership

**Current State:**
- **Salon.logo**: Used for salon profile display, legacy storage
- **BrandExperience.logo**: Used for branding, destination pages
- **Both can reference Media UUIDs** (via polymorphic attachable)

**Canonical Owner:**
- **BrandExperience.logo should be the canonical owner** for salon's visual identity logo
- **Salon.logo should be deprecated** or repurposed for internal/legacy use only

**Rationale:**
- BrandExperience is specifically for visual identity
- Experience settings (border-radius, shadows) are applied alongside logo
- Destination pages (salon-facing) use BrandExperience data
- Salon Profile should only contain business information, not visual assets

### Media Asset Flow (Correct Pattern)

**For Branding Logo Upload:**
```
User uploads file in Branding tab
        ↓
MediaService.upload() called with:
  - attachable_type = 'App\Models\BrandExperience'
  - attachable_id = brand_experience UUID
  - visibility = 'public'
  - salon_id = salon UUID
        ↓
Media record created
        ↓
Media ID returned
        ↓
PUT /v1/brand-experience with:
  - logo = Media ID (UUID)
        ↓
BrandExperience.logo = Media ID
        ↓
BrandExperience.logoMedia relationship resolves to Media record
        ↓
Media URL accessed via ->url accessor
```

**Current 422 Error Cause:**
- Frontend sends: `logo: 'https://example.com/logo.png'` (URL string)
- Backend expects: `logo: <file object>` OR `logo: <Media UUID>`
- Validation fails because URL string is not a file

**Fix Required:**
- Frontend should use MediaService.upload() first
- Then send Media ID in PUT request
- OR backend validation should accept Media ID strings

---

## Settings Page Impact by Architecture Changes

### If Logo Moved to Branding Only

**Impact on Settings Page:**
- **Salon Profile tab:** Remove logo upload section
- **Branding tab:** Keep logo upload (already there)
- **Migration needed:** Move existing Salon.logo data to BrandExperience.logo

**Impact on UI:**
- Salon Profile display should use BrandExperience.logo
- Dashboard header should use BrandExperience.logo
- All logo references should point to BrandExperience

### If Theme Simplified to dark/light

**Impact on Settings Page:**
- **Appearance tab:** Simplify ThemeSwitcher to 3 options (dark, light, auto)
- **No impact on Branding tab:** Experience families remain unchanged

**Impact on UI:**
- ThemeSwitcher component simplified
- ThemeContext preset names updated
- No breaking changes to components

### If Experience Settings Clarified

**Impact on Settings Page:**
- **Branding tab:** No changes needed (already correct location)
- **Documentation:** Add explanation that Experience = salon-facing design language
- **No impact on Dashboard:** Dashboard doesn't use experience settings

**Impact on UI:**
- No changes needed
- Current consumption pattern is correct (salon-facing only)

---

## Recommended Settings Page Reorganization

### Proposed Tab Structure

**Personal:**
1. **My Profile** - User's personal account
   - Name, email, phone
   - Profile photo
   - Notification preferences

**Business:**
2. **Salon Profile** - Business information ONLY
   - Name, description
   - Contact details (phone, email, website)
   - Location (address, city, country)
   - **REMOVE logo** (move to Branding)

**Operations:**
3. **Operating Hours** - Schedule
4. **Holidays & Closures** - Schedule exceptions
5. **Booking Rules** - Booking policies
6. **Payment Methods** - Payment configuration
7. **Staff Roster** - Staff management

**Visual Identity:**
8. **Branding** - Salon's visual identity
   - Experience family selection
   - Brand colors
   - Fonts
   - **Logo** (canonical location)
   - Background image
   - Custom domain
   - White-label settings

**Platform:**
9. **Appearance** - Global UI preference
   - Theme selection (dark/light/auto)
   - **RENAME to "Theme"** to clarify it's platform-wide, not salon-specific

**Account:**
10. **Membership** - Subscription
11. **Add-ons & Credits** - Additional services

### Rationale

**Clear Separation:**
- Personal vs Business vs Operations vs Visual Identity vs Platform vs Account
- Each tab has single, clear responsibility

**Remove Confusion:**
- Logo only in Branding (visual identity)
- Theme renamed to "Theme" (clarifies platform-wide scope)
- Salon Profile only contains business data

**Logical Flow:**
- Personal → Business → Operations → Visual Identity → Platform → Account
- Natural progression from personal to business to platform settings

---

## Summary of Proven Relationships

### Logo Ownership
- **Canonical:** BrandExperience.logo
- **Legacy:** Salon.logo (should be deprecated)
- **Media Reference:** Both support Media UUIDs via polymorphic attachable

### Experience Settings
- **Consumed by:** Salon-facing pages only (destination, discovery, landing)
- **NOT consumed by:** Dashboard (specialist portal)
- **This is intentional and correct**

### Media Asset Flow
- **Correct pattern:** Upload via MediaService → get Media ID → store Media ID in entity
- **Current 422 error:** Frontend sends URL strings instead of using MediaService
- **Fix:** Frontend should use MediaService.upload() before PUT request

### Settings Page Impact
- **Logo duplication:** Remove from Salon Profile, keep in Branding
- **Theme confusion:** Rename Appearance to "Theme" to clarify platform-wide scope
- **Experience location:** Already correct in Branding tab
- **Tab reorganization:** Group by responsibility (Personal, Business, Operations, Visual Identity, Platform, Account)

---

## Next Steps (No Implementation Yet)

1. **Validate Media upload pattern** - Confirm MediaService.upload() is the correct approach for branding assets
2. **Trace existing Salon.logo usage** - Identify all places where Salon.logo is referenced
3. **Plan logo migration** - Determine how to migrate existing Salon.logo data to BrandExperience.logo
4. **Confirm Settings reorganization** - Get stakeholder agreement on proposed tab structure
5. **Document Media integration** - Create clear guide for how to upload and reference Media assets

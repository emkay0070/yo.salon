# Brand Experience System - Handoff Document

## Overview
The Brand Experience System enables each salon to customize their dashboard and customer-facing pages with their own visual identity. This is a "generated experience" architecture - not just theming, but a full design language system.

## Completed Work (Phases 1 & 2)

### Phase 1: Foundation ✅
**Database & Backend:**
- Created `brand_experiences` table with 10 core fields:
  - `salon_id`, `experience_family`, `logo`
  - `primary_color`, `secondary_color`, `accent_color`
  - `font_heading`, `font_body`
  - `background_image`, `custom_domain`, `white_label_enabled`
- Created `BrandExperience` model with relationship to `Salon`
- Created experience families config (`backend/config/experience_families.php`) with 8 families:
  - luxury_noir, modern_glass, urban_pulse, minimal_zen
  - organic, classic_barber, neon_future, executive
- Added API endpoint: `GET/PUT /api/v1/brand-experience`
  - Returns salon info + brand settings + experience config + merged colors
  - Auto-creates default brand experience if none exists

**Frontend:**
- Created `BrandProvider` context to load brand settings via React Query
- Created `useSalonBranding` hook for accessing brand tokens
- Refactored `DashboardLayout` to use dynamic salon branding:
  - Salon logo/name in sidebar
  - Dynamic colors from brand provider
  - Salon background image
- Created brand settings page (`/settings/branding`):
  - Experience family selection
  - Color pickers (primary, secondary, accent)
  - Typography selection (heading/body fonts)
  - Logo and background image URLs
  - White-label settings (custom domain + toggle)
  - Live preview sidebar
- Added branding tab to main settings page

### Phase 2: Global Styling ✅
**CSS Variables Approach (No Breaking Changes):**
- Updated `BrandProvider` to set CSS variables from brand settings:
  - `--brand-font-heading`, `--brand-font-body`
  - `--brand-primary`, `--brand-secondary`, `--brand-accent`
  - `--brand-border-radius`
  - `--brand-shadow-sm/md/lg`
  - `--brand-animation-speed`, `--brand-spring-easing`
- Updated `globals.css` to use brand variables:
  - Typography uses brand fonts
  - Cards use brand colors, border radius, shadows
  - Buttons use brand colors, shadows
  - Scrollbars use brand colors
  - Transitions use brand animation settings
- Experience family properties now applied globally:
  - Shadow styles: deep, soft, colored (with intensity scaling)
  - Motion: animation speed and spring physics per family

**Result:**
Each salon's dashboard now has a unique feel based on their chosen experience family and brand colors. All existing components automatically inherit branding without code changes.

---

## Remaining Work (Phases 3 & 4)

### Phase 3: Customer-Facing Branding

**Goal:** Extend branding beyond the dashboard to customer-facing touchpoints.

#### 3.1: Portal Pages
**Files to update:**
- `frontend/src/app/portal/layout.tsx` - Portal layout
- `frontend/src/app/portal/home/page.tsx` - Portal home
- `frontend/src/app/portal/bookings/page.tsx` - Portal bookings
- `frontend/src/app/portal/profile/page.tsx` - Portal profile

**What to do:**
1. Add `BrandProvider` to portal layout (already wrapped in Providers, but may need to ensure it loads)
2. Use `useSalonBranding` hook in portal pages
3. Apply salon logo/name to portal header
4. Apply brand colors to portal navigation
5. Apply brand fonts to portal typography

**Key consideration:** Portal context is different - customers access via customer relationship, not salon context middleware. May need to adjust brand loading to work with portal context.

#### 3.2: Booking Flow
**Files to update:**
- `frontend/src/app/booking/page.tsx` - Booking page
- `frontend/src/app/booking/BookingContent.tsx` - Booking content component
- `frontend/src/app/salons/[slug]/book/page.tsx` - Salon booking page

**What to do:**
1. Load brand settings by salon slug (not salon_id)
2. Apply brand colors to booking form
3. Apply brand fonts to booking typography
4. Use salon logo in booking header
5. Apply experience family styling to booking cards

**API consideration:** May need a public endpoint to get brand settings by salon slug for unauthenticated booking flow.

#### 3.3: Salon Website
**Files to update:**
- `frontend/src/app/salons/[slug]/page.tsx` - Salon website page

**What to do:**
1. Load brand settings by salon slug
2. Apply brand colors to entire website
3. Apply brand fonts to website typography
4. Use salon logo in website header
5. Apply experience family styling to website components
6. Use salon background image for website hero section

**Design consideration:** Salon website may need different styling approach than dashboard - more marketing-focused, less utility-focused.

#### 3.4: Email Templates
**Backend files:**
- `backend/app/Mail/` - Email mailables

**What to do:**
1. Add brand colors to email CSS
2. Add salon logo to email headers
3. Use brand fonts in email typography
4. Apply experience family styling to email buttons/cards

**Consideration:** Email CSS needs to be inline for compatibility. May need to generate inline styles from brand settings.

---

### Phase 4: Advanced Features

#### 4.1: AI-Powered Brand Generation
**Goal:** Use AI to automatically generate brand colors and suggest experience families based on salon name, description, and industry.

**Backend:**
- Create `BrandGeneratorService` in `backend/app/Services/`
- Integrate with OpenAI GPT-4 API
- Prompt engineering for color palette generation
- Prompt engineering for experience family recommendation
- Add API endpoint: `POST /api/v1/brand-experience/generate`

**Frontend:**
- Add "Generate with AI" button to brand settings page
- Show AI suggestions with preview
- Allow salon owner to accept or modify suggestions

**Implementation approach:**
```php
// Example service structure
class BrandGeneratorService {
    public function generateColors(string $salonName, string $description): array {
        $prompt = "Generate a color palette for a salon named '{$salonName}' that is '{$description}'. Return 3 hex colors: primary, secondary, accent.";
        // Call OpenAI API
        // Parse response
        return ['primary' => '#...', 'secondary' => '#...', 'accent' => '#...'];
    }
    
    public function recommendExperienceFamily(string $salonName, string $description): string {
        $prompt = "Recommend an experience family (luxury_noir, modern_glass, urban_pulse, minimal_zen, organic, classic_barber, neon_future, executive) for a salon named '{$salonName}' that is '{$description}'. Return only the family name.";
        // Call OpenAI API
        return 'luxury_noir';
    }
}
```

#### 4.2: Custom CSS Injection
**Goal:** Allow advanced users to inject custom CSS for fine-grained control.

**Database:**
- Add `custom_css` field to `brand_experiences` table
- Create migration to add the field

**Backend:**
- Update `BrandExperienceController` to handle custom CSS
- Sanitize CSS to prevent security issues

**Frontend:**
- Add code editor to brand settings page
- Inject custom CSS into document head
- Provide CSS variable reference for users

**Security consideration:** Must sanitize CSS to prevent XSS. Use a CSS sanitizer library.

#### 4.3: Advanced Motion Properties
**Goal:** Apply more granular motion settings from experience families.

**Experience families config already includes:**
- `motion_preset` (cinematic, elegant, quick, organic)
- `animation_speed`
- `spring_stiffness`
- `spring_damping`

**What to add:**
- Apply motion presets to Framer Motion animations
- Use spring physics in component transitions
- Add staggered animations based on experience family

**Implementation:**
```typescript
// Example in components
const motion = useMotionTemplateValue(
  'default',
  experience?.motion_preset
);

<motion.div
  transition={{
    type: 'spring',
    stiffness: experience?.spring_stiffness,
    damping: experience?.spring_damping,
  }}
>
```

#### 4.4: Icon Style Application
**Goal:** Apply experience family icon styles (luxury, minimal, filled, outlined).

**What to do:**
- Map icon styles to Lucide icon variants
- Apply icon weights (thin, regular, bold)
- Update icon usage across components

**Implementation approach:**
```typescript
// Create icon wrapper component
const BrandIcon = ({ icon: Icon, ...props }) => {
  const { experience } = useSalonBranding();
  const iconWeight = experience?.icon_weight || 'regular';
  
  return <Icon weight={iconWeight} {...props} />;
};
```

#### 4.5: Glass Material Properties
**Goal:** Apply experience family glass opacity and blur settings.

**Experience families config already includes:**
- `glass_opacity`
- `glass_blur`

**What to do:**
- Update CSS variables for glass properties
- Apply to glass components throughout the app
- Adjust backdrop-filter based on experience family

**Implementation:**
```css
.glass-card {
  background: var(--color-card);
  backdrop-filter: blur(var(--brand-glass-blur, 40px));
  -webkit-backdrop-filter: blur(var(--brand-glass-blur, 40px));
  opacity: var(--brand-glass-opacity, 0.08);
}
```

---

## Technical Architecture

### Data Flow
1. **Salon owner** updates brand settings via `/settings/branding`
2. **Frontend** calls `PUT /api/v1/brand-experience`
3. **Backend** updates `brand_experiences` table
4. **BrandProvider** refetches brand data via React Query
5. **CSS variables** are updated on document root
6. **All components** automatically inherit new styling

### CSS Variable Reference
```css
/* Brand Colors */
--brand-primary
--brand-secondary
--brand-accent

/* Brand Fonts */
--brand-font-heading
--brand-font-body

/* Experience Family Properties */
--brand-border-radius
--brand-shadow-sm
--brand-shadow-md
--brand-shadow-lg
--brand-animation-speed
--brand-spring-easing
```

### API Endpoints
- `GET /api/v1/brand-experience` - Get brand settings (requires salon context)
- `PUT /api/v1/brand-experience` - Update brand settings (requires salon context)

### Database Schema
```sql
CREATE TABLE brand_experiences (
    id UUID PRIMARY KEY,
    salon_id UUID REFERENCES salons(id) ON DELETE CASCADE,
    experience_family ENUM('luxury_noir', 'modern_glass', 'urban_pulse', 'minimal_zen', 'organic', 'classic_barber', 'neon_future', 'executive'),
    logo VARCHAR(255),
    primary_color VARCHAR(7) DEFAULT '#FF622B',
    secondary_color VARCHAR(7) DEFAULT '#FF8C5A',
    accent_color VARCHAR(7) DEFAULT '#FFD700',
    font_heading ENUM('sora', 'playfair', 'inter', 'poppins') DEFAULT 'sora',
    font_body ENUM('sora', 'playfair', 'inter', 'poppins') DEFAULT 'inter',
    background_image VARCHAR(255),
    custom_domain VARCHAR(255),
    white_label_enabled BOOLEAN DEFAULT false,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(salon_id)
);
```

### Experience Family Config Location
`backend/config/experience_families.php`

Each family defines:
- Visual properties (glass opacity, blur, shadows, border radius)
- Motion properties (preset, speed, spring physics)
- Style properties (icon style, weight, cursor style)
- Component styles (sidebar, button, card style)

---

## Deployment Instructions

### Backend Deployment
```bash
ssh ubuntu@16.171.141.237
cd /var/www/yo.salon/backend
git pull
php artisan migrate
./deploy.sh
```

### Frontend Deployment
Frontend is deployed via Vercel - automatically deploys on push to main branch.

### Important Notes
- The `brand_experiences` migration must be run on production
- The experience families config file must be present on production
- BrandProvider gracefully handles 404 errors during deployment
- CSS variables have fallbacks to ensure styling works even if brand fails to load

---

## Testing Checklist

### Phase 1 Testing
- [ ] Brand experience API returns correct data
- [ ] Brand settings page saves changes
- [ ] DashboardLayout shows salon logo/name
- [ ] Sidebar uses brand colors
- [ ] Background image applies correctly

### Phase 2 Testing
- [ ] Brand fonts apply to headings and body text
- [ ] Cards use brand colors and border radius
- [ ] Buttons use brand colors
- [ ] Shadows match experience family style
- [ ] Animations use experience family motion

### Phase 3 Testing (Future)
- [ ] Portal pages show salon branding
- [ ] Booking flow uses salon branding
- [ ] Salon website uses salon branding
- [ ] Email templates use salon branding

### Phase 4 Testing (Future)
- [ ] AI generation produces sensible color palettes
- [ ] Custom CSS injects safely
- [ ] Advanced motion properties apply correctly
- [ ] Icon styles match experience family

---

## Key Design Decisions

### Why CSS Variables?
- **No breaking changes** - existing components automatically inherit styling
- **Fast implementation** - no need to refactor individual components
- **Centralized control** - single source of truth for styling
- **Easy to maintain** - changes in one place affect entire app

### Why Experience Families?
- **Consistent designs** - predefined design languages ensure quality
- **Easy to extend** - add new families without database changes
- **User-friendly** - salon owners choose from curated options
- **Maintainable** - separate config file from database

### Why Simplified Schema (Phase 1)?
- **Faster to market** - get core functionality working first
- **Easier to iterate** - can evolve schema based on real usage
- **Less complexity** - avoids over-engineering before validation
- **Future-proof** - can add more fields later without breaking changes

---

## Next Steps for Next Agent

1. **Start with Phase 3.1** - Extend branding to portal pages (most visible to customers)
2. **Test thoroughly** - ensure portal branding works correctly
3. **Move to Phase 3.2** - Apply branding to booking flow
4. **Consider Phase 3.3** - Salon website branding (may need different approach)
5. **Phase 4 is optional** - advanced features can be added based on user demand

**Priority:** Focus on customer-facing branding first (portal, booking, website) as that's where the brand experience is most visible to end users.

---

## Contact & Context
- **Project:** Yo Salon - AI-powered salon management platform
- **Architecture:** Multi-tenant Laravel + Next.js
- **Design Philosophy:** "Generated Experience" - not just theming, but full design language
- **Current Status:** Phases 1 & 2 complete, Phase 3 ready to start

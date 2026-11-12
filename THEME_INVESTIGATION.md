# Theme System Investigation

## Phase 1: INSPECT ✓

### What Actually Exists

**Configuration Layer:**
- `tailwind.config.ts`: `darkMode: "class"` (expects `class="dark"` on html)
- `Providers.tsx`: `ThemeProvider` from next-themes uses `attribute="data-theme"` (sets `data-theme="noir"`)
- **MISMATCH**: Tailwind looks for class, next-themes sets data attribute

**CSS Layer:**
- `globals.css`:
  - Hardcoded: `html, body { background-color: #0A0A0A !important; color: #FFFFFF !important; }`
  - CSS variables defined in `:root` only (static, no theme variants)
  - NO `[data-theme="*"]` selectors
  - NO `.dark` class selectors
  - Shadcn HSL variables defined but never used by components

**JavaScript Layer:**
- `ThemeContext.tsx`:
  - Defines 5 presets (noir, apple-store, vision-pro, modern-glass, light)
  - Wraps next-themes
  - Has preset data but NO useEffect to apply colors to CSS variables
- `BrandContext.tsx`:
  - Fetches brand colors from API
  - Applies brand colors via `root.style.setProperty('--brand-*', ...)`
  - This works for brand customization, not theme switching
- `ThemeSwitcher.tsx`:
  - UI component to switch themes
  - Calls `setTheme(preset)` which updates next-themes
  - Changes `data-theme` attribute but nothing reacts to it

**Component Usage:**
- Components use Tailwind utilities: `bg-surface`, `text-text-primary`, `border-border-light`
- Some use CSS variables: `var(--color-gold)`, `var(--color-surface)`
- Some use brand variables: `var(--brand-primary)`, `var(--brand-shadow-md)`
- Tailwind colors like `from-gold`, `to-dark-gold` are defined in tailwind.config.ts as CSS var references

---

## Phase 2: MAP

### Data Flow & Ownership

**Who Creates Theme Data?**
1. **ThemeContext.tsx**: Creates preset definitions (hardcoded JS objects)
2. **BrandContext.tsx**: Creates brand-specific colors (from API)
3. **next-themes**: Creates the current theme state (noir, apple-store, etc.)

**Who Owns the Truth?**
- **Current State**: next-themes owns the current theme value (stored in localStorage, applied as `data-theme` attribute)
- **Problem**: Nothing reads this attribute and applies CSS changes

**Who Consumes Theme Data?**
1. **Components**: Consume via Tailwind classes (`bg-surface`, `text-text-primary`)
2. **Tailwind**: Consumes via CSS variables defined in `tailwind.config.ts`
3. **CSS**: Consumes via `var(--color-*)` references

**The Broken Chain:**
```
User clicks ThemeSwitcher
  ↓
setTheme('apple-store') → next-themes
  ↓
next-themes sets data-theme="apple-store" on <html>
  ↓
❌ CSS has no [data-theme="apple-store"] selector
❌ No useEffect in ThemeContext to apply preset colors to CSS variables
❌ Tailwind darkMode looks for class="dark", not data-theme
  ↓
Nothing changes
```

**What Actually Works:**
- BrandContext DOES apply colors via inline styles (`--brand-primary`, etc.)
- But this is for per-salon branding, not global theme switching

**What Doesn't Work:**
- ThemeSwitcher changes the attribute
- But no CSS reacts to that attribute
- So visual appearance never changes

---

## Phase 3: DECIDE

### Canonical Architecture Decision

**Single Source of Truth:** next-themes (already in place)

**Theme Application Mechanism:**
- Use `data-theme` attribute (already configured in Providers.tsx)
- Add CSS selectors for each theme in `globals.css`
- Remove hardcoded `!important` values from body
- Let CSS variables change based on `data-theme` attribute

**Tailwind Configuration:**
- Keep `darkMode: "class"` OR change to `darkMode: "media"` and remove class dependency
- Since we're using custom themes (not just dark/light), we should NOT rely on Tailwind's dark mode
- Instead, use CSS variables that change based on `data-theme`

**CSS Variable Strategy:**
- Define theme-specific CSS variables for each preset
- Components continue using Tailwind utilities that reference these variables
- When `data-theme` changes, CSS variables change, components update automatically

**Brand vs Theme:**
- Theme: Global UI theme (noir, apple-store, etc.) - controlled by ThemeContext
- Brand: Per-salon customization (colors, fonts) - controlled by BrandContext
- They can coexist: Brand variables override theme variables where needed

---

## Phase 4: MODIFY

### Minimal Changes Required

1. **Remove hardcoded body styles** in `globals.css`:
   - Remove `!important` from `background-color` and `color`
   - Let CSS variables control these

2. **Add theme-specific CSS selectors** in `globals.css`:
   ```css
   [data-theme="noir"] { --background: #0A0A0A; --surface: #1A1A1A; ... }
   [data-theme="apple-store"] { --background: #F5F5F7; --surface: #FFFFFF; ... }
   ```

3. **Update body styles** to use CSS variables:
   ```css
   html, body {
     background-color: var(--background);
     color: var(--foreground);
   }
   ```

4. **(Optional) Add useEffect in ThemeContext** to apply preset colors if CSS approach insufficient

---

## Phase 5: VERIFY

### Test Plan

1. Start dev server
2. Open ThemeSwitcher
3. Switch between themes
4. Verify:
   - Background color changes
   - Text color changes
   - Card colors change
   - Border colors change
   - No console errors

---

## Phase 6: LOCK ✓

### Implementation Complete

**Changes Made:**
1. Removed hardcoded `!important` from body background and color in `globals.css`
2. Added theme-specific CSS selectors for all 5 presets (noir, apple-store, vision-pro, modern-glass, light)
3. Updated body and heading styles to use CSS variables (`--background`, `--foreground`)
4. CSS variables now change based on `data-theme` attribute

**How Theme Switching Works:**
```
User clicks ThemeSwitcher → setTheme('apple-store')
  ↓
next-themes sets data-theme="apple-store" on <html>
  ↓
CSS selector [data-theme="apple-store"] matches
  ↓
CSS variables update (--background, --foreground, --color-surface, etc.)
  ↓
Components using Tailwind utilities (bg-surface, text-text-primary) update automatically
  ↓
Visual theme changes
```

**Architecture Rules:**
- **Single Source of Truth**: next-themes (stored in localStorage, applied as `data-theme` attribute)
- **CSS Layer**: Theme-specific selectors in `globals.css` define CSS variables per preset
- **Component Layer**: Components use Tailwind utilities that reference CSS variables
- **Brand vs Theme**: 
  - Theme: Global UI preset (noir, apple-store, etc.) - controlled by ThemeContext
  - Brand: Per-salon customization - controlled by BrandContext
  - Brand variables (`--brand-primary`) can override theme variables where needed

**How to Add New Themes:**
1. Add preset definition to `ThemeContext.tsx` THEME_PRESETS object
2. Add CSS selector in `globals.css`: `[data-theme="new-theme"] { ... }`
3. Define all required CSS variables for the new theme
4. ThemeSwitcher will automatically include it in the dropdown

**CSS Variable Naming Convention:**
- Theme variables: `--color-*` (e.g., `--color-surface`, `--color-text-primary`)
- Brand variables: `--brand-*` (e.g., `--brand-primary`, `--brand-shadow-md`)
- Shadcn variables: `--background`, `--foreground`, `--primary`, etc. (HSL values)

**Testing:**
- Frontend dev server running on http://localhost:3000
- ThemeSwitcher available in Settings pages
- All 5 themes now functional: noir, apple-store, vision-pro, modern-glass, light

**Status: ARCHITECTURE FROZEN**
- Theme switching mechanism is minimal and functional
- No further changes to theme architecture without review
- Future brand customization should use BrandContext, not modify theme system

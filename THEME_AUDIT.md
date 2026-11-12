# Theme System Audit - Canonical Domain Pattern

## Current State Analysis

### Chain Trace

**Expected Chain:**
```
Theme selection → ThemeContext/next-themes → data-theme → CSS semantic tokens → Tailwind/components → visual change
```

**Actual Chain (BROKEN):**
```
Theme selection → ThemeContext/next-themes → data-theme → CSS custom tokens (surface, text-primary) → Tailwind custom utilities → visual change
```

### Issues Found

**1. Components use custom tokens, not semantic ones**
- Components use: `bg-surface`, `text-text-primary`, `text-text-secondary`, `border-border-light`
- Semantic tokens exist but rarely used: `bg-background`, `text-foreground`, `bg-card`
- **Violation**: Components should be theme-agnostic, using semantic tokens only
- **Impact**: ~200+ files use custom tokens instead of semantic ones

**2. Two parallel token systems in Tailwind config**
```ts
// Shadcn semantic (HSL)
background: "hsl(var(--background))"
foreground: "hsl(var(--foreground))"
card: "hsl(var(--card))"

// Custom Yo.Salon (direct colors)
surface: 'var(--color-surface)'
text-primary: 'var(--color-text-primary)'
gold: 'var(--color-gold)'
```
- **Violation**: Single source of truth principle - should have one semantic layer
- **Impact**: Dual token system creates confusion and maintenance burden

**3. CSS variables not aligned**
- Theme selectors update: `--color-surface`, `--color-text-primary`, etc.
- Semantic HSL variables (`--background`, `--foreground`) only defined in `:root` with static HSL values
- Theme selectors don't update semantic variables
- **Violation**: Theme changes don't propagate to semantic layer
- **Impact**: Semantic utilities (bg-background, text-foreground) won't respond to theme changes

**4. ThemeContext wraps next-themes but doesn't fight it**
- ThemeContext correctly delegates to next-themes
- No duplicate state application
- **OK**: This part is correct

**5. Hardcoded body styles removed (already fixed in earlier iteration)**
- Previous `!important` flags removed
- Body now uses `var(--background)` and `var(--foreground)`
- **OK**: This part is correct

## Recommended Repair Strategy

### Phase 1: CSS Layer (Foundation)

**Step 1.1: Convert theme selectors to use semantic HSL variables**
- Replace hex colors with HSL values in each `[data-theme="..."]` selector
- Update all semantic variables: `--background`, `--foreground`, `--card`, `--primary`, etc.
- Keep custom variables (`--color-surface`, etc.) for backward compatibility during migration

**Step 1.2: Map custom tokens to semantic layer (temporary bridge)**
- Add CSS rules that map custom tokens to semantic tokens:
```css
[data-theme="noir"] {
  --color-surface: hsl(var(--card));
  --color-text-primary: hsl(var(--foreground));
  --color-text-secondary: hsl(var(--muted-foreground));
}
```
- This allows existing components to work while we migrate them

### Phase 2: Tailwind Config Layer

**Step 2.1: Keep custom tokens but make them reference semantic layer**
- Don't remove custom tokens yet (would break existing components)
- Instead, make them reference semantic variables:
```ts
surface: 'hsl(var(--card))',
'text-primary': 'hsl(var(--foreground))',
'text-secondary': 'hsl(var(--muted-foreground))',
```
- This creates a bridge for gradual migration

### Phase 3: Component Layer (Gradual Migration)

**Step 3.1: Update components in priority order**
1. **High-impact components** (layouts, core UI): Update to semantic tokens
2. **Medium-impact components** (forms, cards): Update to semantic tokens
3. **Low-impact components** (icons, badges): Update to semantic tokens

**Step 3.2: Token mapping guide for migration**
- `bg-surface` → `bg-card`
- `text-text-primary` → `text-foreground`
- `text-text-secondary` → `text-muted-foreground`
- `text-text-muted` → `text-muted`
- `border-border-light` → `border`
- `border-border-medium` → `border`

### Phase 4: Cleanup

**Step 4.1: Remove custom tokens from Tailwind config**
- Once all components migrated, remove custom token definitions
- Keep only semantic tokens

**Step 4.2: Remove custom CSS variables**
- Remove `--color-surface`, `--color-text-primary`, etc. from CSS
- Keep only semantic HSL variables

**Step 4.3: Remove temporary bridge CSS**
- Remove the mapping rules added in Step 1.2

## Canonical Architecture After Repair

```
Theme contract: noir | apple-store | vision-pro | modern-glass | light
        ↓
next-themes state (localStorage + data-theme attribute)
        ↓
[data-theme="..."] on <html>
        ↓
CSS semantic HSL variables (--background, --foreground, --card, --primary)
        ↓
Tailwind semantic utilities (bg-background, text-foreground, bg-card)
        ↓
Components consume semantic utilities (theme-agnostic)
        ↓
Brand variables (--brand-*) can override where needed
        ↓
Visual result
```

## Migration Risk Assessment

**Low Risk:**
- CSS layer changes (additive, backward compatible)
- Tailwind config bridge changes (non-breaking)

**Medium Risk:**
- Component token migration (requires testing each component)
- Potential visual regressions during migration

**High Risk:**
- Removing custom tokens before component migration complete
- Breaking existing brand customization that relies on custom variables

## Recommended Timeline

**Week 1: Foundation**
- Phase 1: CSS layer updates
- Phase 2: Tailwind config bridge
- Test theme switching still works

**Week 2-3: Component Migration**
- Phase 3: Gradual component updates
- Test after each batch of changes
- Keep custom tokens as fallback

**Week 4: Cleanup**
- Phase 4: Remove custom tokens
- Final testing
- Document final architecture

## Success Criteria

1. All 5 themes switch correctly via ThemeSwitcher
2. All components use semantic tokens (bg-background, text-foreground, etc.)
3. Tailwind config has only semantic tokens
4. CSS has only semantic HSL variables
5. Brand customization still works via `--brand-*` variables
6. No visual regressions across all themes

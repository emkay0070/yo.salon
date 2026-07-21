# Experience Engine - Archived

**Archived Date:** July 14, 2026
**Original Location:** `src/core/`
**Status:** Postponed to Phase 5

---

## What It Did

The Experience Engine was a reusable immersive UI system designed to power all EmKay Studios products across multiple industries (Yo Salon, Yo Restaurant, Yo Clinic, Yo Gym).

### Core Components

**SceneManager**
- Managed 3D scenes, camera controls, and rendering
- Scene initialization and object management
- Lighting management and render loop

**AnimationEngine**
- Spring physics-based animations (no linear transitions)
- Staggered animations for lists and grids
- Scroll-triggered animations
- Cursor interactions and performance optimization

**AudioManager**
- Sound loading and volume control
- Spatial audio positioning
- Mute state management
- Cross-browser compatibility

**InputManager**
- Mouse/touch tracking
- Keyboard events and gesture recognition
- Focus management
- Accessibility support

**AssetManager**
- Asset loading and caching strategy
- Lazy loading and preloading
- Error handling

### UI Components

**GlassCard**
- Floating glass panels with elevation system (0-4 levels)
- 8% base opacity, 40px backdrop blur
- Spring physics on hover (stiffness: 300, damping: 20)

**GlassButton**
- Glass-styled buttons with spring animation
- Variants: primary, secondary, ghost
- Sizes: sm, md, lg

**FloatingElement**
- Ambient floating motion with configurable amplitude and speed
- Infinite loop animation with easeInOut

**CursorGlow**
- Cursor-following light effect
- Configurable color, intensity, radius
- Spring physics for smooth cursor tracking

---

## Why It Existed

The Experience Engine was designed to solve the problem of building consistent, premium digital experiences across multiple industries while maintaining a single codebase. It separated the "Experience Layer" (visuals, animations, interactions) from the "Business Layer" (industry-specific logic), allowing the same engine to power Yo Salon, Yo Restaurant, Yo Clinic, and future verticals.

### Key Benefits

1. **Reusability:** Same engine across all products
2. **Consistency:** Unified motion language and design standards
3. **Quality:** Premium feel through physics-based animations
4. **Scalability:** Easy to add new industries with industry-specific plugins

---

## Why It Is Being Postponed

The Experience Engine is a sophisticated system that requires significant development time and complexity. For the immediate goal of onboarding the first salon within 24-48 hours, this level of immersion is not required.

### Reasons for Postponement

1. **Time Constraints:** Building a complete 3D immersive experience takes weeks/months
2. **MVP Focus:** First salon needs functional booking, not immersive visuals
3. **Complexity:** Three.js, React Three Fiber, and WebGL add significant complexity
4. **Validation Needed:** Need to validate core business model before investing in premium UX
5. **Single Salon Context:** Immersive experiences shine at scale, not for one salon

---

## Estimated Phase for Reintroduction

**Phase 5** (After successful MVP with multiple paying salons)

### Prerequisites for Reintroduction

1. **Validation:** Core booking system working with 10+ salons
2. **Revenue:** Monthly recurring revenue validates investment in premium UX
3. **Technical Debt:** Codebase stable enough to handle complexity
4. **Performance:** Current system meets performance targets
5. **Team:** Development capacity to maintain both systems

### Reintroduction Plan

1. **Restore Archive:** Move `src/core/` back from archive
2. **Gradual Rollout:** Start with one experience family (Executive)
3. **A/B Testing:** Test immersive vs standard conversion rates
4. **Opt-in:** Allow salons to choose immersive experience
5. **Performance:** Ensure 60 FPS on all devices

---

## Preserved Code

All Experience Engine code has been preserved in the project archive:

- `src/core/components/` - GlassCard, GlassButton, FloatingElement, CursorGlow
- `src/core/motion/` - AnimationEngine, SceneManager
- `src/core/scenes/` - 3D scene components
- `src/core/materials/` - Material configurations
- `src/core/identity/` - Typography, spacing, motion tokens

---

## Technical Notes

### Dependencies Removed (for MVP)
- Three.js
- React Three Fiber
- @react-three/drei
- @react-three/fiber
- Framer Motion (reduced to basic transitions)

### Design Bible Preserved
The EMKAY_STUDIOS_DESIGN_BIBLE.md is preserved as it contains valuable design principles that can be applied even without the full Experience Engine:

- Spring physics concepts
- Glass material specifications
- Typography system
- Spacing system
- Color system

---

## Future Considerations

When reintroducing the Experience Engine, consider:

1. **Performance Budget:** Immersive features must not degrade performance
2. **Accessibility:** Ensure immersive experiences remain accessible
3. **Mobile Optimization:** Heavy 3D effects need mobile fallbacks
4. **Progressive Enhancement:** Start with standard, enhance to immersive
5. **User Choice:** Allow salons to opt-in to immersive experience

---

**End of Experience Engine Archive**

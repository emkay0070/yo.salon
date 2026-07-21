# EmKay Studios Design Bible

**Version:** 1.0  
**Last Updated:** June 30, 2026  
**Purpose:** The definitive guide for building EmKay Studios experiences. Every developer, designer, and AI agent works from this playbook.

---

## Table of Contents

01. [Vision](#01-vision)  
02. [Philosophy](#02-philosophy)  
03. [Experience Principles](#03-experience-principles)  
04. [Motion Language](#04-motion-language)  
05. [Spatial Design](#05-spatial-design)  
06. [Lighting Rules](#06-lighting-rules)  
07. [Typography](#07-typography)  
08. [Sound Design](#08-sound-design)  
09. [Accessibility](#09-accessibility)  
10. [Mobile Experience](#10-mobile-experience)  
11. [Performance Rules](#11-performance-rules)  
12. [Component Library](#12-component-library)  
13. [Experience Engine](#13-experience-engine)  
14. [SaaS Architecture](#14-saas-architecture)  
15. [AI Experience Generator](#15-ai-experience-generator)  
16. [Coding Standards](#16-coding-standards)

---

## 01 Vision

### Purpose
EmKay Studios is an Experience-as-a-Service platform. We don't build websites; we craft digital environments that feel like physical spaces.

### Why We Exist
Traditional websites are flat, transactional, and forgettable. Users click through pages, complete tasks, and leave. No emotional connection is formed.

EmKay Studios changes this. We create immersive digital experiences where users don't just visit—they enter, explore, and remember.

### The EmKay Difference
- **Not a website builder.** An experience architect.
- **Not a template system.** A spatial design engine.
- **Not a CMS.** A storytelling platform.

### Target Industries
- Salons & Barbershops (Yo Salon)
- Restaurants & Hospitality
- Healthcare Clinics
- Fitness & Gyms
- Education & Training
- Real Estate
- Retail Spaces

### The Feeling
When a user enters an EmKay experience, they should feel:
- **Curiosity** — "What is this place?"
- **Immersion** — "I'm inside something, not just looking at it."
- **Premium** — "This feels expensive and intentional."
- **Memorable** — "I've never experienced this before."

---

## 02 Philosophy

### Core Belief
**Digital experiences should feel as rich, nuanced, and emotional as physical spaces.**

### Design Philosophy

#### 1. Space Over Pages
We don't design pages. We design spaces. Users navigate through environments, not scroll through content.

#### 2. Physics Over Transitions
Nothing fades. Everything moves. Motion follows physical laws—springs, momentum, gravity. Transitions feel natural, not programmed.

#### 3. Atmosphere Over Information
Information is necessary but insufficient. Atmosphere creates emotion. We prioritize mood, lighting, and spatial context alongside content.

#### 4. Modular Over Monolithic
Every immersive feature is reusable. The glass card system for Yo Salon becomes the glass card system for Yo Restaurant, Yo Clinic, and beyond.

#### 5. Performance Over Visuals
60 FPS is non-negotiable. We never sacrifice performance for visual effects. If WebGL fails, we have elegant fallbacks.

### The EmKay Standard
Every EmKay experience must:
- Separate the Experience Layer from the Business Layer
- Use the same core engine across all industries
- Allow industry-specific visual customization
- Maintain consistent motion and interaction patterns
- Achieve target performance metrics

---

## 03 Experience Principles

### Principle 1: Entry Matters
The first 3 seconds determine whether a user stays or leaves.

**Requirements:**
- No traditional hero sections with static images
- Initial scene establishes spatial context
- Camera movement guides attention
- UI elements fade in after spatial context is established
- Loading states are part of the experience, not interruptions

### Principle 2: Depth is Essential
Flat designs feel like documents. Depth feels like space.

**Requirements:**
- Every element has elevation
- Glass materials with real-time blur
- Shadows that respond to light sources
- Parallax effects on scroll and cursor movement
- 3D transforms for interactive elements

### Principle 3: Movement is Communication
Static elements are dead elements. Movement communicates intent, hierarchy, and state.

**Requirements:**
- Hover states use spring physics
- Scroll-triggered animations with stagger
- Cursor-responsive elements
- Ambient motion (particles, floating elements)
- No linear easing—always use springs or custom curves

### Principle 4: Glass is the Material
Glass communicates premium, modern, and spatial.

**Requirements:**
- Glass opacity: 8% base, 12% on hover
- Glass blur: 40px backdrop-filter
- Glass border: 1px solid rgba(255, 255, 255, 0.1)
- Glass reflection: Subtle gradient overlay
- Glass shadow: Dynamic based on elevation

### Principle 5: Light Guides Attention
Lighting is not just aesthetic—it's functional.

**Requirements:**
- Primary light source establishes hierarchy
- Secondary lights create depth
- Ambient light fills shadows
- Dynamic lighting on interaction
- Light responds to cursor position

---

## 04 Motion Language

### Motion Rules

#### No Fades
Nothing simply fades in or out. Every element enters or exits with motion.

**Entry Patterns:**
- Slide from below with spring
- Scale up with elastic easing
- Unfold from center
- Rotate in with momentum

**Exit Patterns:**
- Slide away in direction of travel
- Scale down with snap
- Collapse to point of origin
- Rotate out with follow-through

#### Spring Physics
All motion uses spring physics, not linear or bezier curves.

**Spring Configurations:**
```javascript
// Gentle (UI elements)
{ stiffness: 100, damping: 15 }

// Snappy (buttons, cards)
{ stiffness: 300, damping: 25 }

// Bouncy (attention-grabbing)
{ stiffness: 400, damping: 20 }

// Heavy (large elements)
{ stiffness: 150, damping: 20 }
```

#### Staggered Animations
Multiple elements never animate simultaneously.

**Stagger Rules:**
- List items: 50ms stagger
- Grid items: 100ms stagger
- Scene elements: 150ms stagger
- Page transitions: 200ms stagger

#### Cursor Response
Interactive elements respond to cursor proximity.

**Response Types:**
- Magnetic pull on buttons
- Tilt on cards (3D transform)
- Glow on hover
- Scale on proximity
- Parallax on scroll

### Animation Duration Guidelines

| Element Type | Duration |
|--------------|----------|
| Micro-interactions | 200-300ms |
| Card hover | 300-400ms |
| Page transitions | 500-700ms |
| Scene changes | 800-1200ms |
| Loading sequences | 1500-2000ms |

---

## 05 Spatial Design

### Z-Index Hierarchy

```
1000: Modals, overlays
 900: Tooltips, popovers
 800: Dropdowns, menus
 700: Floating elements
 600: Cards, panels
 500: Navigation
 400: Content sections
 300: Background elements
 200: Ambient particles
 100: Base background
   0: WebGL canvas
```

### Elevation System

| Level | Elevation | Use Case |
|-------|-----------|----------|
| 0 | 0px | Base surface |
| 1 | 4px | Cards, panels |
| 2 | 8px | Floating elements |
| 3 | 16px | Modals, dialogs |
| 4 | 32px | Overlays |

### Corner Radius

- **Small elements:** 8px (buttons, tags)
- **Medium elements:** 16px (cards, inputs)
- **Large elements:** 24px (panels, sections)
- **Extra large:** 32px (modals, containers)

### Spacing System

Based on 8px grid:

- **xs:** 4px
- **sm:** 8px
- **md:** 16px
- **lg:** 24px
- **xl:** 32px
- **2xl:** 48px
- **3xl:** 64px
- **4xl:** 96px

### Glass Material Specification

```css
.glass {
  background: rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(40px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.glass:hover {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.2);
}
```

---

## 06 Lighting Rules

### Light Sources

#### Primary Light
- Position: Top-left (45° angle)
- Intensity: 1.0
- Color: Warm white (5000K)
- Purpose: Main illumination, highlights

#### Secondary Light
- Position: Bottom-right (135° angle)
- Intensity: 0.5
- Color: Cool white (6000K)
- Purpose: Fill light, depth

#### Ambient Light
- Position: Omni-directional
- Intensity: 0.3
- Color: Neutral (4500K)
- Purpose: Shadow fill, base illumination

### Dynamic Lighting

#### Cursor Light
- Follows cursor position
- Intensity: 0.4
- Radius: 200px
- Falloff: Gaussian
- Purpose: Interactive feedback

#### Hover Glow
- Activates on element hover
- Intensity: 0.6
- Color: Accent color
- Duration: 300ms spring
- Purpose: Attention guidance

### Shadow System

```css
.shadow-sm {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.shadow-md {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.shadow-lg {
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.shadow-xl {
  box-shadow: 0 16px 64px rgba(0, 0, 0, 0.25);
}
```

### Reflection Rules

- Glass surfaces: 5% reflection gradient
- Metallic surfaces: 15% reflection gradient
- Water surfaces: 25% reflection gradient
- Reflection angle: Matches light source
- Reflection blur: 2px

---

## 07 Typography

### Font Families

#### Primary Font: Sora
- Use: Headlines, display text
- Weights: 300, 400, 500, 600, 700
- Style: Modern, geometric, premium

#### Secondary Font: Poppins
- Use: Body text, UI elements
- Weights: 300, 400, 500, 600
- Style: Clean, readable, neutral

### Type Scale

| Scale | Size | Line Height | Use Case |
|-------|------|-------------|----------|
| XS | 12px | 16px | Labels, captions |
| SM | 14px | 20px | Body text, UI |
| Base | 16px | 24px | Default body |
| MD | 18px | 28px | Subheadings |
| LG | 24px | 32px | Section headings |
| XL | 32px | 40px | Page headings |
| 2XL | 48px | 56px | Hero headings |
| 3XL | 64px | 72px | Display headings |
| 4XL | 96px | 100px | Hero display |

### Font Weights

- **Light (300):** Large display text, elegant accents
- **Regular (400):** Body text, standard UI
- **Medium (500):** Emphasized body, buttons
- **Semi-bold (600):** Headings, important UI
- **Bold (700):** Hero text, strong emphasis

### Letter Spacing

- **Headings:** -0.02em (tight)
- **Body:** 0em (normal)
- **Uppercase:** 0.1em (expanded)
- **Small text:** 0.05em (slightly expanded)

### Color System

#### Neutral Colors
```css
--neutral-50: #FAFAFA
--neutral-100: #F5F5F5
--neutral-200: #E5E5E5
--neutral-300: #D4D4D4
--neutral-400: #A3A3A3
--neutral-500: #737373
--neutral-600: #525252
--neutral-700: #404040
--neutral-800: #262626
--neutral-900: #171717
```

#### Accent Colors (Industry-Specific)
```css
/* Yo Salon - Orange */
--accent-primary: #FF622B
--accent-secondary: #FF8C5A

/* Yo Restaurant - Green */
--accent-primary: #10B981
--accent-secondary: #34D399

/* Yo Clinic - Blue */
--accent-primary: #3B82F6
--accent-secondary: #60A5FA
```

### Text Rules

- **Maximum line length:** 75 karakter
- **Paragraph spacing:** 1.5em
- **Heading spacing:** 1em above, 0.5em below
- **No orphan words:** Use non-breaking spaces
- **Contrast ratio:** Minimum 4.5:1 for body text

---

## 08 Sound Design

### Philosophy
Sound enhances immersion but never interrupts. Audio is optional, subtle, and context-aware.

### Sound Categories

#### Ambient Sounds
- Volume: 10-20% of max
- Type: White noise, nature sounds, room tone
- Purpose: Spatial atmosphere
- Auto-play: After user interaction

#### UI Sounds
- Volume: 30-40% of max
- Type: Clicks, hovers, transitions
- Purpose: Interaction feedback
- Auto-play: On user action only

#### Success Sounds
- Volume: 50% of max
- Type: Chimes, confirmations
- Purpose: Positive reinforcement
- Auto-play: On completion

#### Error Sounds
- Volume: 40% of max
- Type: Soft beeps, warnings
- Purpose: Error notification
- Auto-play: On error only

### Sound Guidelines

- **Duration:** Under 500ms for UI sounds
- **Fade:** 100ms fade in/out
- **Frequency:** Pleasant, non-intrusive
- **Spatial:** Optional 3D positioning
- **Mute:** Always accessible, persists across sessions

### Audio Formats

- **Primary:** AAC (compressed, widely supported)
- **Fallback:** MP3 (maximum compatibility)
- **Quality:** 128kbps minimum, 256kbps preferred
- **File size:** Under 100KB per sound

---

## 09 Accessibility

### Core Principle
Immersive experiences must be accessible to everyone. Visual effects enhance, never replace, core functionality.

### WCAG 2.1 Compliance

#### Level AA Requirements
- Color contrast: 4.5:1 for text, 3:1 for UI
- Keyboard navigation: All interactive elements
- Focus indicators: Visible, 2px minimum
- Text resize: Up to 200% without layout break
- No seizures: No flashing > 3 times per second

### Reduced Motion Support

```css
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

### Keyboard Navigation

- **Tab order:** Logical, left-to-right, top-to-bottom
- **Focus trap:** Modals, dialogs
- **Skip links:** "Skip to content" on page load
- **Focus visible:** Always show focus state
- **Escape key:** Close modals, menus

### Screen Reader Support

- **ARIA labels:** All interactive elements
- **Live regions:** Dynamic content updates
- **Alt text:** All images, meaningful icons
- **Semantic HTML:** Proper heading hierarchy
- **Hidden text:** Visual-only content

### Color Independence

- **Never rely on color alone** for meaning
- **Use patterns:** Icons, text labels, underlines
- **Test modes:** Grayscale, colorblind simulation
- **High contrast mode:** Support system preference

### Touch Targets

- **Minimum size:** 44x44px
- **Spacing:** 8px between targets
- **Gesture support:** Standard gestures only
- **No hover-dependent:** Critical features

---

## 10 Mobile Experience

### Philosophy
Mobile is not a smaller desktop. Mobile is a different context with different behaviors, constraints, and opportunities.

### Performance Targets

| Device | Target FPS | Load Time |
|--------|------------|-----------|
| High-end mobile | 60 FPS | < 2s |
| Mid-range mobile | 45 FPS | < 3s |
| Low-end mobile | 30 FPS | < 4s |

### Responsive Breakpoints

```css
/* Mobile */
@media (max-width: 640px) { }

/* Tablet */
@media (min-width: 641px) and (max-width: 1024px) { }

/* Desktop */
@media (min-width: 1025px) { }
```

### Mobile-Specific Rules

#### Touch Optimization
- Minimum touch target: 44x44px
- No hover-dependent interactions
- Swipe gestures for navigation
- Pull-to-refresh where appropriate
- Haptic feedback on actions

#### Layout Adaptation
- Single column layouts
- Stacked cards, not grids
- Bottom navigation for primary actions
- Collapsible menus
- Full-width buttons

#### Performance Optimization
- Reduce particle count by 50%
- Simplify lighting calculations
- Lower shadow quality
- Reduce blur radius
- Lazy load off-screen content

#### Input Adaptation
- Large input fields (48px height)
- Auto-focus on relevant inputs
- Numeric keyboards for numbers
- Date pickers for dates
- File pickers for uploads

### Mobile-First Development
All features must work on mobile first. Desktop enhancements are progressive, not required.

---

## 11 Performance Rules

### Non-Negotiable Targets

#### Frame Rate
- **Desktop:** 60 FPS minimum
- **Mobile:** 30 FPS minimum
- **Measure:** Chrome DevTools Performance tab

#### Load Time
- **First Contentful Paint:** < 1.5s
- **Largest Contentful Paint:** < 2.5s
- **Time to Interactive:** < 3.5s

#### Bundle Size
- **Initial JS:** < 200KB gzipped
- **Total JS:** < 500KB gzipped
- **CSS:** < 50KB gzipped
- **Images:** WebP format, lazy loaded

### Optimization Strategies

#### Code Splitting
- Route-based splitting
- Component-based splitting
- Dynamic imports for heavy libraries
- Tree shaking for unused code

#### Asset Optimization
- Images: WebP with fallback
- Fonts: Subset, WOFF2 format
- Icons: SVG sprites
- Video: Streaming, adaptive bitrate

#### Rendering Optimization
- Virtual scrolling for long lists
- RequestAnimationFrame for animations
- Passive event listeners
- Debounce/throttle handlers

#### WebGL Optimization
- Reuse geometries and materials
- Instance rendering for repeated objects
- Level of detail (LOD) for distant objects
- Frustum culling
- Object pooling

### Performance Monitoring

```javascript
// Core Web Vitals
reportWebVitals((metric) => {
  console.log(metric);
  // Send to analytics
});

// FPS Monitoring
let lastTime = performance.now();
let frames = 0;

function measureFPS() {
  const now = performance.now();
  frames++;
  
  if (now - lastTime >= 1000) {
    console.log(`FPS: ${frames}`);
    frames = 0;
    lastTime = now;
  }
  
  requestAnimationFrame(measureFPS);
}
```

### Fallback Strategy

#### WebGL Fallback
- Detect WebGL support on load
- Provide static image fallback
- Maintain functionality without 3D
- Graceful degradation

#### Low-End Device Fallback
- Detect device capabilities
- Reduce particle count
- Disable expensive effects
- Simplify animations

---

## 12 Component Library

### Core Components

#### GlassCard
Floating glass panel with hover effects.

**Props:**
- `elevation`: 0-4
- `children`: ReactNode
- `hoverable`: boolean
- `onClick`: function

**Usage:**
```jsx
<GlassCard elevation={2} hoverable>
  <h3>Service Name</h3>
  <p>Description</p>
</GlassCard>
```

#### GlassButton
Glass-styled button with spring animation.

**Props:**
- `variant`: 'primary' | 'secondary' | 'ghost'
- `size`: 'sm' | 'md' | 'lg'
- `children`: ReactNode
- `onClick`: function

**Usage:**
```jsx
<GlassButton variant="primary" size="md">
  Book Now
</GlassButton>
```

#### SceneContainer
3D scene wrapper with camera controls.

**Props:**
- `camera`: CameraConfig
- `lighting`: LightingConfig
- `children`: ReactNode
- `onReady`: function

**Usage:**
```jsx
<SceneContainer camera={{ position: [0, 0, 5] }}>
  <Model src="/model.glb" />
</SceneContainer>
```

#### FloatingElement
Element that floats with ambient motion.

**Props:**
- `amplitude`: number
- `speed`: number
- `children`: ReactNode

**Usage:**
```jsx
<FloatingElement amplitude={10} speed={1}>
  <Icon />
</FloatingElement>
```

#### CursorGlow
Cursor-following light effect.

**Props:**
- `color`: string
- `intensity`: number
- `radius`: number

**Usage:**
```jsx
<CursorGlow color="#FF622B" intensity={0.4} radius={200} />
```

### Component Guidelines

- **Props:** TypeScript interfaces required
- **Styling:** Tailwind CSS + custom styles
- **Animation:** Framer Motion
- **Testing:** Jest + React Testing Library
- **Documentation:** Storybook stories

---

## 13 Experience Engine

### Architecture Overview

```
Experience Layer (Industry-Specific)
    ↓
Experience Engine (Core)
    ↓
Business Layer (Industry-Agnostic)
```

### Core Modules

#### SceneManager
Manages 3D scenes, camera, and rendering.

**Responsibilities:**
- Scene initialization
- Camera controls
- Render loop
- Object management
- Lighting management

#### AnimationEngine
Handles all motion and animations.

**Responsibilities:**
- Spring physics
- Staggered animations
- Scroll-triggered animations
- Cursor interactions
- Performance optimization

#### AudioManager
Controls all audio playback.

**Responsibilities:**
- Sound loading
- Volume control
- Spatial audio
- Mute state
- Cross-browser compatibility

#### InputManager
Handles all user input.

**Responsibilities:**
- Mouse/Touch tracking
- Keyboard events
- Gesture recognition
- Focus management
- Accessibility support

#### AssetManager
Manages all assets and resources.

**Responsibilities:**
- Asset loading
- Caching strategy
- Lazy loading
- Preloading
- Error handling

### API Design

```typescript
interface ExperienceEngine {
  // Scene management
  createScene(config: SceneConfig): Scene;
  getScene(id: string): Scene;
  destroyScene(id: string): void;
  
  // Animation
  animate(target: any, config: AnimationConfig): void;
  stagger(targets: any[], config: StaggerConfig): void;
  
  // Audio
  playSound(id: string, options: SoundOptions): void;
  stopSound(id: string): void;
  
  // Input
  on(event: string, handler: Function): void;
  off(event: string, handler: Function): void;
}
```

### Plugin System

The Experience Engine supports plugins for industry-specific features.

**Plugin Interface:**
```typescript
interface ExperiencePlugin {
  name: string;
  version: string;
  init(engine: ExperienceEngine): void;
  destroy(): void;
}
```

**Example Plugins:**
- `SalonPlugin`: Booking system, service menu
- `RestaurantPlugin`: Menu, reservations
- `ClinicPlugin`: Appointments, services

---

## 14 SaaS Architecture

### System Architecture

```
Client Layer (Next.js)
    ↓
API Layer (Next.js API Routes)
    ↓
Business Logic Layer
    ↓
Data Layer (PostgreSQL)
    ↓
Infrastructure (Vercel/AWS)
```

### Core Services

#### AuthService
Authentication and authorization.

**Features:**
- Email/password authentication
- OAuth providers (Google, Apple)
- Session management
- Role-based access control
- Multi-tenant support

#### ContentService
CMS for dynamic content.

**Features:**
- Rich text editing
- Media_management
- Version control
- Publishing workflow
- Multi-language support

#### BookingService
Appointment scheduling.

**Features:**
- Calendar management
- Availability checking
- Time slot generation
- Reminder system
- Cancellation handling

#### AnalyticsService
Usage tracking and insights.

**Features:**
- Event tracking
- User analytics
- Performance monitoring
- Custom dashboards
- Export functionality

#### TenantService
Multi-tenant management.

**Features:**
- Tenant isolation
- Custom domains
- White-labeling
- Billing integration
- Resource quotas

### Database Schema

**Core Tables:**
- `tenants` - Tenant information
- `users` - User accounts
- `roles` - User roles
- `permissions` - Role permissions
- `content` - CMS content
- `bookings` - Appointments
- `analytics` - Usage data

### API Design

**RESTful Endpoints:**
```
GET    /api/tenants/:id
POST   /api/tenants
PUT    /api/tenants/:id
DELETE /api/tenants/:id

GET    /api/bookings
POST   /api/bookings
PUT    /api/bookings/:id
DELETE /api/bookings/:id
```

**WebSocket Events:**
```typescript
// Real-time updates
socket.on('booking:created', handleBookingCreated);
socket.on('booking:updated', handleBookingUpdated);
socket.on('booking:cancelled', handleBookingCancelled);
```

---

## 15 AI Experience Generator

### Purpose
Generate industry-specific experiences from natural language descriptions.

### Architecture

```
User Input
    ↓
Intent Recognition
    ↓
Template Selection
    ↓
Parameter Extraction
    ↓
Experience Generation
    ↓
Preview & Refinement
```

### Capabilities

#### Scene Generation
Generate 3D scenes from descriptions.

**Input:** "Modern salon with warm lighting and glass shelves"
**Output:** Three.js scene with configured lighting, materials, and objects

#### Content Generation
Generate copy and content.

**Input:** "Luxury barbershop targeting young professionals"
**Output:** Headlines, descriptions, CTAs

#### Color Palette Generation
Generate brand-appropriate colors.

**Input:** "Premium, warm, inviting salon"
**Output:** Color palette with hex codes

#### Animation Generation
Generate animation sequences.

**Input:** "Cards float up on scroll"
**Output:** Framer Motion animation config

### Prompt Engineering

**System Prompt:**
```
You are an Experience Architect for EmKay Studios. 
Generate immersive digital experiences following the EmKay Design Bible.
Prioritize atmosphere, motion, and spatial design.
Separate experience layer from business layer.
Use modular, reusable components.
```

### Quality Assurance

**Validation Rules:**
- Must use EmKay component library
- Must meet performance targets
- Must be accessible (WCAG AA)
- Must be responsive
- Must follow motion language

---

## 16 Coding Standards

### General Principles

#### 1. Clarity Over Cleverness
Code should be readable by humans first, machines second.

#### 2. Consistency is Key
Follow existing patterns. Don't introduce variations without reason.

#### 3. Test Everything
Unit tests for logic, integration tests for flows, E2E for critical paths.

#### 4. Document Intent
Comments explain why, not what. Code explains what.

### TypeScript Standards

#### Type Definitions
```typescript
// Always use interfaces for object shapes
interface User {
  id: string;
  name: string;
  email: string;
}

// Use types for unions, primitives, utilities
type UserID = string;
type Theme = 'light' | 'dark';

// Avoid 'any' - use 'unknown' with type guards
function process(data: unknown) {
  if (isUser(data)) {
    // TypeScript knows data is User
  }
}
```

#### Naming Conventions
- **Interfaces:** PascalCase (e.g., `UserService`)
- **Types:** PascalCase (e.g., `ThemeConfig`)
- **Functions:** camelCase (e.g., `getUserById`)
- **Constants:** UPPER_SNAKE_CASE (e.g., `API_BASE_URL`)
- **Private members:** underscore prefix (e.g., `_internalState`)

### React Standards

#### Component Structure
```typescript
// 1. Imports
import { useState, useEffect } from 'react';

// 2. Types
interface Props {
  title: string;
  onClick: () => void;
}

// 3. Component
export function Button({ title, onClick }: Props) {
  // 4. Hooks
  const [isHovered, setIsHovered] = useState(false);
  
  // 5. Effects
  useEffect(() => {
    // Side effects
  }, []);
  
  // 6. Handlers
  const handleClick = () => {
    onClick();
  };
  
  // 7. Render
  return (
    <button onClick={handleClick}>
      {title}
    </button>
  );
}
```

#### Hooks Rules
- Custom hooks start with 'use' (e.g., `useAnimation`)
- Hooks only called at top level
- No conditional hook calls
- Extract complex logic into custom hooks

### CSS Standards

#### Tailwind First
Use Tailwind utility classes for 95% of styling.

```jsx
<div className="flex items-center justify-between p-4 bg-white rounded-lg shadow-md">
  {/* Content */}
</div>
```

#### Custom CSS for Complex Effects
Use custom CSS only for animations, complex effects, or third-party overrides.

```css
@layer utilities {
  .glass {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(40px);
  }
}
```

### Git Standards

#### Commit Message Format
```
type(scope): subject

body

footer
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `refactor`: Code refactoring
- `style`: Code style changes
- `docs`: Documentation
- `test`: Test changes
- `chore`: Maintenance

**Examples:**
```
feat(booking): add calendar integration

Implement calendar sync with Google Calendar API.
Includes error handling and retry logic.

Closes #123
```

#### Branch Strategy
- `main`: Production code
- `develop`: Integration branch
- `feature/*`: Feature branches
- `bugfix/*`: Bug fix branches
- `hotfix/*`: Production hotfixes

### Testing Standards

#### Unit Tests
```typescript
describe('UserService', () => {
  it('should return user by ID', async () => {
    const user = await getUserById('123');
    expect(user).toBeDefined();
    expect(user.id).toBe('123');
  });
});
```

#### Integration Tests
```typescript
describe('Booking Flow', () => {
  it('should complete booking process', async () => {
    await page.goto('/booking');
    await page.fill('[name="date"]', '2026-07-01');
    await page.click('[type="submit"]');
    await expect(page).toHaveURL('/confirmation');
  });
});
```

### Code Review Checklist

- [ ] Follows coding standards
- [ ] Has appropriate tests
- [ ] Documentation updated
- [ ] No console errors
- [ ] Accessible (keyboard, screen reader)
- [ ] Responsive (mobile, tablet, desktop)
- [ ] Performance targets met
- [ ] No security vulnerabilities

---

## Appendix

### Glossary

- **Experience Layer:** Industry-specific visual and interactive elements
- **Business Layer:** Industry-agnostic core functionality
- **Experience Engine:** Core technology powering all EmKay experiences
- **Glass Material:** Semi-transparent, blurred UI material
- **Spring Physics:** Natural motion based on physical laws
- **Spatial Design:** Design that feels like physical space

### Resources

**Internal:**
- EmKay Studios Component Library (Storybook)
- Experience Engine Documentation
- API Reference

**External:**
- Three.js Documentation
- Framer Motion Documentation
- Next.js Documentation
- WCAG 2.1 Guidelines

### Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | June 30, 2026 | Initial release |

---

**End of EmKay Studios Design Bible**

This document is the single source of truth for all EmKay Studios experiences. Any deviation must be approved by the Creative Director.

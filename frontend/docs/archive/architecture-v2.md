# Original Architecture v2 - Archived

**Archived Date:** July 14, 2026
**Original Location:** EMKAY_PLATFORM_ARCHITECTURE.md
**Status:** Postponed - Simplified to MVP Architecture

---

## Original Architecture Overview

The original architecture was designed as a multi-industry Experience-as-a-Service platform with immersive 3D experiences, AI-powered customization, and sophisticated multi-tenancy.

### Ecosystem Model

```
EmKay Studios (Platform Core)
    ↓
Yo Salon (Vertical Product)
    ↓
Customer Salon Websites (Generated Instances)
    ↓
Salon Management Dashboard (Admin Layer)
```

### Four Experience Layers

1. **EmKay Studios Platform Core** - Platform marketing and business development
2. **Yo Salon Vertical Product** - Product-specific marketing for salon industry
3. **Customer Salon Websites** - Customer-facing salon websites (generated instances)
4. **Salon Management Dashboard** - Salon administration and management

---

## Original Technical Architecture

### Technology Stack

**Frontend:**
- Next.js 16 with App Router
- React Three Fiber + Three.js (3D engine)
- Framer Motion (animations)
- Tailwind CSS v4 (styling)
- shadcn/ui + Radix UI (components)

**Backend:**
- Next.js API Routes
- PostgreSQL (multi-tenant)
- NextAuth.js (authentication)
- AWS S3 / Cloudflare R2 (file storage)
- WebSocket / Server-Sent Events (real-time)

**AI/ML:**
- OpenAI GPT-4 (experience generation)
- DALL-E 3 / Stable Diffusion (image generation)
- Custom pipeline (3D model generation)
- Computer vision (brand analysis)

### Architecture Patterns

**Multi-Tenancy:**
- Shared database with tenant isolation
- Subdomain routing per salon
- Tenant-scoped data access
- Resource quotas per plan

**Experience Engine:**
- Reusable 3D scene components
- Configurable experience families
- Material and lighting presets
- Animation physics library

**Performance:**
- Edge caching with Cloudflare
- Image optimization
- 3D asset lazy loading
- Code splitting by route

---

## Original System Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Client Layer                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   EmKay     │  │  Yo Salon    │  │  Customer    │  │
│  │   Marketing │  │  Marketing   │  │  Instance    │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                    API Layer                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Next.js    │  │  Next.js     │  │  Next.js     │  │
│  │   API Routes │  │  API Routes  │  │  API Routes  │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│              Business Logic Layer                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Auth       │  │  Content     │  │  Booking     │  │
│  │   Service    │  │  Service     │  │  Service     │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Tenant     │  │  Analytics   │  │   AI         │  │
│  │   Service    │  │  Service     │  │  Service     │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                   Experience Engine                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Scene      │  │  Animation   │  │   Asset      │  │
│  │   Manager    │  │   Engine     │  │   Manager    │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Audio      │  │   Input      │  │   Plugin     │  │
│  │   Manager    │  │   Manager    │  │   System     │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└—————————————————————————————————————————————————————————┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                    Data Layer                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │ PostgreSQL   │  │   Redis      │  │   S3 / CDN   │  │
│  │   (Primary)  │  │   (Cache)    │  │   (Assets)   │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                 Infrastructure                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Vercel     │  │    AWS       │  │   Cloudflare │  │
│  │   (Hosting)  │  │   (Services) │  │   (CDN)      │  │
│  └──────────────┘└──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## Original Database Schema

### Core Tables (Planned)
- `tenants` - Salon accounts
- `users` - User accounts
- `roles` - User roles
- `permissions` - Role permissions
- `subscriptions` - Subscription plans
- `content` - CMS content
- `bookings` - Appointments
- `analytics` - Usage data

### Business Tables (Planned)
- `services` - Service menu
- `staff` - Staff management
- `customers` - Customer profiles
- `inventory` - Product inventory
- `payments` - Payment records
- `reviews` - Customer reviews
- `loyalty` - Loyalty program
- `notifications` - Notification logs

---

## Why This Architecture Is Being Postponed

The original architecture was designed for a sophisticated, multi-tenant, multi-industry platform with immersive 3D experiences. This level of complexity is not needed for onboarding the first salon.

### Reasons for Simplification

1. **Over-Engineering:** Building for scale before validating need
2. **Time Constraints:** Original architecture would take 6+ months
3. **Complexity:** Too many moving parts (3D, AI, multi-tenancy)
4. **Validation Risk:** Investing heavily before market validation
5. **Single Salon Context:** Multi-tenancy not needed for one salon

---

## New MVP Architecture

### Simplified Technology Stack

**Frontend:**
- Next.js 14/16 with App Router (preserved)
- TypeScript (preserved)
- Tailwind CSS v4 (preserved)
- shadcn/ui (preserved)
- Basic transitions (Framer Motion reduced)

**Backend:**
- Next.js API Routes (preserved)
- PostgreSQL (local development) (preserved)
- Prisma ORM (new - for MVP)
- Supabase (production database) (new - for MVP)

**Removed (for MVP):**
- React Three Fiber + Three.js
- Complex animation system
- AI/ML pipeline
- Multi-tenant complexity
- Experience Engine

### Simplified Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Client Layer                         │
│  ┌──────────────┐  ┌──────────────┐                   │
│  │   Marketing  │  │   Dashboard  │  │  Public Site  │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                    API Layer                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Auth API   │  │  Booking API │  │  CRUD APIs   │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│              Business Logic Layer                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Auth       │  │  Booking     │  │   Services   │  │
│  │   Service    │  │  Service     │  │   Service    │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                    Data Layer                            │
│  ┌──────────────┐  ┌──────────────┐                     │
│  │ PostgreSQL   │  │   Prisma     │                     │
│  │   (Local)    │  │   (ORM)      │                     │
│  └──────────────┘  └──────────────┘                     │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                 Infrastructure                           │
│  ┌──────────────┐  ┌──────────────┐                     │
│  │   Local      │  │   Supabase   │                     │
│  │   (Dev)      │  │   (Prod)     │                     │
│  └──────────────┘  └──────────────┘                     │
└─────────────────────────────────────────────────────────┘
```

### MVP Database Schema

**Core Tables (6 tables only):**
- `salons` - Salon information
- `profiles` - User accounts (salon owners)
- `staff` - Staff members
- `services` - Service menu
- `customers` - Customer information
- `bookings` - Appointments

**Future Tables (postponed):**
- `payments` - Payment records
- `inventory` - Product inventory
- `analytics` - Usage data
- `loyalty` - Loyalty program
- `reviews` - Customer reviews

---

## Migration Path to Original Architecture

When the platform is ready to scale back to the original architecture:

### Phase 1: Multi-Tenancy (Week 2-4)
- Add tenant isolation with Supabase Row Level Security
- Implement subdomain routing
- Add custom domain support
- Migrate from single salon to multiple salons

### Phase 2: Advanced Features (Month 2-3)
- Add customer accounts
- Implement payments
- Add notifications
- Build loyalty system

### Phase 3: Business Intelligence (Month 3-6)
- Add inventory management
- Build analytics/reporting
- Implement POS system
- Add payroll features

### Phase 4: Platform Expansion (Month 6+)
- Add marketplace/discovery
- Implement customer reviews
- Build developer API
- Add white-label solutions

### Phase 5: Experience Engine (Post-Validation)
- Reintroduce Experience Engine from archive
- Add Three.js scenes
- Implement AI customization
- Add experience families

### Phase 6: Multi-Industry (Phase 6+)
- Extract shared core for other industries
- Build Yo Restaurant vertical
- Build Yo Clinic vertical
- Build Yo Gym vertical

---

## Preserved Architectural Decisions

The following architectural decisions from the original plan are preserved:

### Separation of Concerns
- Client layer (UI only)
- API layer (endpoints only)
- Business logic layer (services only)
- Data layer (database only)

### Technology Choices
- Next.js with App Router (proven, scalable)
- TypeScript (type safety)
- PostgreSQL (robust relational database)
- Prisma (modern ORM)
- Tailwind CSS (utility-first styling)

### Design Principles
- Component reusability
- Clean architecture
- Type safety
- Performance optimization

---

## Key Architectural Changes

### What Changed

1. **Removed Experience Engine:** 3D, animations, audio systems postponed
2. **Simplified Database:** 6 tables instead of 15+
3. **Removed AI Pipeline:** No AI generation for MVP
4. **Simplified Auth:** Supabase Auth instead of NextAuth.js
5. **Single Tenant:** Building for one salon first
6. **Removed Multi-Industry:** Focus on salon vertical only

### What Stayed the Same

1. **Next.js Framework:** Still using Next.js with App Router
2. **TypeScript:** Maintaining type safety
3. **PostgreSQL:** Still using PostgreSQL (via Prisma)
4. **Component Architecture:** Clean separation of concerns
5. **API Structure:** RESTful API design
6. **Design Quality:** Professional, functional UI

---

## Future Considerations

When migrating back to the original architecture:

1. **Performance:** Ensure 3D features don't degrade performance
2. **Accessibility:** Maintain WCAG compliance with immersive features
3. **Mobile Optimization:** Heavy 3D needs mobile fallbacks
4. **Progressive Enhancement:** Start with standard, enhance to immersive
5. **User Choice:** Allow salons to opt-in to advanced features

---

## Lessons Learned

### What Went Right
1. **Vision:** Clear long-term platform vision
2. **Architecture:** Clean separation of concerns
3. **Technology:** Solid technology choices
4. **Scalability:** Architecture designed for growth

### What Went Wrong
1. **Over-Engineering:** Built too much before validation
2. **Time Horizon:** 6+ months to first customer
3. **Complexity:** Too many systems at once
4. **Scope:** Features without customer validation

### Corrective Actions
1. **MVP-First:** Ship simple, iterate fast
2. **Customer-Driven:** Build what customers need
3. **Incremental:** Add complexity after validation
4. **Preserve Vision:** Keep long-term vision, defer implementation

---

**End of Original Architecture v2 Archive**

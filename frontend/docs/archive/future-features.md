# Future Features - Archived

**Archived Date:** July 14, 2026
**Status:** Postponed - Features deferred until after MVP validation

---

## Features Postponed for MVP

The following features were planned for the initial platform but have been postponed to focus on MVP delivery.

---

## High-Priority Postponed Features

### 1. Multi-Tenancy
**Original Plan:** Row-level security, tenant isolation, custom domains
**Postponed Reason:** Building for single salon first, will add multi-tenancy when onboarding second salon
**Reintroduction Phase:** Week 2-4 (after first salon)
**Implementation:** Supabase Row Level Security
**Effort:** Low (salonId foreign keys already planned)

### 2. Customer Accounts
**Original Plan:** Customer login, booking history, preferences, profiles
**Postponed Reason:** Customers can book without accounts for MVP
**Reintroduction Phase:** Month 2
**Implementation:** Supabase Auth for customers
**Effort:** Medium

### 3. Payments Integration
**Original Plan:** Stripe integration, online payments, deposits
**Postponed Reason:** Manual payments for MVP
**Reintroduction Phase:** Month 2
**Implementation:** Stripe API
**Effort:** Medium

### 4. Notifications
**Original Plan:** SMS reminders, email confirmations, push notifications
**Postponed Reason:** Manual communication for MVP
**Reintroduction Phase:** Month 2
**Implementation:** Twilio (SMS), SendGrid (email)
**Effort:** Medium

---

## Medium-Priority Postponed Features

### 5. Loyalty Program
**Original Plan:** Points system, rewards, referral program
**Postponed Reason:** Nice-to-have, not core to booking
**Reintroduction Phase:** Month 2
**Implementation:** Custom loyalty system
**Effort:** Medium

### 6. Inventory Management
**Original Plan:** Product tracking, usage analytics, reorder alerts
**Postponed Reason:** Not needed for service-based booking
**Reintroduction Phase:** Month 3
**Implementation:** Inventory CRUD + analytics
**Effort:** High

### 7. Analytics & Reporting
**Original Plan:** Revenue trends, customer retention, service popularity
**Postponed Reason:** Basic dashboard sufficient for MVP
**Reintroduction Phase:** Month 3
**Implementation:** Chart.js or Recharts
**Effort:** High

### 8. POS System
**Original Plan:** Point of sale for retail products
**Postponed Reason:** Service-focused MVP
**Reintroduction Phase:** Month 3
**Implementation:** POS integration with inventory
**Effort:** High

---

## Low-Priority Postponed Features

### 9. Payroll Management
**Original Plan:** Staff hours, commission calculations, payroll processing
**Postponed Reason:** Administrative feature, not customer-facing
**Reintroduction Phase:** Month 3-6
**Implementation:** Payroll API integration
**Effort:** High

### 10. Marketplace / Discovery
**Original Plan:** Salon directory, search, reviews, discovery
**Postponed Reason:** Requires multiple salons to be valuable
**Reintroduction Phase:** Month 6+
**Implementation:** Search + filtering + reviews
**Effort:** Very High

### 11. Customer Reviews
**Original Plan:** Review collection, display, management
**Postponed Reason:** Nice-to-have for single salon
**Reintroduction Phase:** Month 6+
**Implementation:** Review system + moderation
**Effort:** Medium

### 12. Advanced Permissions
**Original Plan:** Role-based access control, granular permissions
**Postponed Reason:** Single salon owner sufficient for MVP
**Reintroduction Phase:** Month 2-3
**Implementation:** RBAC system
**Effort:** Medium

---

## Experience Engine Features (Phase 5+)

### 13. Three.js Scenes
**Original Plan:** 3D salon environments, immersive entrances
**Postponed Reason:** Over-engineering for MVP
**Reintroduction Phase:** Phase 5 (after validation)
**Implementation:** React Three Fiber + Three.js
**Effort:** Very High

### 14. React Three Fiber
**Original Plan:** 3D component system for web
**Postponed Reason:** Not needed for functional MVP
**Reintroduction Phase:** Phase 5
**Implementation:** RTF ecosystem
**Effort:** Very High

### 15. Immersive Scrolling
**Original Plan:** Scroll-triggered 3D animations, parallax
**Postponed Reason:** Performance concern, not core value
**Reintroduction Phase:** Phase 5
**Implementation:** Custom scroll system
**Effort:** High

### 16. AI Experience Generator
**Original Plan:** AI generates unique designs from logo/photos
**Postponed Reason:** Complex, time-consuming, not validated
**Reintroduction Phase:** Phase 5
**Implementation:** OpenAI Vision + GPT-4
**Effort:** Very High

### 17. Multiple Experience Families
**Original Plan:** 8+ theme families (Luxury Noir, Modern Glass, etc.)
**Postponed Reason:** Single theme sufficient for MVP
**Reintroduction Phase:** Phase 5
**Implementation:** Theme system + AI customization
**Effort:** High

### 18. Glass Experimentation
**Original Plan:** Advanced glass materials, blur effects
**Postponed Reason:** Simplified to basic glass for MVP
**Reintroduction Phase:** Phase 5
**Implementation:** Advanced CSS + WebGL
**Effort:** Medium

### 19. Advanced Animation System
**Original Plan:** Complex spring physics, staggered animations
**Postponed Reason:** Basic transitions sufficient for MVP
**Reintroduction Phase:** Phase 5
**Implementation:** Framer Motion advanced
**Effort:** Medium

---

## Platform Expansion Features (Phase 6+)

### 20. Multi-Industry Abstractions
**Original Plan:** Shared core for Yo Restaurant, Yo Clinic, Yo Gym
**Postponed Reason:** Focus on salon vertical first
**Reintroduction Phase:** Phase 6
**Implementation:** Plugin system + industry modules
**Effort:** Very High

### 21. Marketplace Logic
**Original Plan:** Third-party developers, asset marketplace
**Postponed Reason:** Platform maturity needed first
**Reintroduction Phase:** Phase 6
**Implementation:** Marketplace infrastructure
**Effort:** Very High

### 22. White-Label Solutions
**Original Plan:** Enterprise partnerships, custom branding
**Postponed Reason:** Requires platform maturity
**Reintroduction Phase:** Phase 6
**Implementation:** White-label infrastructure
**Effort:** High

### 23. Developer API
**Original Plan:** Public API for third-party integrations
**Postponed Reason:** No demand yet
**Reintroduction Phase:** Phase 6
**Implementation:** REST API + documentation
**Effort:** High

---

## Vision Pro / Future Tech Features

### 24. Vision Pro Integration
**Original Plan:** Spatial computing experiences for Apple Vision Pro
**Postponed Reason:** Platform too early, no customer demand
**Reintroduction Phase:** Phase 7+
**Implementation:** visionOS SDK
**Effort:** Very High

### 25. AR Features
**Original Plan:** Augmented reality salon previews
**Postponed Reason:** Experimental, not core value
**Reintroduction Phase:** Phase 7+
**Implementation:** ARKit / ARCore
**Effort:** Very High

### 26. VR Experiences
**Original Plan:** Virtual reality salon tours
**Postponed Reason:** Hardware adoption low
**Reintroduction Phase:** Phase 7+
**Implementation:** WebXR
**Effort:** Very High

---

## Reintroduction Criteria

Each postponed feature should be reintroduced only when:

1. **Customer Demand:** Multiple customers request the feature
2. **Revenue Validation:** Platform has sufficient MRR to justify investment
3. **Technical Readiness:** Team has capacity to implement and maintain
4. **Strategic Fit:** Feature aligns with current business priorities
5. **Cost-Benefit:** Expected ROI exceeds implementation cost

---

## Feature Prioritization Framework

When deciding which postponed feature to reintroduce first, use this framework:

### Impact Score (1-10)
- How many customers will use this?
- How much value does it provide?
- Will it drive revenue or retention?

### Effort Score (1-10)
- How complex is implementation?
- How much maintenance will it require?
- What are the technical risks?

### Priority Formula
```
Priority = Impact Score / Effort Score
```

**High Priority:** Impact 8-10, Effort 1-5
**Medium Priority:** Impact 5-7, Effort 3-7
**Low Priority:** Impact 1-4, Effort 8-10

---

## Preserved Feature Specifications

All feature specifications, designs, and technical notes are preserved in:

- Phase documents (PHASE_2 through PHASE_5)
- EMKAY_PLATFORM_ARCHITECTURE.md
- EMKAY_STUDIOS_DESIGN_BIBLE.md
- Component documentation in code comments

---

## Decision Log

### Why These Features Were Postponed

1. **Time to Market:** Need first paying customer in 48 hours
2. **Validation:** Build what customers actually need, not what we think they need
3. **Complexity:** Reduce technical debt and maintenance burden
4. **Focus:** Do one thing exceptionally well (booking) rather than many things poorly
5. **Resource Allocation:** Limited development hours, prioritize high-impact features

### What We're Keeping

1. **Core Value:** Booking system (the one thing that matters)
2. **Clean Architecture:** Separation of concerns (simplified)
3. **Design Quality:** Professional, functional UI (Executive theme)
4. **Scalability Foundation:** salonId foreign keys for future multi-tenancy
5. **Technical Stack:** Next.js, TypeScript, PostgreSQL (solid foundation)

---

**End of Future Features Archive**

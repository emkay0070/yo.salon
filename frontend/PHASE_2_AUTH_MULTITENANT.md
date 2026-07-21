# Phase 2: Authentication & Multi-tenant Setup

**Date:** TBD  
**Status:** ⏳ Pending

---

## Objective

Implement authentication system and establish multi-tenant architecture foundation for Yo Salon SaaS.

---

## Planned Tasks

### 1. Authentication System
- [ ] Choose and configure auth provider (NextAuth.js or Clerk)
- [ ] Set up authentication routes in `(auth)/` route group
- [ ] Implement sign up flow for salon owners
- [ ] Implement login flow
- [ ] Implement password reset
- [ ] Add OAuth providers (Google, Apple)
- [ ] Configure session management
- [ ] Set up role-based access control (RBAC)

### 2. Multi-tenant Database Schema
- [ ] Install and configure Prisma ORM
- [ ] Set up PostgreSQL database (Neon)
- [ ] Design tenant schema with row-level security
- [ ] Create core tables:
  - `tenants` - Salon accounts
  - `users` - User accounts
  - `roles` - User roles
  - `permissions` - Role permissions
  - `subscriptions` - Subscription plans
- [ ] Implement tenant isolation
- [ ] Set up custom domain support structure

### 3. API Routes Foundation
- [ ] Create API route structure
- [ ] Implement tenant middleware
- [ ] Set up API rate limiting
- [ ] Create error handling middleware
- [ ] Implement logging system

### 4. Salon Owner Onboarding
- [ ] Create onboarding flow in `(auth)/onboarding`
- [ ] Step 1: Salon information form
- [ ] Step 2: Upload logo
- [ ] Step 3: Upload interior photos
- [ ] Step 4: Select experience family
- [ ] Step 5: Generate initial experience
- [ ] Step 6: Preview and confirm

---

## Architecture Decisions

### Authentication Provider Choice
- **Option A:** NextAuth.js
  - Pros: Full control, open source, Next.js native
  - Cons: More setup required
  
- **Option B:** Clerk
  - Pros: Quick setup, built-in UI, excellent UX
  - Cons: Vendor lock-in, cost at scale

### Multi-tenancy Strategy
- **Database per tenant:** Not chosen (complexity)
- **Schema per tenant:** Possible option
- **Row-level security:** Chosen approach
  - Single database
  - Tenant ID on all records
  - Middleware for automatic filtering

---

## Technical Stack

- **Auth:** NextAuth.js or Clerk
- **Database:** PostgreSQL (Neon)
- **ORM:** Prisma
- **Validation:** Zod
- **API:** Next.js API Routes

---

## Goals

1. Salon owners can sign up and create accounts
2. Multi-tenant architecture supports unlimited salons
3. Database schema ready for business operations
4. API foundation for future features
5. Onboarding flow collects necessary data for experience generation

---

## Success Criteria

- [ ] Sign up flow works end-to-end
- [ ] Login/logout functions correctly
- [ ] Tenant isolation verified
- [ ] Database schema supports all planned features
- [ ] API routes protected with auth middleware
- [ ] Onboarding collects all required data

---

## Notes

---

**End of Phase 2 Documentation**

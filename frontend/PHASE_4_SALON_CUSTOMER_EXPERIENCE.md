# Phase 4: Salon Customer Experience

**Date:** TBD  
**Status:** ⏳ Pending

---

## Objective

Build the generated website experience for each subscribed salon - the public-facing website that salon customers visit.

---

## Planned Tasks

### 1. Salon Website Generator
- [ ] Create salon website template system
- [ ] Implement dynamic routing for salon subdomains
- [ ] Build salon-specific data fetching
- [ ] Create salon configuration system
- [ ] Implement custom domain support

### 2. Core Salon Website Sections

#### Hero Section
- [ ] Immersive hero with salon branding
- [ ] Dynamic content based on experience family
- [ ] Book appointment CTA
- [ ] Location and hours preview
- [ ] Spring physics animations

#### Services & Pricing
- [ ] Service menu with pricing
- [ ] Service descriptions
- [ ] Duration and pricing display
- [ ] Popular service highlighting
- [ ] Glass card layout

#### Team/Stylist Profiles
- [ ] Stylist profiles with photos
- [ ] Specializations and expertise
- [ ] Availability status
- [ ] Book with specific stylist
- [ ] Social media links

#### Gallery
- [ ] Work showcase gallery
- [ ] Before/after transformations
- [ ] Style categories
- [ ] Lightbox view
- [ ] Social sharing

#### Booking System
- [ ] Online appointment booking
- [ ] Service selection
- [ ] Stylist selection
- [ ] Date/time picker
- [ ] Confirmation flow

#### Location & Contact
- [ ] Map integration
- [ ] Address and directions
- [ ] Contact form
- [ ] Phone and WhatsApp
- [ ] Social media links

#### Reviews & Testimonials
- [ ] Customer reviews display
- [ ] Star ratings
- [ ] Review submission
- [ ] Photo reviews
- [ ] Social proof

### 3. Experience Family Application
- [ ] Apply Luxury Noir theme
- [ ] Apply Modern Glass theme
- [ ] Apply Urban theme
- [ ] Dynamic theme switching
- [ ] Theme customization options

### 4. Mobile Optimization
- [ ] Mobile-first responsive design
- [ ] Touch-optimized interactions
- [ ] Reduced particle count for mobile
- [ ] Simplified animations
- [ ] Fast load times

### 5. Performance Optimization
- [ ] Image optimization (WebP)
- [ ] Lazy loading
- [ ] Code splitting
- [ ] CDN integration
- [ ] Caching strategy

---

## Architecture Decisions

### Dynamic Routing
- Subdomain routing: `salon-name.yosalon.app`
- Custom domain routing: `salon-name.com`
- Tenant middleware for data isolation
- Route group: `(salon)/`

### Content Management
- Salon owners manage content via dashboard
- Real-time updates to public site
- Content versioning
- Preview before publish

### Experience Family Integration
- Theme applied based on salon selection
- AI customization within theme constraints
- Brand color integration
- Typography adjustments
- Material matching from uploaded photos

---

## Technical Stack

- **Routing:** Next.js dynamic routes
- **Database:** Prisma with tenant filtering
- **Images:** Next.js Image Optimization
- **Maps:** Google Maps or Mapbox
- **Forms:** React Hook Form + Zod
- **Animations:** Framer Motion

---

## Goals

1. Each salon has unique, branded website
2. Customers can book appointments online
3. Experience families create distinct atmospheres
4. Mobile-optimized for on-the-go customers
5. Fast loading and performant

---

## Success Criteria

- [ ] Salon websites load in < 2 seconds
- [ ] Mobile responsive across all devices
- [ ] Booking flow works end-to-end
- [ ] Experience families visually distinct
- [ ] Custom domains work correctly
- [ ] Real-time content updates

---

## Notes

- This is the B2C experience - serves salon customers
- Each salon gets unique design within their experience family
- Not a template system - AI customizes within theme constraints
- Performance critical for customer conversion
- Booking system is primary conversion point

---

**End of Phase 4 Documentation**

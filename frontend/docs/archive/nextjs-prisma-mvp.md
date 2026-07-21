# Next.js + Prisma MVP - Archived

**Archived Date:** July 14, 2026
**Status:** Replaced with Laravel Backend + Next.js Frontend Architecture

---

## What Was Built

### Database Schema (Prisma)
Created MVP database schema with 6 core tables:
- `Salon` - Main business entity
- `Profile` - User accounts (salon owners, staff)
- `Staff` - Staff members
- `Service` - Service menu
- `Customer` - Customer information
- `Booking` - Appointments

### Key Design Decisions
- Used `salonId` foreign keys on all business tables for future multi-tenancy
- JSON fields for flexible data (openingHours, availability)
- Proper indexing for performance
- Cascade deletes for data integrity

### Files Created
- `prisma/schema.prisma` - Complete MVP database schema
- `src/lib/db.ts` - Prisma client setup
- `src/lib/validators.ts` - Zod validation schemas
- `src/lib/utils.ts` - Utility functions (already existed)

---

## Why This Approach Was Replaced

The Next.js + Prisma approach was good for a simple MVP, but the long-term vision requires a more robust backend architecture:

### Limitations of Next.js + Prisma for This Vision

1. **Business Logic in Frontend:** With Prisma directly in Next.js, business logic lives in the frontend
2. **No API Layer:** Future mobile apps would need to replicate logic
3. **Scaling Challenges:** Harder to add Redis, queues, complex business rules
4. **API Versioning:** No built-in API versioning strategy
5. **Separation of Concerns:** Frontend and backend concerns mixed

### Long-Term Vision Requirements

The platform will eventually need:
- Salon websites
- Salon OS
- Mobile app (iOS/Android)
- Marketplace
- PostGIS "Find My Salon"
- AI features
- Immersive experiences
- Public APIs
- Third-party integrations

This requires a proper backend API that can serve multiple clients.

---

## New Architecture: Laravel + Next.js

### Separation of Concerns

**Frontend (Next.js):**
- Landing pages
- Salon websites
- Dashboard UI
- Booking UI
- Customer portal
- Animations
- Three.js (later)
- **No business logic**
- **No database queries**

**Backend (Laravel):**
- Authentication
- Authorization
- Bookings
- Customers
- Staff
- Services
- Payments
- Notifications
- Validation
- API
- File uploads
- Queues
- Emails
- **Knows everything**

### API Structure

```
/api/v1/auth
/api/v1/bookings
/api/v1/customers
/api/v1/services
/api/v1/staff
/api/v1/salons
```

### Benefits

1. **Multiple Clients:** Same API can serve Next.js, mobile apps, desktop apps
2. **API Versioning:** `/v1` allows future evolution without breaking changes
3. **Business Logic Centralized:** All rules in one place
4. **Scalability:** Easy to add Redis, queues, PostGIS later
5. **Separation:** Clean separation between presentation and business logic

---

## Preserved Work

### Database Schema
The Prisma schema design is preserved and will be converted to Laravel migrations:
- Same table structure
- Same relationships
- Same indexing strategy
- Same foreign key approach (salonId for multi-tenancy)

### Validation Schemas
Zod validators will be converted to Laravel Form Requests:
- Same validation rules
- Same error messages
- Same data structure

### Frontend Components
Next.js components will be preserved but modified to consume API:
- Same UI components
- Same design system
- Same user experience
- **Different data source** (API instead of Prisma)

---

## Migration Plan

### Phase 1: Laravel Backend Setup
1. Create Laravel project in `backend/`
2. Set up database migrations from Prisma schema
3. Create API routes with `/api/v1/` prefix
4. Implement authentication with Laravel Sanctum
5. Create controllers and services

### Phase 2: API Endpoints
1. `/api/v1/auth` - Login, register, logout
2. `/api/v1/salons` - CRUD for salons
3. `/api/v1/services` - CRUD for services
4. `/api/v1/staff` - CRUD for staff
5. `/api/v1/customers` - CRUD for customers
6. `/api/v1/bookings` - CRUD for bookings

### Phase 3: Frontend Refactor
1. Remove Prisma from Next.js
2. Create API client (Axios or fetch)
3. Update all data fetching to use API
4. Update forms to submit to API
5. Test full integration

### Phase 4: Testing
1. Test all API endpoints
2. Test frontend-backend integration
3. Test authentication flow
4. Test booking flow end-to-end

---

## Technical Notes

### Prisma Schema → Laravel Migration

**Prisma Model:**
```prisma
model Salon {
  id          String   @id @default(cuid())
  name        String
  slug        String   @unique
  // ...
}
```

**Laravel Migration:**
```php
Schema::create('salons', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    // ...
});
```

### Zod Validator → Laravel Form Request

**Zod Schema:**
```typescript
export const serviceSchema = z.object({
  name: z.string().min(1),
  price: z.number().min(0),
  duration: z.number().min(5),
});
```

**Laravel Form Request:**
```php
public function rules()
{
    return [
        'name' => 'required|string|min:1',
        'price' => 'required|numeric|min:0',
        'duration' => 'required|integer|min:5',
    ];
}
```

---

## Lessons Learned

### What Went Right
1. **Database Design:** Clean schema with proper relationships
2. **Multi-tenancy Ready:** salonId foreign keys for future scaling
3. **Validation:** Comprehensive validation with Zod
4. **Type Safety:** TypeScript throughout

### What Was Missing
1. **API Layer:** No separation between frontend and backend
2. **Business Logic:** Mixed with presentation logic
3. **Scalability:** Harder to scale to multiple clients
4. **API Versioning:** No strategy for API evolution

### Corrective Actions
1. **Separation:** Move all business logic to Laravel
2. **API-First:** Design Laravel as API from day one
3. **Versioning:** Use `/api/v1/` prefix for all endpoints
4. **Multiple Clients:** Architecture supports future mobile apps

---

## Future Considerations

When the platform scales, the Laravel backend can easily add:

1. **Redis:** Caching and session management
2. **Queues:** Background jobs for emails, notifications
3. **PostGIS:** Geospatial queries for "Find My Salon"
4. **Elasticsearch:** Advanced search capabilities
5. **WebSockets:** Real-time updates for bookings
6. **API Rate Limiting:** Protect public API endpoints
7. **OAuth2:** Third-party integrations

---

**End of Next.js + Prisma MVP Archive**

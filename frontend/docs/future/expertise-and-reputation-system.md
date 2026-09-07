# Expertise and Reputation System

## Overview

The Yo.Salon platform implements a multi-layered approach to specialist capability assessment. This system separates **self-declared expertise** from **customer evidence** while leaving room for future **platform verification**.

## Three Levels of Truth

### 1. Specialist-Declared Expertise 🧑‍🎨

**What it answers:** "What does the specialist claim they are skilled at?"

Specialists can declare their own expertise with custom names and self-selected skill levels. This is important for new specialists who may have zero reviews but legitimate expertise.

**Implementation:**
- Database: `specialist_expertise` table
- Fields: `id`, `specialist_id`, `name`, `skill_level` (enum: beginner/intermediate/advanced/expert)
- Frontend: Craft page → Expertise section
- Operations: Add, Edit, Delete via modal

**Example:**
```
Emma's Expertise:
├── Balayage — Expert
├── Skin Fading — Expert
├── Bridal Makeup — Advanced
└── Beard Sculpting — Intermediate
```

### 2. Customer Evidence (Reputation) ⭐

**What it answers:** "What do customers say about the specialist's work?"

Reviews provide evidence-based performance signals. Reviews are linked to specific services, allowing for per-service reputation tracking.

**Implementation:**
- Database: `reviews` table with `service_id` and `specialist_id`
- Aggregation: Reviews grouped by service per specialist
- Frontend: Craft page → Customer Evidence section
- Display: Service name, average rating, review count

**Example:**
```
Emma's Customer Evidence:
├── Executive Skin Fade — 4.9★ from 87 reviews
├── Beard Sculpt — 4.7★ from 42 reviews
└── Bridal Makeup — 5.0★ from 12 reviews
```

**Key Principle:** Overall reviews are NOT used to rate individual expertise. A specialist with 100 haircut reviews and 3 balayage reviews cannot be assumed to be a 4.9-rated balayage specialist. Reputation is calculated per service.

### 3. Platform/Assessment Evidence 🏆 (Future)

**What it answers:** "Is there stronger verification of this capability?"

This layer is reserved for future implementation:
- Platform-administered skill assessments
- Certification verification
- Third-party credential verification
- Practical skill demonstrations

## Architectural Separation

### Expertise vs. Services

```
Expertise (specialist_expertise)
    ↓
"What am I good at?"
    ↓
Specialist-owned, fully editable, custom names, self-selected levels

Services (specialist_service pivot)
    ↓
"What Provider services can I perform?"
    ↓
Commercial capabilities, linked to Provider's service catalog
```

### Review Flow

```
Booking
   ↓
Service (via booking_service pivot)
   ↓
Specialist (via specialist_id)
   ↓
Review (with service_id)
```

This flow enables per-service reputation aggregation.

## Combined Display Example

The Craft page displays both systems together:

```
EXPERTISE SECTION

Your Expertise (Self-Declared)
├── Balayage — Expert
├── Skin Fades — Expert
└── Bridal Makeup — Advanced

Customer Evidence (Customer-Derived)
├── Executive Skin Fade — 4.9★ from 87 reviews
├── Beard Sculpt — 4.7★ from 42 reviews
└── Bridal Makeup — 5.0★ from 12 reviews
```

## Benefits

1. **New Specialist Onboarding**: Specialists can declare expertise immediately without waiting for reviews
2. **Rich Customer Insight**: Customers see both what specialists claim and what evidence supports it
3. **Service-Specific Reputation**: Accurate ratings per service, not diluted by overall averages
4. **Future-Proof**: Architecture supports platform verification layer when ready
5. **No Conflict**: Self-declared expertise and customer evidence complement rather than contradict each other

## Database Schema

### specialist_expertise
```sql
- id (UUID, primary)
- specialist_id (UUID, foreign key → specialists)
- name (string)
- skill_level (enum: beginner, intermediate, advanced, expert)
- created_at, updated_at
```

### reviews (relevant fields)
```sql
- id (UUID, primary)
- booking_id (UUID)
- specialist_id (UUID, foreign key → specialists)
- service_id (UUID, foreign key → services)
- rating (integer, 1-5)
- comment (text, nullable)
```

## API Endpoints

### Expertise CRUD
- `POST /v1/specialist-portal/craft/expertise` - Create expertise
- `PUT /v1/specialist-portal/craft/expertise/{id}` - Update expertise
- `DELETE /v1/specialist-portal/craft/expertise/{id}` - Delete expertise

### Craft Data
- `GET /v1/specialist-portal/craft` - Returns:
  - `expertise` - Self-declared expertise array
  - `service_reputation` - Aggregated customer evidence per service
  - `services_by_provider` - Commercial services
  - `learning` - Career events (learning)
  - `products` - Career events (products)

## Future Enhancements

1. **Expertise-Service Mapping**: Allow specialists to link declared expertise to specific services they perform
2. **Reputation Badges**: Display badges for services with high ratings or significant review counts
3. **Assessment Integration**: Connect platform assessments to expertise entries
4. **Trend Analysis**: Show reputation trends over time per service
5. **Customer Filtering**: Allow customers to filter specialists by expertise level and reputation

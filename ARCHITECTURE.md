# YO.SALON — Category-Aware Architecture

## Purpose

Yo.Salon supports multiple business categories such as Hair Salons, Barbershops, Nail Studios, Spas, and Wellness Centers.

The product should adapt its **user-facing experience and terminology** to the selected business category while keeping a **unified backend domain model**.

---

## Core Principle

**Presentation terminology ≠ domain terminology**

The frontend may use category-specific language, while the backend keeps its existing domain terminology and API contracts.

Example:

```text
UI                    Backend
────────────────────────────────
Business              Salon
Professional          Specialist
Barber                Specialist
Client                Customer
Booking               Appointment
```

Do not rename backend models, database fields, routes, or API contracts merely to change UI terminology.

---

## Category Flow

```text
business_categories
        ↓
Business Category API
        ↓
CategoryContext
        ↓
User-facing UI
```

`CategoryContext` is the frontend source for category-aware terminology.

Use:

```ts
const { terminology } = useCategory();
```

rather than hardcoding category-specific labels in components.

---

## Terminology

Category terminology may provide:

* `specialist` / `specialistPlural`
* `appointment` / `appointmentPlural`
* `customer` / `customerPlural`
* `business`

Example:

```tsx
Add {terminology.specialist}
```

Category data comes from the database. **Do not create category-specific `if/else` terminology in the frontend.**

Safe default terminology exists when category information is unavailable.

---

## Registration

Registration occurs **before business category selection**.

Therefore registration is category-agnostic:

```text
Customer
Professional
Business
```

These are presentation labels only.

The existing backend journey values remain:

```text
customer
specialist
salon
```

The frontend maps its labels to those existing backend values.

**Do not change backend journey validation, roles, routes, or API contracts for this purpose.**

---

## Category Selection

Business users select their category during onboarding.

After category selection:

```text
Business
   ↓
Business Category
   ↓
CategoryContext
   ↓
Category-aware experience
```

From this point onward, user-facing terminology and experiences should reflect the selected category.

---

## Rules for Agents

### DO

* Use `CategoryContext` for category-aware UI terminology.
* Use database category metadata rather than frontend category lists.
* Keep category-specific configuration data-driven.
* Preserve the existing backend domain model.
* Provide sensible fallbacks when category data is unavailable.

### DON'T

* Hardcode `if category === "barbershop"` terminology logic.
* Rename backend `Salon`, `Specialist`, `Appointment`, etc. solely for UI language.
* Change registration API journey values.
* Duplicate category data in frontend constants.
* Assume every business is a salon in user-facing copy.

---

## Key Sources

```text
frontend/src/contexts/CategoryContext.tsx
backend/app/Models/BusinessCategory.php
backend/database/seeders/BusinessCategorySeeder.php
frontend/src/app/register/page.tsx
```

**When implementing a new category-aware feature, first check `CategoryContext` and `BusinessCategory` before introducing new terminology or category logic.**

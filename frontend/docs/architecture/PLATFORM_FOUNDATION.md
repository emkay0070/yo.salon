# Platform Foundation Architecture

## Overview

The Platform Foundation defines the core bounded contexts that form the language of yo.salon. These domains are not about building all features—they're about establishing the fundamental entities, relationships, events, and extension points that future capabilities will rely on.

## Core Principle

> **Define the language of the platform before building the features.**

Instead of carving new domains into a mature system, establish the foundational domains early. Later features extend existing domains rather than creating new ones.

## Platform Domains

For Version 1.0 platform foundation, these bounded contexts should exist:

```
Identity
Provider
Customer
Booking
Availability
Payments
Finance
Catalog
Inventory
Procurement
Analytics
Intelligence
```

**Note:** Some domains might only have 2-3 tables today. That's fine. You're defining the language, not building all features.

## Domain Responsibilities

### 1. Identity

**Purpose:** Authentication, authorization, user management.

**Core Entities:**
- User
- Role
- Permission
- Session

**Future Extensions:**
- OAuth integration
- SSO
- Multi-factor authentication

### 2. Provider

**Purpose:** Business entity management (Salons, Specialists, Suppliers).

**Core Entities:**
- Provider (polymorphic: Salon, Specialist, Supplier)
- Provider Profile
- Provider Settings

**Key Design:** Supplier is a Provider with type: supplier. No new architecture.

**Future Extensions:**
- Franchise management
- Chain management
- Provider marketplace

### 3. Customer

**Purpose:** Customer relationship management.

**Core Entities:**
- Customer
- Customer Profile
- Customer Preferences

**Future Extensions:**
- Loyalty programs
- Customer segments
- Marketing campaigns

### 4. Booking

**Purpose:** Appointment scheduling and management.

**Core Entities:**
- Booking
- Booking Line Item
- Booking Status

**Future Extensions:**
- Recurring bookings
- Waitlists
- Group bookings

### 5. Availability

**Purpose:** Time slot and resource availability.

**Core Entities:**
- Availability Slot
- Specialist Assignment
- Availability Status

**Future Extensions:**
- Complex scheduling rules
- Resource scheduling
- Calendar integration

### 6. Payments

**Purpose:** Payment processing and transaction management.

**Core Entities:**
- Payment Request
- Transaction
- Payment Method

**Future Extensions:**
- Multiple payment providers
- Subscription billing
- Refund management

### 7. Finance

**Purpose:** Financial facts, obligations, and entitlements.

**Core Entities:**
- Financial Transaction
- Journal Entry
- Ledger Account
- Ledger Entry
- Revenue Distribution Policy
- Settlement
- Payout

**Key Design:** Database is the only source of truth. No hardcoding.

**Future Extensions:**
- Accounting periods
- Multi-currency
- Tax management

### 8. Catalog

**Purpose:** Product information and taxonomy.

**Core Entities:**
- Product (polymorphic owner)
- Brand
- Category
- SKU
- Product Attribute

**Key Design:** Catalog knows products. Inventory knows stock.

**Future Extensions:**
- Product relationships
- Product reviews
- Multi-language support

### 9. Inventory

**Purpose:** Stock tracking and movement.

**Core Entities:**
- Stock Item
- Stock Movement
- Batch
- Stock Reservation
- Stock Adjustment

**Key Design:** Inventory knows stock. Catalog knows products.

**Future Extensions:**
- Multi-warehouse
- Serial number tracking
- Inventory valuation

### 10. Procurement

**Purpose:** Supplier purchasing and receiving.

**Core Entities:**
- Supplier (Provider type)
- Purchase Order
- Receiving
- Invoice
- Return

**Key Design:** Supplier is a Provider with type: supplier.

**Future Extensions:**
- Supplier portal
- Automated reordering
- Supplier performance

### 11. Analytics

**Purpose:** Data aggregation and reporting.

**Core Entities:**
- Report
- Dashboard
- Metric
- Data Source

**Future Extensions:**
- Real-time analytics
- Predictive analytics
- Custom report builder

### 12. Intelligence

**Purpose:** AI and machine learning capabilities.

**Core Entities:**
- AI Model
- Recommendation
- Prediction
- Training Data

**Future Extensions:**
- Personalization
- Demand forecasting
- Dynamic pricing

## Domain Communication

### Event-Driven Architecture

Domains communicate via events, not direct method calls.

**Example Flow:**

```
Booking Domain
    ↓ (BookingCompleted event)
Payments Domain
    ↓ (PaymentCaptured event)
Finance Domain
    ↓ (RevenueDistributed event)
Analytics Domain
```

**Rule:** Never let one domain call another domain's service directly. Use events.

### Polymorphic Relationships

Where possible, use polymorphic relationships to avoid entity-specific logic.

**Example:**
- Finance doesn't know about Specialist, Supplier, etc.
- Finance knows: `owner_type`, `owner_id`
- Any entity can participate in Finance

### Database as Source of Truth

All configuration, rules, and policies stored in database.

**Example:**
- Revenue Distribution Policies in database
- Fee structures in database
- Availability rules in database

**No hardcoding.**

## Design Principles

### 1. Bounded Contexts

Each domain has one clear responsibility. Domains don't overlap.

### 2. Event-Driven Boundaries

Domains publish events. Other domains listen. No direct calls.

### 3. Polymorphic Design

Where possible, use polymorphic relationships to avoid coupling to specific entity types.

### 4. DB-Driven Configuration

No hardcoded rules. All policies in database.

### 5. Immutability

Financial data never updated. Corrections via reversing entries.

### 6. Separation of Concerns

Catalog knows products. Inventory knows stock. Procurement knows purchasing.

## Implementation Strategy

### Phase 1: Core Domains (Current)

**Focus:** Identity, Provider, Customer, Booking, Availability, Payments, Finance

**Status:** Mostly implemented. Finance Domain recently refactored.

### Phase 2: Product Foundation

**Focus:** Catalog, Inventory, Procurement

**Status:** Architecture documented. Implementation pending.

**Approach:** Create core models and relationships. Leave advanced workflows dormant.

### Phase 3: Intelligence Foundation

**Focus:** Analytics, Intelligence

**Status:** Future work.

**Approach:** Define data models and event contracts. Build actual ML capabilities when business needs them.

## Migration Strategy

### Incremental Migrations

Each domain has its own migration file. Migrations are independent and can be run in any order.

**Example:**
- `2026_08_06_130000_update_specialist_model_for_pro.php`
- `2026_08_06_140000_create_finance_domain_tables.php`
- `2026_08_06_150000_create_catalog_domain_tables.php` (pending)
- `2026_08_06_160000_create_inventory_domain_tables.php` (pending)
- `2026_08_06_170000_create_procurement_domain_tables.php` (pending)

### Backward Compatibility

Migrations are designed to be backward compatible where possible. Existing functionality continues to work while new domains are added.

## Testing Strategy

### Domain Isolation

Each domain should be testable in isolation. Mock events from other domains.

### Integration Testing

Test event flows between domains to ensure proper integration.

### Contract Testing

Define event contracts and test that domains adhere to them.

## Documentation Strategy

### Domain Documentation

Each domain has its own architecture document:
- `FINANCE_DOMAIN.md`
- `CATALOG_DOMAIN.md`
- `INVENTORY_DOMAIN.md`
- `PROCUREMENT_DOMAIN.md`

### Cross-Domain Documentation

This document (`PLATFORM_FOUNDATION.md`) provides the high-level view.

### API Documentation

Each domain's API endpoints documented in OpenAPI/Swagger.

## Conclusion

The Platform Foundation is not about building all features. It's about defining the language of the platform.

By establishing these domains now, future features extend existing domains rather than creating new ones. This prevents architectural drift and ensures consistency across the platform.

**The Platform Foundation is the vocabulary of yo.salon.**

## Caution

**Don't populate these domains with speculative features.**

Create the core entities, relationships, events, and extension points that you know are fundamental. Leave the advanced workflows dormant until the business actually needs them.

This approach gives you a professional architecture without falling into the trap of building an entire ERP before onboarding your first salons.

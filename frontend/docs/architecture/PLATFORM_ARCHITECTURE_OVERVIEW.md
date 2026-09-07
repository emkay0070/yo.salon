# Platform Foundation Summary

## Overview

This document summarizes the foundational domains established for the yo.salon platform as of August 6, 2026. These domains form the language and infrastructure of the platform, designed to support 10+ years of growth without requiring major architectural rewrites.

## Completed Domains

### 1. Finance Domain ✅

**Status:** Complete and solidified

**Purpose:** Models financial facts, obligations, and entitlements across all economic actors in the platform.

**Core Components:**
- **FinancialTransaction** - Wrapper for reconciliation, groups related journals
- **JournalEntry** - Groups related ledger movements, provides audit trail
- **LedgerAccount** - Polymorphic account system (no entity type logic)
- **LedgerEntry** - Individual financial movements (immutable)
- **RevenueDistributionPolicy** - DB-driven revenue rules
- **RevenueDistributionEngine** - Executes revenue distribution using policies
- **Settlement** - Represents obligations (polymorphic payable/recipient)
- **Payout** - Represents actual money transfers

**Key Design Principles:**
- **Immutability:** Ledger entries and journals never updated. Corrections via reversing entries.
- **Event-Driven:** Domains communicate via events, not direct calls.
- **Polymorphic Design:** Finance doesn't know entity types (Specialist, Supplier, etc.).
- **DB-Driven Configuration:** All policies in database, no hardcoding.
- **Separation of Concerns:** Finance models obligations, Payments executes flows.

**Events:**
- Input: `PaymentConfirmed` (from Payments Domain)
- Output: `PaymentCaptured`, `RevenueDistributed`, `FinancialTransactionPosted`, `JournalPosted`, `LedgerEntryCreated`, `SettlementClosed`, `JournalVoided`, `FinancialTransactionVoided`

**Testing:**
- Unit tests for journal balance validation
- Unit tests for ledger entry immutability
- Unit tests for journal voiding and reversal
- Unit tests for settlement lifecycle

**Documentation:**
- `FINANCE_DOMAIN.md` - Complete architecture documentation
- `FINANCE_FLOW.md` - Flow diagrams and event sequences

**Migration:**
- `2026_08_06_140000_create_finance_domain_tables.php`

### 2. Commerce/Catalog Domain ✅

**Status:** Foundation established

**Purpose:** Canonical product database. Knows product details but never knows stock levels.

**Core Components:**
- **Brand** - Product brands (independent from suppliers)
- **Category** - Hierarchical product categorization
- **Product** - Polymorphic ownership (Platform, Supplier, Salon)
- **ProductVariant** - SKU system for product variants
- **ProductImage** - Polymorphic images for products/variants
- **ProductAttribute** - Flexible attribute system

**Key Design Principles:**
- **Separation of Concerns:** Catalog knows products. Inventory knows stock.
- **Polymorphic Ownership:** Products can be owned by Platform, Supplier, or Salon.
- **Flexible Attributes:** JSON-based system adapts to different product types.
- **Future-Proofing:** Fields like `is_trackable`, `requires_batch_tracking` establish language for Inventory integration.
- **Brand ≠ Supplier:** Brands are independent entities distributed by suppliers.

**Product Ownership Examples:**
- Platform (global catalog) - Products available to all salons
- Supplier (supplier products) - Products uploaded by suppliers
- Salon (private formulas) - Products specific to a salon

**Documentation:**
- `COMMERCE_CATALOG.md` - Complete architecture documentation

**Migration:**
- `2026_08_06_180000_create_commerce_catalog_tables.php`

### 3. Provider Domain Enhancement ✅

**Status:** Enhanced with Supplier type

**Purpose:** Business entity management (Salons, Specialists, Suppliers).

**Enhancement:**
- Added `TYPE_SUPPLIER` constant to Provider model
- Suppliers are now first-class business entities
- Reuses existing Provider infrastructure (no new architecture)

**Provider Types:**
- `TYPE_SALON` - Salon
- `TYPE_SPECIALIST` - Specialist
- `TYPE_SUPPLIER` - Supplier (NEW)

## Existing Domains (Previously Established)

### 4. Identity Domain ✅

**Purpose:** Authentication, authorization, user management.

**Core Entities:**
- User
- Role
- Permission
- Session

### 5. Customer Domain ✅

**Purpose:** Customer relationship management.

**Core Entities:**
- Customer
- Customer Profile
- Customer Preferences

### 6. Booking Domain ✅

**Purpose:** Appointment scheduling and management.

**Core Entities:**
- Booking
- Booking Line Item
- Booking Status

### 7. Availability Domain ✅

**Purpose:** Time slot and resource availability.

**Core Entities:**
- Availability Slot
- Specialist Assignment
- Availability Status

### 8. Payments Domain ✅

**Purpose:** Payment processing and transaction management.

**Core Entities:**
- Payment Request
- Transaction
- Payment Method

**Integration:** Publishes `PaymentConfirmed` event to trigger Finance Domain.

## Domain Communication Architecture

### Event-Driven Boundaries

```
Bookings Domain
    ↓ (BookingCompleted event)
Payments Domain
    ↓ (PaymentConfirmed event)
Finance Domain
    ↓ (RevenueDistributed event)
Analytics Domain
```

**Rule:** Never let one domain call another domain's service directly. Use events.

### Polymorphic Relationships

Where possible, use polymorphic relationships to avoid entity-specific logic.

**Examples:**
- Finance: `owner_type` / `owner_id` for ledger accounts
- Catalog: `owner_type` / `owner_id` for products
- Finance: `payable_type` / `payable_id` for settlements

### Database as Source of Truth

All configuration, rules, and policies stored in database.

**Examples:**
- Revenue Distribution Policies in database
- Product ownership in database
- No hardcoded rules

## Design Principles Across All Domains

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

- Catalog knows products. Inventory knows stock. Procurement knows purchasing.
- Finance models obligations. Payments executes flows.
- Brand ≠ Supplier separation.

## Future Domains (Not Yet Implemented)

### Inventory Domain

**Purpose:** Stock tracking and movement.

**Status:** Future work - implement when business needs justify it.

**Planned Components:**
- Stock Item
- Stock Movement
- Batch
- Stock Reservation
- Stock Adjustment

**Integration:** Will reference SKUs from Catalog domain.

### Procurement Domain

**Purpose:** Supplier purchasing and receiving.

**Status:** Future work - implement when business needs justify it.

**Planned Components:**
- Supplier (Provider type: supplier - already established)
- Purchase Order
- Receiving
- Invoice
- Return

**Integration:** Will reference Products from Catalog domain.

### Analytics Domain

**Purpose:** Data aggregation and reporting.

**Status:** Future work.

**Planned Components:**
- Report
- Dashboard
- Metric
- Data Source

### Intelligence Domain

**Purpose:** AI and machine learning capabilities.

**Status:** Future work.

**Planned Components:**
- AI Model
- Recommendation
- Prediction
- Training Data

**Integration:** Will recommend Products from Catalog domain.

## Migration Summary

### Completed Migrations

1. `2026_08_06_130000_update_specialist_model_for_pro.php` - Specialist model updates
2. `2026_08_06_140000_create_finance_domain_tables.php` - Finance domain tables
3. `2026_08_06_180000_create_commerce_catalog_tables.php` - Catalog domain tables

### Migration Strategy

- Each domain has its own migration file
- Migrations are independent and can be run in any order
- Migrations are designed to be backward compatible where possible

## Code Structure

### Finance Domain

```
backend/app/Domain/Finance/
├── FinancialTransaction.php
├── Events/
│   ├── PaymentCaptured.php
│   ├── RevenueDistributed.php
│   ├── SettlementCreated.php
│   ├── FinancialTransactionPosted.php
│   ├── FinancialTransactionVoided.php
│   ├── JournalPosted.php
│   ├── JournalVoided.php
│   ├── LedgerEntryCreated.php
│   └── SettlementClosed.php
├── Journal/
│   └── JournalEntry.php
├── Ledger/
│   ├── LedgerAccount.php
│   └── LedgerEntry.php
├── RevenueDistribution/
│   ├── RevenueDistributionEngine.php
│   └── RevenueDistributionPolicy.php
├── Settlement/
│   └── Settlement.php
└── Payout/
    └── Payout.php
```

### Commerce/Catalog Domain

```
backend/app/Domain/Commerce/Catalog/
├── Brand.php
├── Category.php
├── Product.php
├── ProductVariant.php
├── ProductImage.php
└── ProductAttribute.php
```

## Testing Strategy

### Unit Tests

- Finance Domain: Journal balance validation, immutability, voiding, settlement lifecycle
- Catalog Domain: Polymorphic ownership, category hierarchy, attribute flexibility

### Integration Tests

- Payment → Finance flow
- Revenue distribution → Settlements
- Event propagation

### Contract Tests

- Event structure validation
- API contract validation
- Database schema validation

## Documentation

### Architecture Documents

- `FINANCE_DOMAIN.md` - Finance Domain architecture
- `FINANCE_FLOW.md` - Finance Domain flow diagrams
- `CATALOG_DOMAIN.md` - Catalog Domain architecture
- `INVENTORY_DOMAIN.md` - Inventory Domain architecture (future)
- `PROCUREMENT_DOMAIN.md` - Procurement Domain architecture (future)
- `PLATFORM_FOUNDATION.md` - Platform foundation overview
- `PLATFORM_FOUNDATION_SUMMARY.md` - This document

### Inline Comments

All models include comprehensive inline comments explaining:
- Purpose and principles
- Method functionality
- Design decisions
- Integration points
- Future-proofing considerations

## Next Steps (Recommended Roadmap)

### Immediate Priority

**Return to product delivery and onboarding.**

The platform now has two fundamental pillars:
- **Finance Domain** - Financial facts, obligations, settlements
- **Commerce/Catalog Domain** - Product vocabulary, polymorphic ownership

These foundations are sufficient to support the initial salon onboarding and product delivery.

### Future Work (When Business Needs Justify)

1. **Inventory Domain** - Stock tracking (when salons need inventory management)
2. **Procurement Domain** - Supplier purchasing (when supplier workflows are needed)
3. **Retail Domain** - Product sales (when retail functionality is needed)
4. **Analytics Domain** - Advanced reporting (when analytics needs grow)
5. **Intelligence Domain** - AI recommendations (when AI capabilities are needed)

## Conclusion

The platform foundation is intentionally designed. Future capabilities will extend these foundations rather than requiring architectural rewrites.

**Key Achievements:**

1. **Finance Domain** - Rock solid with immutability, events, tests, and documentation
2. **Commerce/Catalog Domain** - Foundation established with polymorphic ownership
3. **Provider Domain** - Enhanced with Supplier type
4. **Event-Driven Architecture** - Domains communicate via events
5. **Polymorphic Design** - Flexible, entity-agnostic relationships
6. **DB-Driven Configuration** - No hardcoded rules
7. **Comprehensive Documentation** - Architecture docs, flow diagrams, inline comments

**The platform is no longer software written for salons. It's software written for commerce.**

This distinction matters because tomorrow you can introduce:
- Suppliers
- Beauty schools
- Franchise networks
- Equipment rentals
- Retail product sales
- Membership billing
- Marketplace services

…and they can all participate in the same financial and product models without requiring architectural changes.

**The foundation is stable. The language is defined. The platform is ready.**

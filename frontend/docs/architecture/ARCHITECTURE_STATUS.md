# Architecture Status

## Platform Milestones

### Milestone 1 — Core Platform ✅ Complete
- Identity
- Provider
- Customer
- Specialist
- Booking
- Payments
- Finance
- Commerce Foundation

### Milestone 2 — Operational Excellence 🔄 In Progress
- Integration
- Observability
- Performance
- Reliability
- Analytics
- Notifications
- UX Polish
- Onboarding

### Milestone 3 — Business Expansion ⏸️ Planned
- Inventory
- Procurement
- Retail
- Supplier Portal
- Driven by actual customer demand

## Domain Status Overview

| Domain       | Status  | Notes                   |
| ------------ | ------- | ----------------------- |
| Identity     | Stable  | Authentication complete |
| Customer     | Stable  | Profile complete        |
| Provider     | Stable  | Supplier type added     |
| Specialist   | Stable  | Feature-complete V1     |
| Booking      | Stable  | Policy-driven           |
| Payments     | Stable  | Direct payment model    |
| Finance      | Stable  | Event-driven ledger     |
| Commerce     | Stable  | Catalog foundation      |
| Inventory    | Planned | Depends on Commerce     |
| Procurement  | Planned | Depends on Supplier     |
| Intelligence | Active  | Expanding               |

## Status Definitions

### Stable
- Feature-complete for current version
- May evolve only through:
  - Integration work
  - Bug fixes
  - Performance improvements
  - Security improvements
  - New business requirements introduced by another domain
- No new features unless another domain requires them

### Planned
- Designed but not implemented
- Will be built when business usage justifies
- Dependencies clearly identified

### Active
- Currently under development
- New features being added

## Quality Gates for Stable Domains

A stable domain requires all of the following before it's considered complete:

- ✅ Architecture documented
- ✅ Database migrations finalized
- ✅ Domain events documented (see DOMAIN_EVENTS.md)
- ✅ Unit tests
- ✅ Integration tests
- ✅ API documented
- ✅ Permissions verified
- ✅ Audit logging where appropriate
- ✅ Monitoring/logging hooks
- ✅ No TODOs in production code

## Domain Details

### Identity ✅ Stable
- Authentication system
- User management
- Role-based access control
- **Next:** Only bug fixes or integration work

### Customer ✅ Stable
- Customer profiles
- Customer relationships
- Loyalty system
- **Next:** Only bug fixes or integration work

### Provider ✅ Stable
- Provider model
- Salon, Specialist, Supplier types
- Provider profiles
- **Next:** Only bug fixes or integration work

### Specialist ✅ Stable
- Identity
- Public profile
- Career history
- Portfolio
- Verification
- Assignments
- Customer relationships
- Booking participation
- Finance integration
- Specialist Portal
- Intelligence hooks
- Workspace
- **Next:** Only bug fixes or integration work

### Booking ✅ Stable
- Booking creation
- Booking management
- Policy-driven rules
- Availability integration
- **Next:** Only bug fixes or integration work

### Payments ✅ Stable
- Payment processing
- Direct payment model
- Payment confirmation events
- **Next:** Only bug fixes or integration work

### Finance ✅ Stable
- FinancialTransaction wrapper
- JournalEntry with immutability
- LedgerEntry with immutability
- RevenueDistributionEngine
- Settlement lifecycle
- Payout system
- Granular finance events
- Unit tests
- **Next:** Only bug fixes or integration work

### Commerce ✅ Stable
- Brand model
- Category model (hierarchical)
- Product model (polymorphic ownership)
- ProductVariant (SKU system)
- ProductImage (polymorphic)
- ProductAttribute (flexible)
- **Next:** Only bug fixes or integration work

### Inventory Planned
- Stock tracking
- Stock movement
- Batch tracking
- Stock reservation
- **Dependencies:** Commerce (Catalog)
- **When:** When salons need inventory management

### Procurement Planned
- Supplier purchasing
- Purchase orders
- Receiving
- Invoices
- **Dependencies:** Provider (Supplier type), Commerce (Catalog)
- **When:** When supplier workflows are needed

### Intelligence Active
- AI recommendations
- Analytics
- Predictive features
- **Next:** Expanding capabilities

## Integration Priorities

### Booking → Finance
- Verify every completed booking produces correct financial transaction
- Ensure revenue distribution works end-to-end

### Finance → Analytics
- Enable salon analytics to use Finance events
- Replace direct queries with event-based analytics

### Finance → Intelligence
- Enable AI to answer revenue questions without querying booking tables
- Use Finance events for specialist performance analysis

### Finance → Notifications
- Notify specialists when settlements are closed
- Notify salons about pending payouts

### Finance → Dashboard
- Show today's revenue using Finance data
- Show outstanding specialist settlements
- Show pending payouts
- Replace calculations with Finance queries

## Development Guidelines

### Stable Domains
- **No new features** unless another domain requires them
- Only bug fixes or integration work
- Maintain API contracts
- Keep documentation updated

### Planned Domains
- Build only when business usage justifies
- Respect dependencies
- Follow established patterns from stable domains

### Active Domains
- Continue development
- Integrate with stable domains
- Follow architecture principles

## Architecture Principles

### 1. Domain Boundaries
- Each domain has one clear responsibility
- Domains communicate via events, not direct calls
- Polymorphic relationships where possible

### 2. Event-Driven
- Domains publish events
- Other domains listen
- No direct method calls between domains

### 3. DB-Driven Configuration
- All policies in database
- No hardcoded rules
- Configurable per context

### 4. Immutability
- Financial data never updated
- Corrections via reversing entries
- Audit trail preserved

### 5. Separation of Concerns
- Catalog knows products. Inventory knows stock.
- Finance models obligations. Payments executes flows.
- Brand ≠ Supplier separation

## Next Phase Focus

**Current Phase:** Product delivery and salon onboarding

**Focus Areas:**
1. Integration work between stable domains
2. Real salon workflows
3. Onboarding experience
4. Customer-facing features

**Avoid:**
- Building new major domains
- Adding features to stable domains
- Premature optimization

## Version History

### V1 Foundation (August 2026)
- Identity, Customer, Provider, Specialist domains
- Booking, Payments, Finance domains
- Commerce/Catalog foundation
- Specialist declared feature-complete

### Future Versions
- Inventory domain (when needed)
- Procurement domain (when needed)
- Retail domain (when needed)

## Notes

This document is maintained to track domain stability across the platform. It tells:
- Which domains are **open for development** (Active)
- Which domains are **stable** (only bug fixes/integration)
- Which domains are **planned** (not yet implemented)

This prevents continuous feature creep and ensures the platform stays healthy and maintainable.

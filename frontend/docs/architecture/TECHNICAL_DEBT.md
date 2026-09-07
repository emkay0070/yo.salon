# Technical Debt

This document tracks intentional technical decisions made during development. These are not bugs—they are conscious trade-offs made based on current constraints.

Each entry includes the decision, the reasoning, and a migration path for when it should be replaced.

---

## Queue System

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| Polling instead of queue workers | Budget constraints - no funding for always-on workers | Stable recurring revenue or when active salons > 10 | Implement Laravel Queues + Redis, migrate polling jobs to queue workers |

**Current Implementation:**
- Scheduled tasks poll for background work
- No dedicated queue workers
- Simple, cost-effective for early stage

**Migration Plan:**
1. Set up Redis
2. Configure Laravel Queues
3. Convert polling jobs to queue jobs
4. Deploy queue workers
5. Monitor and scale

---

## Payments

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| Manual subscription activation | No merchant account set up | Business account + payment gateway approved | Integrate automated subscription management with payment gateway |

**Current Implementation:**
- Manual activation of Pro subscriptions
- No automated recurring billing
- Direct salon payments to specialists

**Migration Plan:**
1. Set up merchant account
2. Integrate payment gateway subscription API
3. Migrate existing subscriptions
4. Implement automated billing
5. Add subscription management UI

---

## Payouts

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| Manual specialist payouts | Direct salon payments to specialists | Marketplace payout system needed | Implement automated marketplace payouts via payment gateway |

**Current Implementation:**
- Salons pay specialists directly
- No platform-mediated payouts
- Manual settlement tracking

**Migration Plan:**
1. Integrate marketplace payout API
2. Configure payout schedules
3. Migrate to platform-mediated payouts
4. Add payout management UI
5. Implement payout reconciliation

---

## Inventory

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| No Inventory workflows | Waiting for real usage to validate needs | Multiple salons request stock management | Implement Inventory Domain with Catalog integration |

**Current Implementation:**
- Catalog Foundation exists (Products, SKUs)
- No stock tracking
- No inventory movements
- No batch/lot tracking

**Migration Plan:**
1. Design Inventory Domain architecture
2. Create Inventory models (StockItem, StockMovement, Batch)
3. Integrate with Catalog SKUs
4. Implement inventory tracking
5. Add inventory management UI

---

## Procurement

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| No Procurement workflows | Supplier type exists but no supplier workflows | Salons request supplier purchasing | Implement Procurement Domain with Supplier integration |

**Current Implementation:**
- Supplier type added to Provider model
- No purchase orders
- No receiving workflow
- No supplier invoices

**Migration Plan:**
1. Design Procurement Domain architecture
2. Create Procurement models (PurchaseOrder, Receiving, Invoice)
3. Integrate with Supplier model
4. Implement procurement workflow
5. Add procurement management UI

---

## Retail

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| No Retail workflows | Catalog foundation exists but no retail sales | Salons request retail product sales | Implement Retail Domain with Catalog integration |

**Current Implementation:**
- Catalog Foundation exists (Products, SKUs)
- No retail sales
- No product checkout
- No retail inventory

**Migration Plan:**
1. Design Retail Domain architecture
2. Create Retail models (RetailSale, RetailLineItem)
3. Integrate with Catalog Products
4. Implement retail checkout
5. Add retail management UI

---

## Monitoring

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| Basic logging only | Budget constraints - no paid monitoring service | Revenue supports monitoring budget | Implement comprehensive observability (APM, logs, metrics) |

**Current Implementation:**
- Basic application logging
- No centralized log aggregation
- No APM (Application Performance Monitoring)
- No metrics dashboard

**Migration Plan:**
1. Select APM solution (e.g., Datadog, New Relic)
2. Configure log aggregation
3. Set up metrics collection
4. Create monitoring dashboards
5. Configure alerts

---

## Email/SMS

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| Basic email only | Budget constraints - no SMS service | Revenue supports communication budget | Implement multi-channel notifications (Email, SMS, WhatsApp) |

**Current Implementation:**
- Basic email notifications
- No SMS notifications
- No WhatsApp integration
- No notification preferences

**Migration Plan:**
1. Integrate SMS gateway
2. Integrate WhatsApp Business API
3. Implement notification preferences
4. Add notification history
5. Create notification management UI

---

## Testing

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| Unit tests only for Finance | Time constraints - focused on core domain | Before major feature additions | Add integration tests and E2E tests for all domains |

**Current Implementation:**
- Unit tests for Finance Domain
- Limited integration tests
- No E2E tests
- Manual testing for other domains

**Migration Plan:**
1. Add integration tests for Booking
2. Add integration tests for Payments
3. Add integration tests for Commerce
4. Implement E2E test suite
5. Set up CI/CD test automation

---

## Documentation

| Decision | Why | Replace When | Migration Path |
|----------|-----|--------------|----------------|
| Architecture docs only | Time constraints - focused on core architecture | Before team expansion | Add API documentation, user guides, runbooks |

**Current Implementation:**
- Architecture documentation complete
- Domain events documented
- No API documentation
- No user guides
- No operational runbooks

**Migration Plan:**
1. Document API endpoints
2. Create user onboarding guides
3. Create operational runbooks
4. Document troubleshooting procedures
5. Add video tutorials

---

## Notes

This document is not a list of problems to fix immediately. It's a record of conscious decisions made under current constraints.

**Principles:**
1. Document trade-offs when making them
2. Replace when business value justifies the cost
3. Don't optimize prematurely
4. Ship first, improve later

**Review Schedule:**
- Monthly review of technical debt
- Prioritize based on business impact
- Replace when revenue supports the investment

This ensures future-you understands why decisions were made and when they should be revisited.

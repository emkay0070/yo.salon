# Finance Domain Architecture

## Overview

The Finance Domain is a foundational subsystem that models financial facts, obligations, and entitlements across all economic actors in the platform. It is designed to support the platform for 10+ years without requiring major architectural changes.

## Core Principle

> **The database is the only source of truth. No hardcoding.**

All financial rules, policies, and configurations are stored in the database. The Finance Domain executes policies; it does not define them.

## Domain Boundaries

```
Bookings
    │
    ▼
Payments
    │
    ▼
Finance
    │
    ├── Journal
    ├── Ledger
    ├── Revenue Distribution
    ├── Settlement
    └── Payout
```

Each domain has one responsibility. Domains communicate via events, not direct method calls.

## Finance Domain Components

### 1. Financial Transaction (Planned)

A wrapper that groups related financial operations for reconciliation.

```
Financial Transaction: Haircut #8247
    │
    ▼
Journal: Payment
    │
    ▼
Ledger Entries
```

**Purpose:** Makes reconciliation easier. One transaction = one business event.

### 2. Journal Entry

Groups related ledger movements together.

**Example:**
```
Journal #JNL-ABC123
├── Debit: Customer Account 50,000
└── Credit: Salon Account 50,000
```

**Purpose:** Audit trail. Five years from now, "Why did Specialist John receive UGX 85,000 on August 6?" → Open one journal, see everything.

**Rules:**
- Never edit journals
- Void if necessary
- Create correction journals
- Never overwrite history

### 3. Ledger Account

Pure polymorphic account system. The Finance Domain doesn't care about entity types.

**Owner can be any entity:**
- Customer
- Provider (Salon, Specialist, Supplier, etc.)
- Platform
- Employee
- Whatever future entities are added

**No entity type logic in Finance Domain. Pure polymorphic relationships.**

**Account Types:**
- `revenue` - Money earned
- `receivable` - Money owed to this entity
- `payable` - Money this entity owes
- `asset` - Things owned (AI credits, gift card liability, etc.)
- `liability` - Obligations (tax liability, refund reserve, etc.)

### 4. Ledger Entry

Represents a single financial movement within a journal.

**Rules:**
- **Never update ledger entries. Ever.**
- Not balances, not amounts, not references
- If something is wrong, create reversing entries
- Immutable - this is how accounting systems survive audits

### 5. Revenue Distribution Policy

DB-driven rules for how revenue is distributed. Businesses don't think in "commissions" - they think in:
- Employment contracts
- Chair rental
- Revenue sharing
- Franchise fees
- Supplier margins
- Promotions
- Referral bonuses

**Policy Structure:**
```json
{
  "rules": [
    {
      "party": "specialist",
      "owner_type": "App\\Models\\Specialist",
      "type": "percentage",
      "percentage": 40
    },
    {
      "party": "platform",
      "owner_type": "App\\Models\\Platform",
      "type": "fixed",
      "fixed": 5000
    }
  ]
}
```

**Future Policy Capabilities:**
- Tiered rules: "First 100,000 this month → 100% Salon, Remaining → 60/40"
- Caps: "Platform fee maximum UGX 10,000"
- Fixed amounts: "Chair rental fixed 500,000/month"
- Conditional: "Commission applies only after booking completed"

**Policy Selection:**
1. Context-specific policy (e.g., salon-specific)
2. Fallback to default policy
3. Priority-based selection
4. Time-based rules (effective periods, promotions)

### 6. Settlement

Represents an obligation from one entity to another.

**Polymorphic:**
- `payable_type` / `payable_id` - Who owes
- `recipient_type` / `recipient_id` - Who receives
- `reference_type` / `reference_id` - What caused this

**States:**
- `pending` - Obligation created, not yet paid
- `partially_paid` - Some amount paid (future)
- `paid` - Fully settled
- `cancelled` - Obligation cancelled (future)
- `disputed` - Under dispute (future)

**Examples:**
- Salon owes Specialist
- Salon owes Supplier
- Platform owes Salon
- Supplier owes Platform

### 7. Payout

Represents actual money transfer (settlement execution).

**Today:**
- Manual MTN
- Cash
- Bank transfer

**Tomorrow:**
- Flutterwave
- Pesapal
- Stripe
- Bank API

**No change in architecture.** Payout is just execution of a settlement.

## Event-Driven Architecture

### Finance Domain Events

**Input Events (from Payments Domain):**
- `PaymentCaptured` - Payment confirmed

**Output Events (Finance Domain publishes):**
- `RevenueDistributed` - Revenue split calculated
- `SettlementCreated` - New obligation created
- `JournalPosted` - Journal entry posted (future)
- `LedgerEntryCreated` - Ledger entry created (future)
- `SettlementClosed` - Settlement fully paid (future)
- `PayoutFailed` - Payout execution failed (future)
- `PayoutCompleted` - Payout execution successful (future)
- `RefundProcessed` - Refund processed (future)

**Rule:** Other domains listen to Finance events. They never call Finance directly.

## Platform Sub-Accounts

The platform itself should have multiple accounts for different economic boundaries:

```
yo.salon
├── AI Credits
├── Subscription Revenue
├── Marketplace Fees
├── Advertising Revenue
├── Gift Card Liability
├── Loyalty Liability
├── Promotional Credits
├── Internal Operating Budget
├── Refund Reserve
└── Tax Liability
```

These should not be one balance. They are different accounts.

## Future Roadmap

### Near-Term (Finance Domain Polish)

1. **Financial Transaction Wrapper**
   - Add `FinancialTransaction` model around Journal Entries
   - Group related journals by business event
   - Improve reconciliation

2. **Enhanced Settlement States**
   - Add `partially_paid`, `cancelled`, `disputed`
   - Support partial settlement execution

3. **Granular Events**
   - Add `JournalPosted`, `LedgerEntryCreated`
   - Enable detailed analytics

4. **Immutability Guards**
   - Add database constraints to prevent ledger entry updates
   - Add application-level validation

### Medium-Term (Accounting Periods)

```
Financial Period: 2026-08
├── Status: Open/Closed/Locked
├── Started: 2026-08-01
├── Ended: 2026-08-31
└── Locked: 2026-09-15
```

**Purpose:** Once accountants close August, nobody should accidentally modify August's books.

### Long-Term (Additional Domains)

#### Catalog Domain

**Purpose:** Knows product details, never knows stock.

**Entities:**
- Product
- Brand
- Category
- SKU
- Barcode
- Manufacturer
- Attributes
- Images

**Product Ownership:**
Every product has an owner:
- Platform → Global Catalog
- Salon → Private Formula
- Supplier → Supplier Product

**Why:** AI needs to recommend from Platform catalog, Salon catalog, Supplier catalog.

#### Inventory Domain

**Purpose:** Knows stock, never knows product details.

**Entities:**
- Quantity
- Location
- Expiry
- Batch
- Movement
- Adjustment

#### Procurement Domain

**Purpose:** Supplier purchasing.

**Entities:**
- Supplier
- Purchase Order
- Receiving
- Invoices
- Returns

**Note:** Supplier becomes `Provider` with `type: supplier`. No new architecture.

## Design Principles

### 1. Separation of Concerns

**Finance Domain models obligations. Payments Domain executes flows.**

The platform does not hold or move money initially. It records financial events and obligations.

### 2. Polymorphic Design

Finance Domain doesn't know about Specialist, Supplier, etc. It knows:
- `owner_type` / `owner_id`
- `payable_type` / `payable_id`
- `recipient_type` / `recipient_id`

### 3. Event-Driven Boundaries

- **Payments** publishes payment events
- **Bookings** publishes booking lifecycle events
- **Inventory** publishes stock and purchasing events
- **Finance** alone decides how those events become journals, ledger entries, settlements, and payouts

**Caution:** Never let other domains write directly into Finance tables. Preserve this discipline.

### 4. Immutability

- Ledger entries never updated
- Journals never edited
- Corrections via reversing entries

### 5. DB-Driven Configuration

No hardcoded rules. All policies, fees, commissions stored in database.

## Platform Foundation Domains

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

**Note:** Some domains might only have 2-3 tables today. That's fine. You're defining the language of the platform, not building all features.

## Migration Strategy

### Current Migration

`2026_08_06_140000_create_finance_domain_tables.php`

Creates:
- `revenue_distribution_policies` - DB-driven revenue rules
- `journal_entries` - Groups related ledger movements
- `ledger_entries` - Individual financial movements
- `ledger_accounts` - Polymorphic accounts
- `settlements` - Polymorphic obligations (updated)
- `payouts` - Settlement execution records

### Removed

- `specialist_earnings` table - Replaced by generic Finance Domain

## Code Structure

```
backend/app/Domain/Finance/
├── Events/
│   ├── PaymentCaptured.php
│   ├── RevenueDistributed.php
│   ├── SettlementCreated.php
│   └── CommissionCalculated.php (deprecated)
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

## Integration Points

### Event Listener

`ProcessPaymentInFinanceDomain` - Bridges Payments Domain to Finance Domain

Registered in `AppServiceProvider`:
```php
Event::listen(
    PaymentConfirmed::class,
    [
        ProcessPaymentInFinanceDomain::class,
        CreatePaymentNotification::class,
        AddPaymentActivity::class,
        BroadcastNotification::class,
    ]
);
```

### Model Updates

**Specialist Model:**
- Removed `earnings()` relationship
- Added `ledgerAccount()` - Finance Domain ledger
- Added `settlementsAsRecipient()` - Finance Domain settlements
- Added `payouts()` - Finance Domain payouts

**PublicSpecialistController:**
- Earnings endpoint now uses ledger entries instead of SpecialistEarning model

## Testing & Verification

**Verify that:**
- Finance Domain correctly records financial facts
- Creates settlements without interfering with payment flow
- No regressions in payment processing
- Ledger entries are immutable
- Journals balance (debits = credits)

## Conclusion

This is no longer software written for salons. It's software written for **commerce**.

Tomorrow you can introduce:
- Suppliers
- Beauty schools
- Franchise networks
- Equipment rentals
- Retail product sales
- Membership billing
- Marketplace services

…and they can all participate in the same financial model.

**The Finance Domain should become boring over time**—not because it's unimportant, but because every new capability can rely on it instead of reinventing financial logic.

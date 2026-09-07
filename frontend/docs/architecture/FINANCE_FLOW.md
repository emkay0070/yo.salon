# Finance Domain Flow Documentation

## Overview

This document describes the complete flow of financial events through the Finance Domain, from payment capture to settlement execution.

## Architecture Diagram

```
┌─────────────────┐
│   Bookings      │
│   Domain        │
└────────┬────────┘
         │ BookingCompleted
         ▼
┌─────────────────┐
│   Payments      │
│   Domain        │
└────────┬────────┘
         │ PaymentConfirmed
         ▼
┌─────────────────────────────────┐
│   Finance Domain                │
│                                 │
│  ┌───────────────────────────┐  │
│  │ Financial Transaction     │  │
│  │ FT-HAIRCUT-8247          │  │
│  └───────────┬───────────────┘  │
│              │                   │
│              ▼                   │
│  ┌───────────────────────────┐  │
│  │ Journal Entry             │  │
│  │ JNL-ABC123                │  │
│  │ Type: revenue_distribution │  │
│  └───────────┬───────────────┘  │
│              │                   │
│              ▼                   │
│  ┌───────────────────────────┐  │
│  │ Ledger Entries            │  │
│  │                          │  │
│  │ Debit: Salon Revenue     │  │
│  │ Credit: Specialist       │  │
│  │ Credit: Platform Fee     │  │
│  └───────────┬───────────────┘  │
│              │                   │
│              ▼                   │
│  ┌───────────────────────────┐  │
│  │ Settlements               │  │
│  │                          │  │
│  │ Salon owes Specialist    │  │
│  │ Salon owes Platform       │  │
│  └───────────┬───────────────┘  │
│              │                   │
│              ▼                   │
│  ┌───────────────────────────┐  │
│  │ Payouts                  │  │
│  │                          │  │
│  │ Pay Specialist           │  │
│  │ Pay Platform             │  │
│  └───────────────────────────┘  │
└─────────────────────────────────┘
```

## Event Flow Sequence

### 1. Payment Confirmation

**Trigger:** Customer completes payment for booking

**Event:** `PaymentConfirmed` (from Payments Domain)

**Listener:** `ProcessPaymentInFinanceDomain`

**Action:** Dispatches `PaymentCaptured` event and triggers revenue distribution

```php
Event::listen(PaymentConfirmed::class, [
    ProcessPaymentInFinanceDomain::class,
    // ... other listeners
]);
```

### 2. Revenue Distribution

**Trigger:** `PaymentCaptured` event

**Service:** `RevenueDistributionEngine`

**Action:** 
- Fetches applicable `RevenueDistributionPolicy` from database
- Calculates revenue splits based on policy rules
- Creates financial transaction with journal entries
- Creates settlements for payable obligations
- Dispatches `RevenueDistributed` event

**Example Policy (from DB):**
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

### 3. Financial Transaction Creation

**Model:** `FinancialTransaction`

**Purpose:** Groups related journals for reconciliation

**Example:**
```
FinancialTransaction: FT-HAIRCUT-8247
├── Type: revenue_distribution
├── Reference: Booking #8247
└── Total: 50,000 UGX
```

**Event:** `FinancialTransactionPosted`

### 4. Journal Entry Creation

**Model:** `JournalEntry`

**Purpose:** Groups related ledger movements

**Example:**
```
JournalEntry: JNL-ABC123
├── Type: revenue_distribution
├── Total Debit: 50,000
├── Total Credit: 50,000
└── Status: posted
```

**Ledger Entries:**
```
├── Debit: Salon Revenue Account 50,000
├── Credit: Specialist Receivable 20,000 (40%)
└── Credit: Platform Receivable 5,000 (fixed fee)
```

**Event:** `JournalPosted`

**Immutability:** Journals cannot be edited. Void and create correction.

### 5. Ledger Account Updates

**Model:** `LedgerAccount`

**Purpose:** Tracks account balances

**Polymorphic Owners:**
- Salon (revenue account)
- Specialist (receivable account)
- Platform (receivable account)
- Customer (payable account)

**Balance Calculation:**
- Debit: Decreases balance
- Credit: Increases balance

**Event:** `LedgerEntryCreated`

**Immutability:** Ledger entries cannot be updated or deleted.

### 6. Settlement Creation

**Model:** `Settlement`

**Purpose:** Represents obligation from one entity to another

**Polymorphic:**
- `payable_type` / `payable_id` - Who owes (Salon)
- `recipient_type` / `recipient_id` - Who receives (Specialist, Platform)

**Example:**
```
Settlement #1
├── Payable: Salon
├── Recipient: Specialist
├── Amount: 20,000 UGX
├── Status: pending
└── Due Date: 2026-08-13
```

**Event:** `SettlementCreated`

**Lifecycle States:**
- `pending` - Obligation created
- `partially_paid` - Some amount paid
- `paid` - Fully settled
- `cancelled` - Obligation cancelled
- `disputed` - Under dispute

### 7. Payout Execution

**Model:** `Payout`

**Purpose:** Records actual money transfer

**Polymorphic Recipient:** Specialist, Platform, Supplier

**Example:**
```
Payout #1
├── Settlement: #1
├── Recipient: Specialist
├── Amount: 20,000 UGX
├── Method: mobile_money
├── Status: completed
└── Processed By: Salon Owner
```

**Settlement Status Update:** When payout completes, settlement status changes to `paid`

**Event:** `SettlementClosed`

## Reversal Flow

### Voiding a Journal

**Scenario:** Error in original journal

**Process:**
1. Call `journal->void()`
2. System creates reversing journal with opposite entries
3. Original journal marked as `voided`
4. Account balances restored
5. Event: `JournalVoided`

**Example:**
```
Original Journal JNL-ABC123
├── Debit: Salon 50,000
└── Credit: Specialist 20,000

Reversing Journal JNL-XYZ789
├── Credit: Salon 50,000
└── Debit: Specialist 20,000
```

**Immutability:** Never edit. Always create reversing entries.

## Event Summary

### Input Events (from other domains)

- `PaymentConfirmed` - Payment completed
- `BookingCompleted` - Booking finished
- `RefundRequested` - Refund requested

### Output Events (Finance Domain publishes)

- `PaymentCaptured` - Payment received by Finance
- `RevenueDistributed` - Revenue split calculated
- `FinancialTransactionPosted` - Transaction created
- `JournalPosted` - Journal posted
- `LedgerEntryCreated` - Ledger entry created
- `SettlementCreated` - New obligation created
- `SettlementClosed` - Settlement fully paid
- `JournalVoided` - Journal voided
- `FinancialTransactionVoided` - Transaction voided

## Database Schema

### Core Tables

```
financial_transactions
├── id (UUID)
├── transaction_number (unique)
├── type
├── description
├── reference_type / reference_id
├── total_amount
├── currency
├── status (draft/posted/voided)
└── metadata

journal_entries
├── id (UUID)
├── financial_transaction_id (FK)
├── journal_number (unique)
├── type
├── description
├── total_debit
├── total_credit
├── status (draft/posted/voided)
└── metadata

ledger_entries
├── id (UUID)
├── journal_entry_id (FK)
├── ledger_account_id (FK)
├── type (debit/credit)
├── amount
├── balance_after
├── description
└── metadata

ledger_accounts
├── id (UUID)
├── owner_type / owner_id (polymorphic)
├── account_type
├── currency
├── balance
├── status (active/frozen/closed)
└── metadata

settlements
├── id (UUID)
├── payable_type / payable_id (polymorphic)
├── recipient_type / recipient_id (polymorphic)
├── amount
├── currency
├── status (pending/partially_paid/paid/cancelled/disputed)
├── due_date
└── metadata

payouts
├── id (UUID)
├── settlement_id (FK)
├── recipient_type / recipient_id (polymorphic)
├── amount
├── currency
├── method
├── status (pending/processing/completed/failed)
└── metadata

revenue_distribution_policies
├── id (UUID)
├── context_type / context_id (polymorphic)
├── revenue_type
├── rules (JSON)
├── is_active
├── priority
├── effective_from
├── effective_until
└── metadata
```

## Key Design Principles

### 1. Immutability

- Ledger entries never updated
- Journals never edited
- Corrections via reversing entries
- Audit trail preserved

### 2. Event-Driven

- Domains communicate via events
- No direct method calls between domains
- Loose coupling

### 3. Polymorphic Design

- Finance doesn't know entity types
- Uses `owner_type` / `owner_id`
- Any entity can participate

### 4. DB-Driven Configuration

- Revenue policies in database
- No hardcoded rules
- Configurable per context

### 5. Separation of Concerns

- Finance models obligations
- Payments executes flows
- Settlement ≠ Payout

## Testing Strategy

### Unit Tests

- Journal entry balance validation
- Ledger entry immutability
- Journal voiding and reversal
- Settlement lifecycle
- Revenue distribution calculation

### Integration Tests

- Payment → Finance flow
- Revenue distribution → Settlements
- Settlement → Payout flow
- Event propagation

### Contract Tests

- Event structure validation
- API contract validation
- Database schema validation

## Future Enhancements

### Accounting Periods

```
Financial Period: 2026-08
├── Status: Open/Closed/Locked
├── Started: 2026-08-01
├── Ended: 2026-08-31
└── Locked: 2026-09-15
```

### Multi-Currency Support

- Currency conversion
- FX rate tracking
- Multi-currency settlements

### Advanced Revenue Policies

- Tiered rules
- Caps and maximums
- Conditional rules
- Time-based rules

## Conclusion

The Finance Domain provides a robust, immutable, event-driven foundation for financial operations. By separating concerns and maintaining clear boundaries, it can support the platform's growth for years without requiring major architectural changes.

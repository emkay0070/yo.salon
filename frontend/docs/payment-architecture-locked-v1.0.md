# Payment Architecture - Locked v1.0

**Status:** ✅ Stable Foundation  
**Date:** 21 August 2026  
**Test Coverage:** 89 tests, 330 assertions (100% passing)

---

# Executive Summary

The payment architecture has been established as a stable foundation for Yo.Salon. This document describes the implemented and verified architecture for incoming payments (money coming IN from customers), which complements the existing compensation/payout architecture (money going OUT to specialists).

**Key Achievement:** Eliminated ambiguous `amount` field and replaced it with explicit economics: `gross_amount`, `gateway_fee`, `platform_fee`, `tax_amount`, `net_amount`.

---

# Core Architectural Principle

The payment architecture enforces strict separation of concerns:

| Layer                  | Responsibility                              |
| ---------------------- | ------------------------------------------- |
| **PaymentMethod**      | HOW the customer pays                       |
| **PaymentAccount**     | WHOSE account receives the money            |
| **PaymentRoutingService** | Which account should be used                |
| **PaymentProvider**    | How the external provider moves money       |
| **FeeEngine**          | What the transaction economically means     |
| **Transaction**        | Immutable record of what happened           |
| **Finance/Ledger**     | What that transaction means to the business |

---

# 1. Transaction Economics Normalization

## Problem Solved

The Transaction model previously had an ambiguous `amount` field that didn't distinguish between:
- Gross amount (what customer paid)
- Gateway fees (payment processor costs)
- Platform fees (Yo.Salon commission)
- Net amount (what salon actually receives)

## Solution

Replaced `amount` with canonical fee fields:

```php
Transaction
├── gross_amount      // Total amount customer paid
├── gateway_fee       // Payment processor fee (e.g., Flutterwave 2.8%)
├── platform_fee      // Yo.Salon commission (0 for core bookings)
├── tax_amount        // Tax withholdings (0 currently)
└── net_amount        // gross - gateway - platform - tax
```

## Example Calculation

```
Customer pays:        UGX 3,000
Gateway fee (2.8%):  UGX 84
Platform fee:         UGX 0 (zero commission)
Tax:                  UGX 0
─────────────────────────────────
Net to salon:         UGX 2,916
```

## Business Impact

- **Intelligence** can confidently answer "How much did the salon generate?" using `gross_amount`
- **Finance** can track "How much did payment processing cost?" using `gateway_fee`
- **Revenue** is calculated correctly as `net_amount`
- **Zero platform commission** is enforced for core bookings

---

# 2. PaymentAccount Architecture

## Purpose

`PaymentAccount` represents the financial account that receives funds, distinct from `PaymentMethod`:

- **PaymentMethod**: HOW the customer pays (MTN MoMo, Visa, Cash)
- **PaymentAccount**: WHOSE account receives the money (Salon's Flutterwave, Yo.Salon routing)

## Model Structure

```php
PaymentAccount
├── salon_id              // Owner salon
├── specialist_id         // Owner specialist (future)
├── provider              // flutterwave, mtn, airtel, stripe
├── account_type          // merchant, subaccount, routing, manual
├── external_account_id   // Provider's account ID
├── verification_status   // pending, verified, failed, suspended
├── is_active             // Account operational status
├── is_default            // Salon's default account
├── settlement_schedule   // daily, weekly, monthly
└── metadata              // Additional provider data
```

## Account Types

| Type        | Purpose                                      |
| ----------- | -------------------------------------------- |
| `merchant`  | Salon's own merchant account (existing setup) |
| `subaccount`| Yo.Salon-connected subaccount (future)        |
| `routing`   | Yo.Salon routing account (future)             |
| `manual`    | Offline/cash settlement                      |

## Provider Agnostic

The architecture supports any payment provider:
- Flutterwave
- Stripe
- MTN MoMo
- Airtel Money
- M-Pesa
- Future providers

---

# 3. PaymentRoutingService

## Routing Hierarchy

The service determines which PaymentAccount should receive funds using a priority cascade:

```
1. PaymentMethod's explicit PaymentAccount (if set)
        ↓
2. Salon's default PaymentAccount for the provider
        ↓
3. Salon's default PaymentAccount (any provider)
        ↓
4. null (backward compatibility)
```

## Validation Rules

Before using an account, the service validates:

- **Active status**: Suspended accounts are skipped
- **Verification status**: Unverified accounts are skipped
- **Provider match**: Wrong provider accounts are skipped
- **Usability**: Account must be capable of receiving payments

## Example Scenarios

| Payment Method | Explicit Account | Salon Default | Expected    |
| -------------- | ---------------- | ------------- | ----------- |
| MTN            | MTN account      | Flutterwave   | MTN account |
| MTN            | —                | MTN account   | MTN account |
| MTN            | —                | Flutterwave   | Flutterwave |
| Cash           | —                | —             | null        |
| Card           | Flutterwave A    | Flutterwave B | A           |

## Edge Cases Handled

- Suspended accounts → fallback to next option
- Unverified accounts → fallback to next option
- Wrong provider accounts → fallback to next option
- No usable accounts → return null (backward compatible)

---

# 4. FeeEngine

## Single Source of Truth

`FeeEngine` is the only place where fee calculations should occur.

## Methods

### `calculateFees()` - Default (Zero Fees)

```php
public function calculateFees(float $grossAmount, ?PaymentMethod $paymentMethod): array
{
    return [
        'gross_amount' => $grossAmount,
        'gateway_fee' => 0.0,
        'platform_fee' => 0.0,
        'tax_amount' => 0.0,
        'net_amount' => $grossAmount,
    ];
}
```

Used for manual payments and when provider fees are unavailable.

### `calculateFeesFromProvider()` - Actual Gateway Fees

```php
public function calculateFeesFromProvider(float $grossAmount, ?PaymentMethod $paymentMethod, array $providerResponse): array
{
    $gatewayFee = (float) ($providerResponse['fees'] ?? 0.0);
    $platformFee = 0.0; // Zero platform commission
    $taxAmount = 0.0;
    
    $netAmount = $grossAmount - $gatewayFee - $platformFee - $taxAmount;
    
    return [
        'gross_amount' => $grossAmount,
        'gateway_fee' => $gatewayFee,
        'platform_fee' => $platformFee,
        'tax_amount' => $taxAmount,
        'net_amount' => $netAmount,
    ];
}
```

Extracts actual gateway fees from provider responses (e.g., Flutterwave's `app_fee`).

### `calculateFeesWithConfig()` - Custom Configuration

For future use when configurable fee structures are needed.

## Zero Platform Commission

The architecture enforces Yo.Salon's zero-platform-commission philosophy for core bookings:

```php
'platform_fee' => 0.0  // Always zero for core bookings
```

---

# 5. Database Schema Changes

## New Table: payment_accounts

```sql
CREATE TABLE payment_accounts (
    id UUID PRIMARY KEY,
    salon_id UUID,
    specialist_id UUID,
    provider VARCHAR(50),
    account_type VARCHAR(50),
    external_account_id VARCHAR(255),
    external_account_reference VARCHAR(255),
    account_name VARCHAR(255),
    verification_status VARCHAR(50),
    is_active BOOLEAN DEFAULT true,
    is_default BOOLEAN DEFAULT false,
    settlement_schedule VARCHAR(50),
    settlement_currency VARCHAR(10),
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_payment_accounts_salon (salon_id),
    INDEX idx_payment_accounts_specialist (specialist_id),
    INDEX idx_payment_accounts_provider (provider),
    INDEX idx_payment_accounts_status (verification_status, is_active),
    
    FOREIGN KEY (salon_id) REFERENCES salons(id),
    FOREIGN KEY (specialist_id) REFERENCES specialists(id)
);
```

## Updated Table: payment_methods

Added nullable foreign key:

```sql
ALTER TABLE payment_methods
ADD COLUMN payment_account_id UUID NULL,
ADD INDEX idx_payment_methods_payment_account (payment_account_id),
ADD FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id);
```

## Updated Table: transactions

Added nullable foreign key:

```sql
ALTER TABLE transactions
ADD COLUMN payment_account_id UUID NULL,
ADD INDEX idx_transactions_payment_account (payment_account_id),
ADD FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id);
```

## Backward Compatibility

All new foreign keys are nullable, ensuring existing payment flows continue to work without configuration.

---

# 6. Service Integration

## Updated Services

### SalonPaymentService

- Added `PaymentRoutingService` dependency
- Uses `calculateFeesFromProvider()` when provider returns fees
- Records `payment_account_id` in all transactions
- Refunds inherit `payment_account_id` from original transaction

### TransactionController

- Added `PaymentRoutingService` dependency
- Determines payment account for manual transactions
- Records `payment_account_id` in transaction

### PaymentRequestController

- Added `PaymentRoutingService` dependency
- Determines payment account on payment status updates
- Records `payment_account_id` in transaction

---

# 7. Test Coverage

## PaymentRoutingService (14 tests)

✅ Routing matrix fallback scenarios  
✅ Suspended account handling  
✅ Unverified account handling  
✅ Wrong provider account handling  
✅ No usable accounts fallback  
✅ Manual payment routing  
✅ Provider hint routing  
✅ Available accounts filtering  
✅ Default account retrieval  

## TransactionCreationTest (5 tests)

✅ Digital payment initialization  
✅ Digital payment verification  
✅ Idempotent payment handling  
✅ Manual payment recording  
✅ Manual payment refund  

## FeeEngineTest (9 tests)

✅ Zero platform fee enforcement  
✅ Zero gateway fee default  
✅ Null payment method handling  
✅ Provider fee extraction  
✅ Custom config calculations  
✅ Fee structure format validation  

## PaymentToFinanceIntegrationTest (5 tests)

✅ End-to-end payment economics  
✅ Manual payment economics  
✅ Refund transaction economics  
✅ PaymentAccount routing verification  
✅ Zero platform fee maintained  

## Total: 89 tests, 330 assertions (100% passing)

---

# 8. Flutterwave Integration

## Actual Fee Tracking

The system now extracts and uses Flutterwave's actual gateway fees:

```php
// FlutterwaveProvider returns
'fees' => $data['app_fee'] ?? 0

// FeeEngine uses it
$gatewayFee = (float) ($providerResponse['fees'] ?? 0.0);
```

## Uganda Economics

Documented in `docs/flutterwave-uganda-economics.md`:
- Mobile Money Uganda: ~1.5% - 2%
- Card payments: ~3.5%
- USSD: ~1.5% - 2%

These figures are provider configuration, not hardcoded business constants.

---

# 9. Architectural Rules

## Do Not Collapse These Concepts

```text
PaymentMethod ≠ PaymentProfile
```

- **PaymentMethod**: HOW the salon sends money / outbound rail
- **PaymentProfile**: WHERE the recipient receives money

## Do Not Calculate From Booking.price

Never calculate compensation from:
```php
Booking::sum('price')
```

Use the canonical Transaction economics instead.

## Do Not Use Transaction as Accounting Truth

Financial reporting should use:
```text
LedgerEntry
```

not raw Transaction data.

## Do Not Create Wallets

Ledger is already the financial truth. Do not introduce virtual wallet systems.

## Do Not Change Platform Fee

Zero platform commission for core bookings is enforced:
```php
'platform_fee' => 0.0
```

---

# 10. Migration Strategy

## Phase 1: Foundation (Complete)

- ✅ PaymentAccount model and table
- ✅ PaymentAccount foreign keys added
- ✅ PaymentRoutingService implemented
- ✅ FeeEngine enhanced with provider fee extraction
- ✅ All services updated
- ✅ Full test coverage

## Phase 2: Configuration (Future)

- Salons configure their PaymentAccounts
- PaymentMethods linked to PaymentAccounts
- Default accounts established

## Phase 3: Production Rollout (Future)

- Monitor routing behavior
- Verify fee accuracy
- Validate settlement calculations

---

# 11. Connection to Compensation Architecture

The payment architecture (incoming) connects to the compensation architecture (outgoing) at the Ledger:

```
Customer Payment
        ↓
Transaction (with economics)
        ↓
Finance Domain
        ↓
Ledger
        ↓
RevenueDistributionEngine
        ↓
Earning
        ↓
CompensationPeriod
        ↓
Settlement
        ↓
Payout
```

The compensation architecture is documented separately in the Finance handoff document.

---

# 12. Files Changed

## New Files

- `app/Models/PaymentAccount.php`
- `app/Services/Payments/PaymentRoutingService.php`
- `database/migrations/2026_08_19_191002_create_payment_accounts_table.php`
- `database/migrations/2026_08_19_191324_add_payment_account_id_to_payment_methods_table.php`
- `database/migrations/2026_08_20_072657_add_payment_account_id_to_transactions_table.php`
- `database/factories/PaymentAccountFactory.php`
- `database/factories/SalonFactory.php`
- `database/factories/PaymentMethodFactory.php`
- `tests/Feature/PaymentRoutingServiceTest.php`
- `tests/Feature/PaymentToFinanceIntegrationTest.php`
- `docs/flutterwave-uganda-economics.md`

## Modified Files

- `app/Models/PaymentMethod.php` (added payment_account_id, relationship)
- `app/Models/Transaction.php` (added payment_account_id, relationship)
- `app/Services/FeeEngine.php` (added calculateFeesFromProvider)
- `app/Services/Payments/SalonPaymentService.php` (added routing, provider fees)
- `app/Http/Controllers/Api/V1/TransactionController.php` (added routing)
- `app/Http/Controllers/Api/V1/PaymentRequestController.php` (added routing)

---

# 13. Status: LOCKED v1.0

This architecture is now **stable and verified**.

## What This Means

- ✅ Core payment architecture is approved
- ✅ Transaction economics are canonical
- ✅ PaymentAccount abstraction is flexible
- ✅ PaymentRoutingService is validated
- ✅ FeeEngine is the single source of truth
- ✅ Zero platform commission is enforced
- ✅ All tests passing (89/89)

## What This Does NOT Mean

- ❌ Provider integrations are production-complete (Flutterwave needs production verification)
- ❌ All payment providers are integrated (only Flutterwave is implemented)
- ❌ Settlement reconciliation is complete
- ❌ Finance UI is redesigned
- ❌ Production KYC is mature

## Next Steps (Not This Architecture)

The next phase should focus on:
- Production provider verification
- Finance UI redesign based on new economics
- Free/Pro/Trial state testing
- Backend capability enforcement
- Subscription/upgrade mechanics

---

# 14. Handoff Instruction

> **Treat the existing PaymentAccount → PaymentRoutingService → FeeEngine → Transaction economics architecture as approved and locked; extend it through the existing abstractions rather than redesigning it, and never introduce a second financial truth, wallet system, or Booking/Transaction-based compensation calculation.**

---

**Document Version:** locked-v1.0  
**Last Updated:** 21 August 2026  
**Test Status:** 89/89 passing (330 assertions)

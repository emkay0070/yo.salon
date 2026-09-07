# Yo.Salon Billing Domain Architecture

> **Principle:** Build the complete schema now. Ship minimal logic. Expand in place.
>
> The database is the foundation of the skyscraper. Tables cost nothing extra today.
> Business logic is the floors — build only what you occupy.

---

## The Unified Commerce Pipeline

Every monetizable thing on the platform flows through one pipeline:

```
Catalog
  ↓
Entitlements
  ↓
Usage
  ↓
Rating (metering)
  ↓
Invoice
  ↓
Payment
  ↓
Accounting
  ↓
Reporting
```

This pipeline handles:
- Monthly subscriptions
- AI usage packs
- SMS packs
- Marketing campaign packs
- Premium website themes
- Marketplace featured listings
- Extra staff seats
- Additional branches
- Storage
- API access
- Future products not yet imagined

---

## The Two Money Flows (Keep Separate Forever)

```
Money Flow A — SaaS (Yo.Salon bills the Salon)
  Salon Owner → Yo.Salon
  (Subscription, AI, SMS, Add-ons, Packs)

Money Flow B — Commerce (Customer books a Salon)
  Customer → Salon
  (via Flutterwave, MTN MoMo, Airtel, Cash)
```

These two flows must never be merged in the billing domain.
Flow B (Commerce) is activated only in Phase 2 (Fintech Pivot).

---

## Domain Map

### 1. Billing Catalog

Defines everything Yo.Salon can sell.

| Table               | Responsibility                          |
|---------------------|-----------------------------------------|
| `billing_resources` | Atomic units that can be consumed       |
| `platform_products` | What is sold to salon owners            |
| `plans`             | Subscription tiers (existing)           |
| `plan_entitlements` | What resources each plan includes       |
| `price_books`       | Regional/promotional pricing containers |
| `prices`            | Actual price per product per price book |

**Resource codes (canonical):**

| Code                  | Description                       |
|-----------------------|-----------------------------------|
| `SMS`                 | Single SMS message                |
| `AI_REQUEST`          | Single AI inference call          |
| `WHATSAPP`            | Single WhatsApp message           |
| `EMAIL`               | Single transactional email        |
| `STORAGE_GB`          | 1 GB of file storage              |
| `STAFF_SEAT`          | One staff member slot             |
| `BRANCH`              | One additional branch             |
| `BOOKING`             | One confirmed booking             |
| `EXPORT`              | One data export                   |
| `API_CALL`            | One API request                   |
| `MARKETPLACE_BOOST`   | One month of featured listing     |
| `MARKETING_CAMPAIGN`  | One outbound campaign             |

---

### 2. Usage Engine

Tracks consumption against entitlements.

| Table          | Responsibility                                     |
|----------------|----------------------------------------------------|
| `usage`        | Running totals & quota limits per period (existing)|
| `usage_events` | Immutable audit log of every consumption event     |

**`usage_events` columns:**
```
id             UUID
provider_id    UUID (the salon)
resource_code  string (e.g. 'SMS', 'AI_REQUEST')
units          integer
metadata       JSON (context: booking_id, customer_id, etc.)
occurred_at    timestamp
created_at     timestamp
```

Every feature that consumes a resource must fire a `UsageEvent`. No exceptions.
This data enables: fair-use limits, overage billing, infrastructure cost forecasting,
analytics, and future pricing changes.

---

### 3. Subscription Engine

Manages the lifecycle of a salon's plan.

**Lifecycle states (existing `subscriptions` table):**
```
trialing → active → past_due → cancelled / expired
```

**Renewal flow (ProcessSubscriptionRenewals job):**
```
1. Find subscriptions due for renewal
2. Calculate: subscription fee + any metered overage from usage_events
3. Generate Invoice (status: draft)
4. Freeze Invoice (status: finalized — amount is now immutable)
5. Attempt automatic collection via PaymentManager
     ├─ Success → Invoice: paid, BillingEvent logged
     └─ Failure/Unsupported → Invoice: pending, Salon notified to pay manually
6. Subscription continues on grace period until paid
```

---

### 4. Invoice Engine

**Invoice lifecycle:**
```
draft → finalized → paid
                  → void
                  → refunded (→ credit_note)
```

**Accounting invariant (must always hold):**
```
Invoice.total = subscription_fee + usage_overage + add_ons - discounts + tax
```

This must be validated before an invoice is finalized and frozen.

---

### 5. Payment Engine

Provider-agnostic by design. The billing engine never speaks Flutterwave directly.

**Interface contract (`Contracts/PaymentProviderInterface`):**
```php
requestPayment(PaymentRequestDTO): PaymentResponseDTO
checkPaymentStatus(string $transactionId): PaymentResponseDTO
attemptInvoiceCollection(Invoice, PaymentMethod): PaymentResponseDTO
refundPayment(string $reference, ?float $amount): PaymentResponseDTO
handleWebhook(array $payload): bool
verifyWebhookSignature(array $payload, string $signature): bool
getProviderName(): string
getCapabilities(): array   // ['subscriptions', 'refunds', 'momo_push', 'cards']
```

**Provider capabilities (not all providers support everything):**
```
Flutterwave:  cards, refunds, webhooks
MTN MoMo:     momo_push, webhooks
Airtel:       momo_push, webhooks
Manual/Cash:  mark_paid (no automatic collection)
```

**Response object (existing `PaymentResponseDTO`):**
```
success           bool
status            string
transactionId     string
providerReference string
message           string
rawResponse       array   ← raw gateway JSON, never exposed to billing engine
```

**PaymentManager (existing, extend only):**
```
PaymentManager
  ├── mtn_momo     → MTNMomoService
  ├── airtel       → AirtelProvider
  ├── flutterwave  → FlutterwaveProvider   ← register here
  └── manual       → ManualProvider        ← for cash/POS confirmations
```

---

### 6. Add-on Purchase Flow

```
Salon clicks "Buy SMS Pack (500 SMS — UGX 30,000)"
  ↓
platform_products lookup (code: SMS_500)
  ↓
Invoice generated (type: add_on, not subscription)
  ↓
Payment collected
  ↓
usage limit increased (usage table updated)
  ↓
BillingEvent logged
```

---

### 7. Price Books

Enables regional and promotional pricing without changing products.

```
platform_products
  ↓ (has many)
prices
  ├── price_book: uganda_default,  amount: 30000, currency: UGX
  ├── price_book: kenya_default,   amount: 850,   currency: KES
  ├── price_book: nigeria_default, amount: 1500,  currency: NGN
  └── price_book: promo_q1_2027,  amount: 25000,  currency: UGX
```

*Price books are not required for Phase 1 launch.*
*Build the table schema now. Populate Uganda pricing only.*

---

## What Is NOT Built Now

The following introduce legal, regulatory, and operational complexity.
They are architected for but not implemented until real business requirements exist:

- Salon wallet settlements / payouts (Money Flow B — Phase 2)
- Double-entry accounting / ledger reconciliation
- Multi-currency FX conversion engine
- Treasury / escrow management
- Banking reconciliation

---

## Implementation Sequence

### Phase 1 — Complete Schema (Build now, ship foundation)
1. `billing_resources` table + model + seed resource codes
2. `platform_products` table + model + seed initial packs (SMS_500, AI_1000, MARKETING_PACK)
3. `plan_entitlements` table + model (links plans to resources with limits)
4. `price_books` + `prices` tables + model (Uganda pricing only)
5. `usage_events` table + model

### Phase 2 — Minimal Logic (Ship what unlocks first revenue)
1. `ProcessSubscriptionRenewals` job
2. `AttemptInvoiceCollection` on PaymentProviderInterface
3. Register FlutterwaveProvider in PaymentManager
4. Add-on purchase endpoint

### Phase 3 — Leave Alone (Already working)
- `Subscription`, `Invoice`, `BillingEvent`, `Plan`, `Usage`, `PaymentManager`

---

## Single Responsibility Reference

| Table               | The One Question It Answers                   |
|---------------------|-----------------------------------------------|
| `billing_resources` | What are the atomic things we meter?          |
| `platform_products` | What do we sell?                              |
| `plan_entitlements` | What does each plan include?                  |
| `price_books`       | What is the pricing context?                  |
| `prices`            | How much does this product cost here?         |
| `usage_events`      | What was consumed, by whom, and when?         |
| `usage`             | How much has been used vs. the limit?         |
| `plans`             | What are the subscription tiers?              |
| `subscriptions`     | Which plan is this salon on?                  |
| `invoices`          | How much does this salon owe?                 |
| `billing_events`    | What happened in the billing lifecycle?       |
| `invoice_payments`  | How was an invoice paid?                      |

---

*Last updated: August 2026*
*Author: Platform Architecture Discussion — Yo.Salon founding team*

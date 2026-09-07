# Subscription Domain Architecture

## Overview

The subscription domain is a provider-centric billing system designed to support all future business types without requiring redesign. It follows the principle that subscriptions belong to providers, not salons, and that invoices are the source of truth for billing.

## Strategic Vision

**Core Principle:** Build a generic, provider-centric billing system that works for:
- Salons
- Independent Specialists
- Beauty Schools
- Spas
- Mobile Teams
- Any future provider type

**Revenue Model:**
- Salon OS: Subscription tiers (Starter, Professional, Premium, Enterprise)
- Professional Network: Growth plans (Free, Pro, Elite)
- Future: AI credits, SMS bundles, featured listings, storage upgrades, white-label services, API access

**Key Design Decisions:**
1. Subscriptions belong to `provider_id`, not `salon_id`
2. Plans support different `provider_type` values
3. Invoices are the source of truth (Invoice → Payment → Subscription)
4. Cash is a first-class payment method
5. Payment infrastructure is reused from customer booking payments
6. Customer booking payments are completely separate from platform subscription payments

## Domain Model

### Provider-Centric Architecture

```
Person
    │
    ├── can work for → Provider
    ├── can own → Provider
    └── has Portal Account

Provider
    ├── Services
    ├── Bookings
    ├── Reviews
    ├── Payment Methods
    ├── Subscription
    ├── Invoices
    └── Analytics

Customer
    ├── Bookings
    ├── Reviews
    ├── Loyalty
    └── Payments (for bookings only)
```

### Subscription Flow

```
Choose Plan
    ↓
Create Invoice
    ↓
Choose Payment Method
    ↓
Payment Attempt
    ↓
Verified
    ↓
Subscription Activated
```

**Note:** Activation happens from the invoice, not directly from the payment provider.

### Payment Methods

Providers can renew using:
- MTN Mobile Money (Personal - Manual Verification)
- Airtel Money (Personal - Manual Verification)
- Bank Transfer (DFCU - Manual Verification)
- Cash (Manual Verification)
- MTN Mobile Money (Merchant - Automatic Verification - Future)
- Flutterwave (Webhook Verification - Future)

**Note:** Card credentials (card number, expiry, CVV) are never exposed. Cards are processed through merchant gateways like Flutterwave, not by exposing card details to customers.

## Platform Payment Methods

### Launch Strategy (Manual Verification)

For initial launch, Yo.Salon accepts payments directly to platform-owned accounts:

**Platform Payment Methods:**
- MTN Mobile Money Number (Personal - Manual Verification)
- Airtel Money Number (Personal - Manual Verification)
- DFCU Bank Account (Manual Verification)
- Cash (Manual Verification)

**Payment Flow:**
```
Provider chooses plan
    ↓
Invoice generated
    ↓
Payment Options displayed
    ↓
Provider pays directly to platform account
    ↓
Admin verifies payment receipt
    ↓
Subscription activated
```

### Future Strategy (Automatic Verification)

When Yo.Salon obtains merchant credentials:

**Platform Payment Methods:**
- MTN Mobile Money (Merchant - Automatic Verification)
- Flutterwave (Webhook Verification)
- Card Payments (via Flutterwave - Webhook Verification)

**Payment Flow:**
```
Provider chooses plan
    ↓
Invoice generated
    ↓
Payment Options displayed
    ↓
Provider clicks Pay
    ↓
Payment gateway processes
    ↓
Webhook confirms payment
    ↓
Subscription activated automatically
```

**Note:** The subscription system remains the same. Only the payment verification method changes.

### Verification Modes

Each payment method declares its verification mode:

**Manual Verification:**
- MTN Personal
- Airtel Personal
- Bank Transfer
- Cash
- Process: Admin manually verifies payment receipt

**Automatic Verification:**
- MTN Merchant
- Process: Payment gateway automatically confirms via API

**Webhook Verification:**
- Flutterwave
- Card Payments
- Process: Payment gateway sends webhook with signature verification

**Implementation:**
The billing engine checks the payment method's verification mode:
```php
$verificationMode = $paymentMethod->verification_mode;

switch ($verificationMode) {
    case 'manual':
        // Admin confirms payment
        break;
    case 'automatic':
        // Gateway confirms via API
        break;
    case 'webhook':
        // Gateway sends webhook
        break;
}
```

### Important Security Note

**Card Credentials:**
- ❌ Never expose card number, expiry date, or CVV to customers
- ✅ Cards are processed through merchant gateways (Flutterwave)
- ✅ Customers pay via secure checkout, not by entering card details directly

**Personal Accounts:**
- ✅ Accept payments to personal mobile money/bank accounts for launch
- ✅ Treat as temporary operational solution
- ✅ Migrate to business-owned accounts as Yo.Salon grows
- ❌ Do not use personal card credentials for platform payments

## Database Schema

### Plans Table

**Purpose:** Generic plan definitions for different provider types

**Fields:**
- `id` (UUID, primary)
- `provider_type` (string, default: 'salon') - salon, specialist, spa, beauty_school, mobile_team
- `name` (string) - Starter, Professional, Premium, Enterprise
- `slug` (string, unique) - starter, professional, premium, enterprise
- `description` (text, nullable)
- `monthly_price` (decimal)
- `yearly_price` (decimal)
- `features` (json, nullable) - array of feature names
- `staff_limit` (integer, default: 1)
- `branches_limit` (integer, default: 1)
- `storage_limit_gb` (integer, default: 1)
- `support_level` (string, default: 'email')
- `is_active` (boolean, default: true)
- `sort_order` (integer, default: 0)
- `created_at`, `updated_at`

**Indexes:**
- `slug`
- `is_active`
- `provider_type`

**Example Plans (Salon):**
- Starter: 50,000 UGX/month
- Professional: 150,000 UGX/month
- Premium: 350,000 UGX/month
- Enterprise: 750,000 UGX/month

**Future Plans (Specialist):**
- Free: 0 UGX/month
- Pro: 50,000 UGX/month
- Elite: 150,000 UGX/month

### Subscriptions Table

**Purpose:** Provider subscription records

**Fields:**
- `id` (UUID, primary)
- `provider_id` (UUID, foreign key to providers) - NOT salon_id
- `plan_id` (UUID, foreign key to plans)
- `status` (string, default: 'trialing') - trialing, active, past_due, cancelled, expired
- `billing_cycle` (string, default: 'monthly') - monthly, yearly
- `trial_ends_at` (timestamp, nullable)
- `starts_at` (timestamp)
- `ends_at` (timestamp, nullable)
- `renews_at` (timestamp, nullable)
- `cancelled_at` (timestamp, nullable)
- `cancel_reason` (string, nullable)
- `metadata` (json, nullable)
- `created_at`, `updated_at

**Indexes:**
- `provider_id` (unique)
- `plan_id`
- `status`

**Relationships:**
- `provider()` - belongs to Provider
- `plan()` - belongs to Plan
- `invoices()` - has many Invoice
- `billingEvents()` - has many BillingEvent
- `usage()` - has many Usage

### Invoices Table

**Purpose:** Invoice records - source of truth for billing

**Fields:**
- `id` (UUID, primary)
- `provider_id` (UUID, foreign key to providers, nullable)
- `subscription_id` (UUID, foreign key to subscriptions, nullable)
- `invoice_number` (string, unique) - auto-generated (INV-YYYYMMDD-####)
- `status` (string, default: 'pending') - pending, paid, failed, cancelled
- `currency` (string, default: 'UGX')
- `subtotal` (decimal, default: 0)
- `tax` (decimal, default: 0)
- `discount` (decimal, default: 0)
- `total` (decimal, default: 0)
- `due_date` (timestamp, nullable)
- `paid_at` (timestamp, nullable)
- `payment_method` (string, nullable) - cash, mtn, airtel, flutterwave, card
- `line_items` (json, nullable) - array of line items
- `metadata` (json, nullable)
- `created_at`, `updated_at`

**Indexes:**
- `subscription_id`
- `provider_id`
- `status`
- `invoice_number`

**Relationships:**
- `provider()` - belongs to Provider
- `subscription()` - belongs to Subscription
- `payments()` - has many InvoicePayment

### InvoicePayments Table

**Purpose:** Payment records against invoices (separate from booking transactions)

**Fields:**
- `id` (UUID, primary)
- `invoice_id` (UUID, foreign key to invoices)
- `status` (string, default: 'pending') - pending, completed, failed, refunded
- `currency` (string, default: 'UGX')
- `amount` (decimal, default: 0)
- `payment_method` (string, nullable)
- `payment_gateway` (string, nullable) - mtn, airtel, flutterwave, card
- `transaction_id` (string, nullable)
- `processed_at` (timestamp, nullable)
- `gateway_response` (json, nullable)
- `metadata` (json, nullable)
- `created_at`, `updated_at`

**Indexes:**
- `invoice_id`
- `status`
- `transaction_id`

**Relationships:**
- `invoice()` - belongs to Invoice

**Note:** This is separate from the `transactions` table used for customer booking payments.

### BillingEvents Table

**Purpose:** Audit trail of billing events

**Fields:**
- `id` (UUID, primary)
- `subscription_id` (UUID, foreign key to subscriptions)
- `type` (string) - subscription_created, trial_started, subscription_updated, subscription_cancelled, subscription_renewed, payment_succeeded, payment_failed, payment_refunded
- `description` (string)
- `payload` (json, nullable)
- `created_at`, `updated_at`

## Services

### SubscriptionService

**Purpose:** Subscription lifecycle management

**Methods:**
- `getSubscriptionByProvider(string $providerId)` - Get subscription by provider
- `createSubscription(array $data)` - Create new subscription
- `startTrial(string $providerId, string $planId, int $trialDays)` - Start trial period
- `activateSubscription(string $subscriptionId)` - Activate from trial/past_due
- `changePlan(string $subscriptionId, string $newPlanId)` - Upgrade/downgrade计划
- `cancelSubscription(string $subscriptionId, ?string $reason)` - Cancel subscription
- `resumeSubscription(string $subscriptionId)` - Resume cancelled subscription
- `renewSubscription(string $subscriptionId)` - Handle renewal
- `suspendSubscription(string $subscriptionId, ?string $reason)` - Suspend for non-payment
- `checkSubscriptionStatus(string $providerId)` - Get subscription status

**Status Transitions:**
- trialing → active (on payment)
- active → past_due (on payment failure)
- past_due → active (on payment)
- active → cancelled (user action)
- cancelled → active (resume within grace period)
- active → expired (after end date)

### InvoiceService

**Purpose:** Invoice generation and payment management

**Methods:**
- `generateInvoice(array $data)` - Create invoice with auto-numbering
- `generateSubscriptionInvoice(Subscription $subscription)` - Invoice from subscription
- `markAsPaid(string $invoiceId, string $paymentMethod, array $paymentData)` - Mark paid with payment record
- `markAsPending(string $invoiceId, string $paymentMethod)` - Mark pending for cash
- `confirmCashPayment(string $invoiceId, array $confirmationData)` - Admin confirms cash payment
- `markAsFailed(string $invoiceId, string $reason)` - Mark failed and suspend subscription
- `getInvoiceByNumber(string $invoiceNumber)` - Lookup by invoice number
- `getInvoicesByProvider(string $providerId)` - Provider invoices
- `getPendingInvoices()` - Overdue pending invoices

**Invoice Number Format:**
- `INV-YYYYMMDD-####`
- Example: `INV-20260731-0001`

**Cash Payment Flow:**
1. `markAsPending()` - Creates pending payment record
2. Admin receives cash
3. `confirmCashPayment()` - Marks as paid, activates subscription

### BillingService

**Purpose:** Charge calculation and renewal management

**Methods:**
- `calculateCharge(Subscription $subscription)` - Calculate subscription charge
- `processRenewal(string $subscriptionId)` - Generate invoice + renew subscription
- `checkAndProcessDueRenewals()` - Batch renewal processing (for scheduled task)
- `getBillingTimeline(string $subscriptionId)` - Billing event history
- `getUpcomingRenewals(int $days)` - Renewals in next N days
- `getOverdueInvoices()` - Unpaid overdue invoices

**Renewal Process:**
1. Check if subscription is active
2. Generate invoice for renewal
3. Renew subscription (extend renewal date)
4. Record billing event

### PlatformPaymentService

**Purpose:** Orchestrate invoice/payment/subscription flow

**Methods:**
- `initializeSubscriptionPayment(string $invoiceId, string $customerEmail, string $customerName, ?string $customerPhone)` - Initialize payment
- `verifySubscriptionPayment(string $reference)` - Verify payment and activate subscription
- `handleSubscriptionWebhook(array $payload, string $signature)` - Handle webhook callback
- `refundSubscriptionPayment(string $invoiceId, ?float $amount)` - Refund payment

**Responsibility:**
- Orchestration only
- Delegates to InvoiceService and SubscriptionService for business logic
- Reuses existing payment infrastructure (MTN, Airtel, Flutterwave, Card)

## Controllers

### PlatformPaymentController

**Purpose:** HTTP endpoints for platform subscription payments

**Endpoints:**
- `POST /payments/platform/initialize` - Initialize payment for invoice
- `POST /payments/platform/verify` - Verify payment by reference
- `POST /payments/platform/refund` - Refund payment
- `GET /payments/platform/invoices/{invoiceId}/status` - Get invoice payment status
- `POST /payments/platform/cash/initialize` - Initialize cash payment
- `POST /payments/platform/cash/confirm` - Confirm cash payment (admin)

**Cash Payment Flow:**
1. Provider selects "Cash" as payment method
2. `POST /payments/platform/cash/initialize` - Creates pending invoice
3. Provider pays cash in person
4. Admin confirms receipt via `POST /payments/platform/cash/confirm`
5. Invoice marked as paid, subscription activated

## API Routes

### Platform Subscription Payments

```
POST /api/payments/platform/initialize
POST /api/payments/platform/verify
POST /api/payments/platform/refund
GET /api/payments/platform/invoices/{invoiceId}/status
POST /api/payments/platform/cash/initialize
POST /api/payments/platform/cash/confirm
```

### Customer Booking Payments (Separate Domain)

```
POST /api/payment-requests
GET /api/payment-requests
GET /api/payment-requests/{id}
POST /api/payment-requests/{id}/status
POST /api/payment-requests/{id}/cancel
GET /api/payment-requests/{paymentRequest}/check-status
```

**Note:** Customer booking payments use the `Transaction` model and are completely separate from platform subscription payments.

## Migration Files

1. `2026_07_31_200441_add_provider_type_to_plans_table` - Added provider_type to plans
2. `2026_07_31_200528_change_subscriptions_to_provider_id` - Changed salon_id to provider_id
3. `2026_07_31_200656_add_provider_id_to_invoices_table` - Added provider_id to invoices
4. `2026_07_31_200804_rename_payments_to_invoice_payments` - Renamed payments to invoice_payments

## Data Seeding

### PlanSeeder

**Salon Plans:**
- Starter (50,000 UGX/month, 500,000 UGX/year)
- Professional (150,000 UGX/month, 1,500,000 UGX/year)
- Premium (350,000 UGX/month, 3,500,000 UGX/year)
- Enterprise (750,000 UGX/month, 7,500,000 UGX/year)

All plans marked with `provider_type: 'salon'`.

**Future Specialist Plans (to be added):**
- Free (0 UGX/month)
- Pro (50,000 UGX/month)
- Elite (150,000 UGX/month)

## Cash Payment Use Case

### Scenario: Alex (Independent Specialist)

**Problem:** Alex wants Pro plan but doesn't have MTN Merchant account.

**Solution:**
1. Alex selects Pro plan
2. Invoice generated
3. Alex chooses "Cash" as payment method
4. Invoice marked as pending
5. Alex walks into Yo.Salon office
6. Pays 50,000 UGX cash
7. Admin confirms payment via dashboard
8. Invoice marked as paid
9. Subscription activated

**Benefits:**
- No payment gateway required
- Practical for local markets
- Audit trail with confirmation details
- Same activation flow as digital payments

## Future-Proofing

### Future Billable Products

The invoice/payment system can support:

1. **AI Credits**
   - Invoice line item: "AI Credits - 1,000 tokens"
   - One-time purchase or subscription add-on

2. **SMS Bundles**
   - Invoice line item: "SMS Bundle - 10,000 messages"
   - Monthly subscription

3. **Featured Listings**
   - Invoice line item: "Featured Listing - 30 days"
   - One-time purchase

4. **Storage Upgrades**
   - Invoice line item: "Storage Upgrade - 100GB"
   - Monthly subscription

5. **Marketing Campaigns**
   - Invoice line item: "Marketing Campaign - Email blast"
   - One-time purchase

6. **White-Label Services**
   - Invoice line item: "White-Label Setup"
   - One-time setup fee + monthly fee

7. **API Access**
   - Invoice line item: "API Access - 10,000 calls/month"
   - Monthly subscription

**No Schema Changes Required:**
- Use existing `invoices` table
- Add line items to `line_items` JSON field
- Reuse payment infrastructure
- Reuse invoice/payment/subscription flow

## Separation of Concerns

### Customer Booking Payments

**Domain:** Customer → Provider → Payment

**Table:** `transactions`

**Service:** `PaymentManager`, `MTNMomoService`

**Controller:** `PaymentRequestController`, `TransactionController`

**Purpose:** Record customer payments for bookings

### Platform Subscription Payments

**Domain:** Provider → Yo.Salon → Invoice → Payment

**Tables:** `invoices`, `invoice_payments`, `subscriptions`, `plans`

**Services:** `InvoiceService`, `SubscriptionService`, `BillingService`, `PlatformPaymentService`

**Controller:** `PlatformPaymentController`

**Purpose:** Manage provider subscriptions to Yo.Salon platform

**Key Difference:** These are completely separate domains with separate tables, services, and controllers.

## Security Considerations

### Invoice Payment Security
- Payment references stored in invoice metadata
- Webhook signature verification (reuses payment infrastructure)
- Admin confirmation required for cash payments
- Audit trail via BillingEvents

### Subscription Security
- One subscription per provider (unique constraint)
- Status transitions validated
- Grace period for cancellations
- Automatic suspension on payment failure

## Background Jobs

### Renewal Processing

**Command:** `php artisan billing:process-renewals` (to be created)

**Purpose:** Process due subscription renewals

**Logic:**
1. Find subscriptions where `renews_at <= now`
2. Generate invoice for each
3. Renew subscription
4. Record billing events
5. Handle failures gracefully

### Invoice Cleanup

**Command:** `php artisan billing:cleanup-invoices` (to be created)

**Purpose:** Clean up old paid invoices

**Logic:**
1. Archive invoices older than 1 year
2. Move to archive table
3. Keep active invoices accessible

## Testing

### Subscription Flow
1. Provider selects plan
2. Invoice generated
3. Payment initialized
4. Payment verified
5. Subscription activated

### Cash Payment Flow
1. Provider selects plan
2. Invoice generated
3. Cash payment initialized
4. Admin confirms cash receipt
5. Subscription activated

### Renewal Flow
1. Subscription renewal date reached
2. Invoice generated
3. Payment processed
4. Subscription renewed

### Plan Change Flow
1. Provider requests plan change
2. Pro-rated invoice generated (if mid-cycle)
3. Payment processed
4. Plan updated

## Monitoring

### Key Metrics
- Active subscriptions by provider type
- Monthly recurring revenue (MRR)
- Invoice payment success rate
- Cash payment confirmation time
- Churn rate by plan
- Renewal rate by plan

### Alerts
- Failed payment attempts
- Overdue invoices
- High churn rate
- Unusual payment patterns

## Summary

The subscription domain architecture provides:

**Provider-Centric Design:**
- Subscriptions belong to providers, not salons
- Plans support multiple provider types
- Future-proof for new business types

**Invoice-First Approach:**
- Invoices are the source of truth
- Payments are made against invoices
- Subscription activation from invoice payment

**Cash as First-Class Payment:**
- Cash payments fully supported
- Admin confirmation workflow
- Same activation flow as digital payments

**Separated Domains:**
- Customer booking payments separate from subscription payments
- No mixing of transaction types
- Clear separation of concerns

**Future-Proof:**
- Supports future billable products without schema changes
- Generic invoice/payment system
- Reusable payment infrastructure

**Service Layer:**
- Clear separation of concerns
- SubscriptionService for lifecycle
- InvoiceService for billing
- BillingService for renewals
- PlatformPaymentController for orchestration

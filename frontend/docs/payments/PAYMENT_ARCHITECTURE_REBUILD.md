# Payment Architecture Rebuild

## Overview

The payment system has been refactored to align with a new strategic vision: Yo.Salon is an operating system for salons, not a payment processor. The platform now orchestrates payments between customers and providers without holding customer funds.

## Strategic Vision

**Core Business:** Enable confident bookings and provider payments

**Revenue Model:**
- Salon OS: Subscription-based revenue (Starter, Professional, Premium, Enterprise)
- Professional Network: Growth tools for specialists (Free, Pro, Elite)
- Marketplace: Customer acquisition (future advertising/promotions revenue)

**Key Principle:** Yo.Salon records payment events but does not hold customer funds. Payments go directly from customers to providers.

## Changes Made

### Removed Components

**Wallet Accounting System:**
- Removed `WalletService.php`
- Removed wallet balance tracking
- Removed credit/debit transaction logic
- Removed internal fund holding

**Settlement Engine:**
- Removed `Settlement.php` model
- Removed `SettlementController.php`
- Removed settlement calculations and processing

**Duplicate MTN Implementation:**
- Removed `MTNProvider.php`
- Consolidated into `MTNMomoService.php`

### Simplified Components

**Transaction Model:**
- Removed fee breakdown fields:
  - `gross_amount`
  - `gateway_fee`
  - `platform_fee`
  - `tax_amount`
  - `net_amount`
- Now uses simple fields:
  - `amount`
  - `currency`
  - `status`
  - `type`
  - `provider_reference`
  - `internal_reference`
  - `paid_at`

**FeeEngine:**
- Now returns zero fees by default
- Added `calculateFeesWithConfig()` for future fee-based revenue models
- Fees are optional and configurable, not core to payment flow

**WalletController:**
- Converted to loyalty points management only
- Removed wallet balance endpoints
- Removed "Add Funds" functionality
- Kept promo code functionality for loyalty
- Added loyalty tier system (Bronze, Silver, Gold, Platinum)

### Enhanced Components

**PaymentMethod Model:**
- Added `webhook_secret` field for HMAC-SHA256 signature verification
- Added encryption for webhook_secret
- Refactored to provider-specific capabilities declaration
- Updated validation logic

**PaymentRequest Model:**
- Added `idempotency_key` field for idempotent payment requests
- Prevents duplicate payment processing

**MTNMomoService:**
- Implemented proper webhook signature verification using HMAC-SHA256
- Added fallback for signature verification
- Simplified transaction creation (no fee calculation)
- Updated to use webhook secret from PaymentMethod

**PaymentManager:**
- Updated `handleWebhook()` to accept webhook_secret parameter
- Passes secret to provider for signature verification

**PaymentMethodController:**
- Updated validation to include `webhook_secret`
- Simplified `testConnection()` to only support MTN MoMo
- Removed legacy provider instances

**PaymentRequestController:**
- Added idempotency key validation before amount validation
- Checks for existing payment requests with same idempotency key
- Simplified transaction creation on payment completion
- Updated payment dispatch logic

**TransactionController:**
- Removed fee calculation in manual transaction recording
- Updated summary metrics to use simplified fields

### Security Enhancements

**Webhook Signature Verification:**
- HMAC-SHA256 signature verification for MTN MoMo webhooks
- Webhook secret stored encrypted in PaymentMethod
- Fallback mechanism for signature verification
- Prevents webhook spoofing

**Idempotency Keys:**
- Prevents duplicate payment requests
- Client-generated unique identifier
- Server validates before processing

**Rate Limiting:**
- Added to all payment endpoints
- Payment methods: 30 requests/minute
- Payment requests (create): 20 requests/minute
- Payment requests (read): 60 requests/minute
- Transactions: 60 requests/minute
- Promo code apply: 10 requests/minute
- Wallet/loyalty endpoints: 60 requests/minute

### Background Jobs

**Expired Payment Request Cleanup:**
- Existing command: `php artisan payments:expire-pending`
- Marks expired payment requests as 'expired'
- Runs via scheduled task

## Payment Flow

### Customer Booking Payment Flow

```
Customer → Provider → Payment
```

1. Customer initiates booking
2. PaymentRequest created with idempotency key
3. Payment dispatched to provider (MTN MoMo)
4. Webhook received with signature verification
5. Transaction recorded (simple amount, no fees)
6. Booking confirmed

### Platform Subscription Payment Flow

```
Provider → Yo.Salon → Invoice → Payment
```

1. Provider selects plan
2. Invoice generated
3. Payment initialized (MTN, Airtel, Flutterwave, Card, or Cash)
4. Payment verified
5. Subscription activated

**Note:** This is separate from customer booking payments and uses the subscription domain architecture.

## API Changes

### Wallet Endpoints (Updated)

**Removed:**
- `POST /portal/wallet/add-funds` - wallet balance functionality

**Updated:**
- `GET /portal/wallet` - now returns loyalty points and tier
- `GET /portal/wallet/transactions` - now returns loyalty point history
- `POST /portal/wallet/promo/apply` - unchanged (promo codes for loyalty)

### Payment Endpoints (Rate Limited)

**Payment Methods:**
- `GET/POST/PUT/DELETE /api/payment-methods` - 30/minute
- `POST /api/payment-methods/test-connection` - 10/minute
- `POST /api/payment-methods/{paymentMethod}/verify-credentials` - 10/minute

**Payment Requests:**
- `POST /api/payment-requests` - 20/minute (idempotency check)
- `GET /api/payment-requests` - 60/minute
- `GET /api/payment-requests/{id}` - 60/minute
- `POST /api/payment-requests/{id}/status` - 30/minute
- `POST /api/payment-requests/{id}/cancel` - 20/minute
- `GET /api/payment-requests/{paymentRequest}/check-status` - 60/minute

**Transactions:**
- `GET/POST /api/transactions` - 60/minute
- `GET /api/transactions/summary` - 60/minute

## Frontend Changes

### Wallet Page Refactor

**Changed from:** "Wallet" with balance management
**Changed to:** "Loyalty & Rewards" with points management

**Removed:**
- Wallet balance display
- "Add Funds" button
- "Payment Methods" tab
- Transaction history (wallet transactions)

**Added:**
- Loyalty points display
- Loyalty tier system (Bronze, Silver, Gold, Platinum)
- Tier benefits display
- Progress bar to next tier
- Loyalty point history
- Kept promo codes functionality
- Kept gift cards functionality

## Database Schema Changes

### Transaction Model

**Removed Fields:**
- `gross_amount`
- `gateway_fee`
- `platform_fee`
- `tax_amount`
- `net_amount`
- `settlement_id`

**Kept Fields:**
- `amount`
- `currency`
- `status`
- `type`
- `provider_reference`
- `internal_reference`
- `paid_at`

### PaymentMethod Model

**Added Fields:**
- `webhook_secret` (encrypted)

### PaymentRequest Model

**Added Fields:**
- `idempotency_key`

## Migration Files

- `2026_07_31_000000_update_payment_requests_status_enum.php`
- `2026_07_29_add_merchant_credentials_to_payment_methods.php`
- `2026_07_30_update_payment_methods_type_field.php`

## Service Files

### Created
- `InvoiceService.php` - Invoice generation and payment management
- `BillingService.php` - Charge calculation and renewal management

### Updated
- `SubscriptionService.php` - Provider-centric subscription management
- `PlatformPaymentService.php` - Invoice/payment/subscription orchestration
- `MTNMomoService.php` - Webhook signature verification
- `PaymentManager.php` - Webhook secret support
- `FeeEngine.php` - Optional fee calculation

### Removed
- `WalletService.php` - Wallet accounting
- `MTNProvider.php` - Duplicate MTN implementation

## Controller Files

### Updated
- `WalletController.php` - Loyalty points only
- `PaymentMethodController.php` - Webhook secret support
- `PaymentRequestController.php` - Idempotency key validation
- `TransactionController.php` - Simplified transaction recording
- `PlatformPaymentController.php` - Cash payment endpoints

### Removed
- `SettlementController.php` - Settlement management

## Future Considerations

### Fee-Based Revenue
The `FeeEngine` is now optional and can be enabled later when implementing:
- Transaction fees for premium features
- Marketplace commission on bookings
- Payment processing fees

### Additional Payment Providers
The architecture supports adding new providers:
- Airtel Money (already planned)
- Flutterwave (already planned)
- Pesapal (already planned)
- Card payments (Visa, Mastercard)
- Bank transfers

### Subscription Payments
Platform subscription payments use the separate subscription domain architecture (see `SUBSCRIPTION_DOMAIN_ARCHITECTURE.md`).

## Testing

### End-to-End Payment Flow
1. Create booking
2. Generate payment request with idempotency key
3. Process payment via MTN MoMo
4. Verify webhook signature
5. Record transaction
6. Confirm booking

### Cash Payment Flow
1. Generate invoice
2. Initialize cash payment (pending)
3. Admin confirms cash receipt
4. Invoice marked as paid
5. Subscription activated

## Security Checklist

- [x] Webhook signature verification (HMAC-SHA256)
- [x] Idempotency keys for payment requests
- [x] Rate limiting on all payment endpoints
- [x] Encrypted credential storage
- [x] Encrypted webhook secret storage
- [x] Background job for expired payment cleanup

## Migration Notes

### Breaking Changes
- Wallet balance functionality removed
- Settlement engine removed
- Transaction fee breakdown removed
- Wallet endpoints changed to loyalty endpoints

### Data Migration
- Existing transactions retain basic fields
- Fee breakdown data lost (acceptable per requirements)
- Wallet balances not migrated (no longer supported)

### Rollback Plan
If needed, revert migrations in reverse order:
1. Remove webhook_secret from PaymentMethod
2. Remove idempotency_key from PaymentRequest
3. Restore fee breakdown fields to Transaction
4. Restore WalletService and Settlement components

## Summary

The payment system has been successfully rebuilt to align with the strategic vision:
- Yo.Salon orchestrates payments, doesn't hold funds
- Simplified transaction recording
- Enhanced security (webhook signatures, idempotency, rate limiting)
- Provider-centric subscription billing (separate domain)
- Loyalty points instead of wallet balance
- Future-proof for additional payment providers and fee models

# Customer Acquisition Journeys

## Core Principles

> **Authentication identifies people. Business workflows establish relationships.**

> **A Portal Account must always be attached to a Customer. Customer records are created or resolved by a business interaction with a salon—not by authentication alone.**

### Key Distinctions

- **Customer** = the business entity (the person the salon serves)
- **Portal Account** = an optional authentication identity
- **Authentication never creates customers. Business events create or resolve customers.**

---

## Service Ownership

### PortalAuthService
Responsible for:
- Registering portal credentials
- Logging in
- Password reset
- Email verification
- Linking a Portal Account to an existing Customer
- Issuing authentication tokens

**Must never decide when a Customer should exist.**

### CustomerResolver
Responsible for:
- Resolving existing customers
- Finding customers by phone/email
- Preventing duplicates
- Matching by phone/email according to business rules

### CustomerService
Responsible for:
- Creating customers when required by a business event
- Updating customer information
- Managing salon relationships
- Incrementing visit counts

**Only called by business workflows, never by authentication services alone.**

### BookingService
Responsible for:
- Creating bookings
- Creating or resolving the Customer
- Optionally creating and linking a Portal Account if user chooses "Create an account"

**Orchestrates Journey 4.**

---

## The 5 Official Customer Acquisition Journeys

### Journey 1: Walk-in

```
Reception
    ↓
Customer
    ↓
Optional Portal Later
```

**Implementation:**
- Reception creates customer via CustomerService
- No portal account created
- Customer can later register for portal via Journey 5

**Service Flow:**
```php
CustomerService::createWithSalon($data, $salonId)
```

---

### Journey 2: Reception Manually Creates Customer

```
Reception
    ↓
Customer
    ↓
Portal Optional
```

**Implementation:**
- Reception manually adds customer in Salon OS
- No portal account created
- Customer can later register for portal via Journey 5

**Service Flow:**
```php
CustomerService::createWithSalon($data, $salonId)
```

---

### Journey 3: Guest Booking

```
Website
    ↓
Booking
    ↓
Customer
    ↓
Portal Optional
```

**Implementation:**
- User books as guest on website
- BookingService resolves or creates customer
- No portal account created
- Customer can later register for portal via Journey 5

**Service Flow:**
```php
BookingService::createGuestBooking($data)
```

**Internal Flow:**
1. CustomerService::resolveOrCreateForBusiness()
2. Booking::create()
3. Return booking

---

### Journey 4: Booking + Create Account

```
Website
    ↓
Booking
    ↓
Customer
    ↓
Portal Account
    ↓
Linked
```

**Implementation:**
- User books on website and checks "Create a Client Portal Account"
- BookingService orchestrates the entire flow
- Customer created by booking (business event)
- Portal account linked after customer exists

**Service Flow:**
```php
BookingService::createBookingWithAccount($data)
```

**Internal Flow:**
1. CustomerService::resolveOrCreateForBusiness()
2. Booking::create()
3. PortalAccount::create() (if requested)
4. Return booking, customer, and portal account

**UI Flow:**
```
Choose Service
    ↓
Choose Stylist
    ↓
Choose Time
    ↓
Customer Details
    ↓
☐ Create a Client Portal Account
    ↓
Confirm Booking
```

---

### Journey 5: Salon Invitation

```
Salon
    ↓
Invitation
    ↓
Existing Customer
    ↓
Portal Account
```

**Implementation:**
- Salon sends invitation to existing customer
- Customer accepts invitation and creates portal account
- Portal account linked to existing customer
- No new customer created

**Service Flow:**
```php
PortalAccountController::acceptInvitation($data)
```

**Internal Flow:**
1. Find customer by invitation token
2. PortalAccount::create()
3. Link to existing customer
4. Clear invitation token

---

## Future-Proof Architecture

The same CustomerResolver should support future channels:

- WhatsApp booking
- Instagram booking
- Google Reserve
- QR check-in
- Marketplace
- AI assistant
- Kiosk booking

**Every one of those should call:**
```
CustomerResolver
    ↓
Customer
    ↓
Business Flow
```

**Never create customers independently.**

---

## Duplicate Prevention

All business workflows must use `CustomerResolver` to find or resolve customers:

```php
CustomerResolver::resolveOrCreateForSalon($data, $salonId, $createIfNotFound)
```

This ensures:
- Phone is primary identifier
- Email is secondary identifier
- Existing customers are found before creating new ones
- Salon relationships are properly managed

If duplicates somehow get created, use:
```php
CustomerResolver::mergeCustomers($primary, $duplicate)
```

---

## Portal Registration Context

A portal account without a salon relationship has no meaning.

Registration entry points must always begin with context:

- Scan QR code
- Enter invitation code
- Follow booking confirmation link
- Search for your salon

**Direct portal registration without salon context is not allowed.**

---

## Summary

| Journey | Creates Customer | Creates Portal | Service |
|---------|----------------|----------------|---------|
| Walk-in | Yes | No | CustomerService |
| Reception | Yes | No | CustomerService |
| Guest Booking | Yes | No | BookingService |
| Booking + Account | Yes | Yes | BookingService |
| Invitation | No | Yes | PortalAuthService |

**All journeys converge on a single canonical Customer record.**

**Authentication never creates customers. Business events create or resolve customers.**

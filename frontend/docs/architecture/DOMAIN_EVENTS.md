# Domain Events

This document defines the event contracts between domains. It serves as the single source of truth for what events each domain publishes and consumes.

## Event Naming Convention

Events use descriptive past-tense names:
- `BookingCreated` (not `CreateBooking`)
- `PaymentCaptured` (not `CapturePayment`)

This indicates an event that has already occurred.

---

## Identity Domain

### Publishes

```
UserRegistered
UserVerified
UserDeactivated
PasswordChanged
EmailVerified
```

### Consumes

```
None (Identity is foundational)
```

---

## Customer Domain

### Publishes

```
CustomerCreated
CustomerProfileUpdated
CustomerPreferencesChanged
CustomerRelationshipCreated
CustomerRelationshipUpdated
```

### Consumes

```
UserRegistered (from Identity)
BookingCompleted (from Booking)
ReviewSubmitted (from Booking)
```

---

## Provider Domain

### Publishes

```
ProviderCreated
ProviderVerified
ProviderProfileUpdated
ProviderActivated
ProviderDeactivated
SpecialistAddedToProvider
SpecialistRemovedFromProvider
```

### Consumes

```
UserRegistered (from Identity)
BookingCompleted (from Booking)
```

---

## Specialist Domain

### Publishes

```
SpecialistProfileUpdated
SpecialistVerificationSubmitted
SpecialistVerified
SpecialistCareerEventAdded
SpecialistAssignmentCreated
SpecialistAssignmentUpdated
SpecialistFollowed
SpecialistFavorited
```

### Consumes

```
UserRegistered (from Identity)
BookingCreated (from Booking)
BookingCompleted (from Booking)
ReviewSubmitted (from Booking)
SettlementClosed (from Finance)
```

---

## Booking Domain

### Publishes

```
BookingCreated
BookingConfirmed
BookingCompleted
BookingCancelled
BookingRescheduled
BookingNoShow
BookingReminderSent
```

### Consumes

```
CustomerCreated (from Customer)
SpecialistAssignmentCreated (from Specialist)
AvailabilitySlotBooked (from Availability)
PaymentCaptured (from Payments)
```

---

## Payments Domain

### Publishes

```
PaymentInitiated
PaymentCaptured
PaymentFailed
PaymentRefunded
PaymentMethodAdded
PaymentMethodRemoved
```

### Consumes

```
BookingCreated (from Booking)
BookingConfirmed (from Booking)
BookingCancelled (from Booking)
```

---

## Finance Domain

### Publishes

```
FinancialTransactionPosted
FinancialTransactionVoided
JournalPosted
JournalVoided
LedgerEntryCreated
RevenueDistributed
SettlementCreated
SettlementClosed
PayoutRecorded
PayoutCompleted
PayoutFailed
```

### Consumes

```
PaymentCaptured (from Payments)
BookingCompleted (from Booking)
BookingCancelled (from Booking)
RefundRequested (from Booking)
```

---

## Commerce Domain

### Publishes

```
ProductCreated
ProductUpdated
ProductDiscontinued
BrandCreated
BrandUpdated
CategoryCreated
CategoryUpdated
ProductVariantCreated
ProductVariantUpdated
ProductImageAdded
ProductAttributeAdded
```

### Consumes

```
None (Commerce is foundational for Inventory, Procurement, Retail)
```

---

## Availability Domain

### Publishes

```
AvailabilitySlotCreated
AvailabilitySlotBooked
AvailabilitySlotCancelled
SpecialistAssignmentCreated
SpecialistAssignmentUpdated
SpecialistAssignmentRemoved
```

### Consumes

```
BookingCreated (from Booking)
BookingCancelled (from Booking)
SpecialistProfileUpdated (from Specialist)
```

---

## Analytics Domain

### Consumes

```
BookingCreated (from Booking)
BookingCompleted (from Booking)
BookingCancelled (from Booking)
PaymentCaptured (from Payments)
PaymentFailed (from Payments)
RevenueDistributed (from Finance)
SettlementClosed (from Finance)
CustomerCreated (from Customer)
CustomerRelationshipCreated (from Customer)
SpecialistFollowed (from Specialist)
SpecialistFavorited (from Specialist)
```

---

## Intelligence Domain

### Consumes

```
BookingCompleted (from Booking)
RevenueDistributed (from Finance)
ReviewSubmitted (from Booking)
CustomerProfileUpdated (from Customer)
SpecialistProfileUpdated (from Specialist)
ProductCreated (from Commerce)
ProductViewed (from Commerce)
```

---

## Notifications Domain

### Publishes

```
NotificationSent
NotificationRead
NotificationDismissed
```

### Consumes

```
BookingCreated (from Booking)
BookingConfirmed (from Booking)
BookingReminderSent (from Booking)
BookingCancelled (from Booking)
PaymentCaptured (from Payments)
PaymentFailed (from Payments)
SettlementClosed (from Finance)
PayoutCompleted (from Finance)
SpecialistVerificationSubmitted (from Specialist)
SpecialistVerified (from Specialist)
```

---

## Event Flow Examples

### Booking → Finance Flow

```
BookingCreated
    ↓
PaymentCaptured
    ↓
FinancialTransactionPosted
    ↓
JournalPosted
    ↓
LedgerEntryCreated
    ↓
RevenueDistributed
    ↓
SettlementCreated
```

### Settlement → Notification Flow

```
SettlementClosed
    ↓
NotificationSent (to Specialist)
```

### Booking → Analytics Flow

```
BookingCompleted
    ↓
Analytics (consumes for reporting)
```

### Booking → Intelligence Flow

```
BookingCompleted
    ↓
Intelligence (consumes for recommendations)
```

---

## Event Payload Structure

All events should follow this structure:

```typescript
{
  eventId: string;      // UUID
  eventType: string;    // Event class name
  timestamp: string;   // ISO 8601
  version: string;     // Event version (e.g., "1.0.0")
  source: string;      // Domain that published
  data: object;        // Event-specific data
  metadata?: object;   // Additional context
}
```

### Example: BookingCompleted

```typescript
{
  eventId: "550e8400-e29b-41d4-a716-446655440000",
  eventType: "BookingCompleted",
  timestamp: "2026-08-06T14:00:00Z",
  version: "1.0.0",
  source: "Booking",
  data: {
    bookingId: "booking-uuid",
    customerId: "customer-uuid",
    specialistId: "specialist-uuid",
    salonId: "salon-uuid",
    serviceId: "service-uuid",
    completedAt: "2026-08-06T14:00:00Z",
    amount: 25000,
    currency: "UGX"
  },
  metadata: {
    correlationId: "correlation-uuid",
    requestId: "request-uuid"
  }
}
```

---

## Event Versioning

When event structure changes:

1. Increment version number
2. Maintain backward compatibility for at least one version
3. Document breaking changes
4. Update consuming domains

Example:
- `BookingCompleted v1.0.0` → `BookingCompleted v1.1.0`
- Both versions supported during transition period
- Consumers updated to v1.1.0
- v1.0.0 deprecated after transition

---

## Event Delivery Guarantees

### At-Least-Once Delivery

Events may be delivered multiple times. Consumers must be idempotent.

### Ordering

Events from the same source are delivered in order of occurrence.

### Dead Letter Queue

Failed events go to DLQ for investigation and retry.

---

## Event Security

### Authentication

Event consumers must authenticate with valid credentials.

### Authorization

Event consumers can only consume events they have permission to access.

### Encryption

Sensitive data in event payloads must be encrypted.

---

## Event Monitoring

### Metrics to Track

- Event publishing rate
- Event consumption rate
- Event processing latency
- Event failure rate
- DLQ size

### Alerts

- High event failure rate
- DLQ backlog growing
- Event processing latency exceeding threshold

---

## Event Testing

### Unit Tests

- Event payload validation
- Event serialization/deserialization
- Event version compatibility

### Integration Tests

- Event publishing
- Event consumption
- Event flow end-to-end

### Contract Tests

- Event structure validation
- Event version compatibility
- Consumer contract validation

---

## Notes

This document is the contract between domains. Any changes to events must:

1. Update this document
2. Update event version
3. Notify consuming domains
4. Maintain backward compatibility during transition

This ensures all teams know how data flows without reading the code.

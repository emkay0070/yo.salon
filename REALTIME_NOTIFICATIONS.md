# Real-Time Notification System - Architecture & Future Implementation

## Overview

This document describes the real-time notification and activity timeline system built for Yo.Salon. The system was designed to provide instant updates to customers, salon owners, and staff regarding booking events and payment statuses using Laravel Reverb WebSocket broadcasting.

**Status**: Currently disabled (polling fallback) pending domain purchase and Reverb configuration.

---

## What Was Built

### 1. Event-Driven Architecture

A comprehensive domain event layer was implemented to standardize all business actions as events:

- **BookingCreated**: Triggered when a new booking is created
- **PaymentConfirmed**: Triggered when a payment is successfully processed
- **BookingConfirmed**: Triggered when a booking is confirmed
- **StaffAssigned**: Triggered when staff is assigned to a booking
- **BookingCancelled**: Triggered when a booking is cancelled
- **AppointmentStarted**: Triggered when an appointment begins
- **AppointmentCompleted**: Triggered when an appointment finishes

### 2. Separated Listeners by Responsibility

Each event has multiple listeners for different concerns:

```
BookingCreated Event
├── CreateBookingNotification (creates notifications)
├── AddBookingActivity (adds to activity timeline)
└── BroadcastNotification (broadcasts via WebSocket)
```

This separation ensures:
- Single responsibility per listener
- Easy to add new listeners without modifying existing ones
- Testability of individual components

### 3. Enhanced Notification System

The notification schema was extended with rich metadata:

- **category**: payment, booking, staff, system
- **priority**: low, medium, high, urgent
- **icon**: emoji/icon identifier for UI
- **action_url**: deep link to relevant page
- **data**: structured JSON payload

### 4. Activity Timeline

Every booking action is recorded in a timeline:
- Booking created
- Deposit paid
- Staff assigned
- Appointment started
- Payment completed
- Appointment finished

### 5. Live Dashboard Widgets

Real-time statistics displayed in the salon dashboard:
- New bookings today
- Payments received today
- Awaiting approval count
- Customers waiting
- Revenue today

### 6. Redis Caching

Dashboard stats are cached for 5 minutes and invalidated when events occur, providing:
- Reduced database load
- Faster response times
- Automatic cache invalidation on relevant events

### 7. WebSocket Security

Channel authorization was implemented in `routes/channels.php`:
- `salon.{id}` - Only accessible to salon members
- `customer.{id}` - Only accessible to that customer
- `staff.{id}` - Only accessible to that staff member
- `presence.salon.{id}` - Shows online users

### 8. Frontend WebSocket Hook

A reusable `useReverb` hook was created with:
- Automatic connection management
- Exponential backoff reconnection (up to 10 attempts)
- Message parsing and event handling
- Connection status tracking

### 9. Customer Portal Booking Tracker

An Uber Eats-style progress tracker showing:
- Booking confirmed
- Deposit paid
- Staff assigned
- Appointment in progress
- Completed

### 10. MTN Payment Integration

The payment webhook was integrated with the event system:
- MTN webhook → Payment status update → PaymentConfirmed event → Notifications + Timeline + Dashboard update

---

## Why This Matters

### 1. Competitive Advantage

Real-time updates are now expected in modern booking systems:
- Customers expect instant confirmation
- Salons need immediate visibility into bookings
- Staff need instant assignment notifications

### 2. Operational Efficiency

- No more refreshing pages to check for updates
- Instant notifications reduce no-shows
- Real-time staff assignment improves scheduling
- Live revenue tracking enables better decisions

### 3. Scalability

The event-driven architecture scales well:
- New features can plug into the event stream
- No need to modify existing code for new notifications
- Queue-based processing prevents blocking
- Redis caching reduces database load

### 4. User Experience

- Instant feedback builds trust
- Progress tracking reduces anxiety
- Actionable notifications improve workflow
- Multi-channel delivery (future: Push, Email, SMS, WhatsApp)

---

## Current Status

### Disabled Components

The following components are currently disabled due to lack of domain and Reverb configuration:

1. **WebSocket Broadcasting**: Events don't broadcast (ShouldBroadcast interface removed)
2. **useReverb Hook**: Not used in production (reverted to polling)
3. **Channel Authorization**: Not actively used

### Active Components

These components are working with polling:

1. **Event System**: Events still dispatch and trigger listeners
2. **Notifications**: Created and stored in database
3. **Activity Timeline**: Updated on events
4. **Dashboard Stats**: Cached and invalidated on events
5. **MTN Payment Integration**: Fully functional with webhook

### Polling Configuration

Frontend currently polls every 10 seconds:
- `LiveDashboardWidgets`: Polls `/dashboard/live-stats`
- `NotificationCenter`: Polls `/notifications`

---

## How to Re-Enable

### Step 1: Purchase Domain

Buy a domain for your API, e.g., `api.yosalon.com`

### Step 2: Configure Nginx

Add Nginx configuration to proxy Reverb:

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}
```

### Step 3: Configure Reverb

Set environment variables in production `.env`:

```env
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

### Step 4: Configure Frontend

Set Vercel environment variables:

```env
NEXT_PUBLIC_REVERB_APP_KEY=your-app-key
NEXT_PUBLIC_REVERB_HOST=api.yosalon.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

### Step 5: Re-enable Broadcasting

Add `ShouldBroadcast` interface back to events:

```php
class BookingCreated implements ShouldBroadcast
{
    // ... existing code
    public function broadcastOn() { /* ... */ }
    public function broadcastAs() { /* ... */ }
}
```

### Step 6: Update Frontend Components

Replace polling with `useReverb` hook:

```typescript
// LiveDashboardWidgets.tsx
useReverb('salon.{id}', {
    onMessage: (data) => {
        if (data.event === 'booking.created' || data.event === 'payment.confirmed') {
            fetchStats();
        }
    }
});

// NotificationCenter.tsx
useReverb('salon.{id}', {
    onMessage: (data) => {
        if (data.event === 'booking.created' || data.event === 'payment.confirmed') {
            fetchNotifications();
        }
    }
});
```

### Step 7: Deploy and Test

1. Deploy backend changes
2. Deploy frontend changes
3. Test booking creation
4. Verify real-time updates
5. Test payment flow

---

## Future Enhancements

### 1. Notification Grouping

Group similar notifications:

```
3 New Bookings
2 Payments Received
```

### 2. Actionable Notifications

Add workflow buttons to notifications:

- Accept/Decline booking
- Message customer
- Confirm appointment

### 3. Salon-Wide Activity Feed

Dedicated page showing all salon activity:

```
Operations
Today
09:10 Grace booked Hair Styling
09:11 Deposit received
09:12 Sarah assigned
09:35 Appointment started
10:20 Completed
```

### 4. Live Salon Status

Customer portal feature showing:
- Stylist availability
- Current queue length
- Estimated wait time

### 5. Multi-Channel Delivery

- Push notifications (mobile)
- Email confirmations
- SMS updates
- WhatsApp messages

### 6. Queue-Based Processing

Move listeners to queues for better performance:

```
BookingCreated → Queue → Notification → Broadcast → Email → SMS
```

### 7. Analytics Dashboard

Leverage events for:
- Conversion funnel visualization
- Peak booking times
- Staff performance metrics
- Revenue trends

---

## Files Reference

### Backend

- `backend/app/Events/BookingCreated.php` - Booking creation event
- `backend/app/Events/PaymentConfirmed.php` - Payment confirmation event
- `backend/app/Listeners/CreateBookingNotification.php` - Creates notifications
- `backend/app/Listeners/AddBookingActivity.php` - Adds timeline entries
- `backend/app/Listeners/BroadcastNotification.php` - Broadcasts events
- `backend/app/Listeners/CreatePaymentNotification.php` - Payment notifications
- `backend/app/Listeners/AddPaymentActivity.php` - Payment timeline entries
- `backend/app/Http/Controllers/Api/V1/DashboardController.php` - Live stats API
- `backend/routes/channels.php` - WebSocket channel authorization
- `backend/config/reverb.php` - Reverb configuration

### Frontend

- `frontend/src/hooks/useReverb.ts` - WebSocket connection hook
- `frontend/src/components/Notifications/LiveDashboardWidgets.tsx` - Live stats widgets
- `frontend/src/components/Notifications/NotificationCenter.tsx` - Notification center
- `frontend/src/components/Notifications/ActivityTimeline.tsx` - Timeline component
- `frontend/src/components/Notifications/BookingTracker.tsx` - Customer progress tracker
- `frontend/.env的生产.example` - Production environment template

---

## Migration Checklist

When ready to re-enable real-time features:

- [ ] Purchase domain (api.yosalon.com)
- [ ] Configure SSL certificate
- [ ] Set up Nginx proxy for Reverb
- [ ] Configure Reverb environment variables
- [ ] Add ShouldBroadcast interface to events
- [ ] Update frontend environment variables
- [ ] Replace polling with useReverb hook
- [ ] Test WebSocket connection
- [ ] Test booking creation flow
- [ ] Test payment confirmation flow
- [ ] Verify notifications appear instantly
- [ ] Verify dashboard updates instantly
- [ ] Monitor WebSocket connection stability

---

## Conclusion

The real-time notification system represents a significant architectural improvement for Yo.Salon. It transforms the platform from a simple booking system into a true operating system for salons.

The infrastructure is 90% complete and ready to be activated when the business justifies the investment in a domain and proper WebSocket infrastructure. The event-driven architecture will continue to provide value even without WebSockets, as it enables clean separation of concerns and makes the system more maintainable and extensible.

**Recommendation**: Re-enable this system once Yo.Salon has:
1. 10+ active salons
2. 100+ daily bookings
3. Customer feedback requesting real-time updates
4. Budget for domain and infrastructure

The migration will be straightforward since all the code is already in place and tested.

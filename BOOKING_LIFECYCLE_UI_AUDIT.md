# Booking Lifecycle UI Audit

**Date:** October 6, 2026  
**Scope:** Frontend booking lifecycle UI components  
**Purpose:** Identify what UI exists and what needs to be implemented for the booking lifecycle

---

## Executive Summary

The frontend has solid booking management UI but is missing critical components for the new lifecycle architecture:
- **No operational state UI** (grace period, needs resolution, overdue display)
- **No resolution workflow UI** (mark no-show with reason codes, follow-up)
- **No "Needs Attention" view** for yesterday's unresolved bookings
- **Missing status states** (arrived, waiting, no_show, rescheduled)
- **No activity history timeline**

---

## A. Existing Booking UI Components

### 1. Booking Management Page
**File:** `frontend/src/app/[slug]/(business)/bookings/page.tsx`

**Features:**
- Timeline view (day/week/month calendar)
- List view
- Drag-and-drop rescheduling
- Booking details drawer
- Quick actions (new booking, walk-in, reschedule, cancel, assign staff)
- Status filtering (today only)
- 15-second auto-refresh

**Status handling:**
```typescript
status: string; // confirmed, checked_in, in_service, completed, cancelled
```

---

### 2. BookingDetailsDrawer
**File:** `frontend/src/components/booking/BookingDetailsDrawer.tsx`

**Current STATUS_STYLE mapping:**
```typescript
confirmed:   { label: 'Confirmed',   chip: 'bg-blue-500/20...',    dot: 'bg-blue-500'    },
checked_in:  { label: 'Checked In',  chip: 'bg-emerald-500/20...', dot: 'bg-emerald-500' },
in_service:  { label: 'In Service',  chip: 'bg-gold/20...',        dot: 'bg-gold'       },
completed:   { label: 'Completed',   chip: 'bg-zinc-700/40...',    dot: 'bg-zinc-500'    },
cancelled:   { label: 'Cancelled',   chip: 'bg-red-500/20...',     dot: 'bg-red-500'     },
```

**Actions:**
- onCheckIn (manager/receptionist)
- onStartService (employee/manager)
- onCompleteService (employee/manager)
- onReschedule (manager/receptionist/owner)
- onCancel (manager/receptionist/owner)
- onAssignStaff (manager/owner)

**Missing statuses:**
- arrived (not in STATUS_STYLE)
- waiting (not in STATUS_STYLE)
- no_show (not in STATUS_STYLE)
- rescheduled (not in STATUS_STYLE)

---

### 3. TimelineView
**File:** `frontend/src/components/booking/TimelineView.tsx`

**Current getStatusStyle mapping:**
```typescript
confirmed:  'bg-emerald-500/10 border-emerald-500/25 text-emerald-400',
checked_in: 'bg-blue-500/10 border-blue-500/25 text-blue-400',
in_service: 'bg-gold/10 border-gold/25 text-gold',
completed:  'bg-card border-border-light text-text-secondary',
cancelled:  'bg-red-500/10 border-red-500/25 text-red-400',
```

**Features:**
- Single-column timeline (mobile)
- Multi-column timeline (desktop)
- Staff switching
- Hour-by-hour slots (9 AM → 8 PM)
- Booking cards with status styling

**Missing:**
- Operational state indicators
- Grace period countdown
- Overdue minutes display

---

### 4. QuickActions
**File:** `frontend/src/components/booking/QuickActions.tsx`

**Actions:**
- New Booking
- Walk-in
- Reschedule
- Cancel
- Assign Staff

**Missing:**
- Mark as No-show
- Mark as Arrived (late)
- Resolve Booking
- Initiate Follow-up

---

### 5. RescheduleModal
**File:** `frontend/src/components/booking/RescheduleModal.tsx`

**Features:**
- Date picker
- Time picker
- Current appointment display
- Direct booking update via API

**Missing:**
- Resolution tracking (original booking marked as rescheduled)
- Link to new booking
- Reason code selection

---

### 6. Other Components

| Component | Purpose | Status |
|-----------|---------|--------|
| WalkInModal | Create walk-in bookings | ✅ Exists |
| AssignStaffModal | Assign staff to booking | ✅ Exists |
| CalendarPicker | Date selection | ✅ Exists |
| TimePicker | Time selection | ✅ Exists |
| FloatingQuickActions | Floating action buttons | ✅ Exists |
| BookingStats | Booking statistics | ✅ Exists |
| BookingSteps | Booking flow steps | ✅ Exists |
| BookingSummary | Booking summary | ✅ Exists |
| AppointmentCard | Individual appointment card | ✅ Exists |

---

## B. Missing UI Components

### 1. Operational State Display

**What's needed:**
- Visual indicator for operational state (upcoming, due_soon, grace_period,需要_resolution, resolved)
- Grace period countdown (e.g., "4 min overdue" or "2 min remaining")
- Overdue minutes calculation display

**Where to add:**
- TimelineView booking cards
- BookingDetailsDrawer header
- List view items

**Example UI:**
```typescript
// Grace period countdown
{booking.operational_state === 'grace_period' && (
  <div className="text-amber-400 text-xs">
    {getGraceRemainingMinutes(booking)} min remaining
  </div>
)}

// Overdue display
{booking.operational_state === 'needs_resolution' && (
  <div className="text-red-400 text-xs">
    {getOverdueMinutes(booking)} min overdue
  </div>
)}
```

---

### 2. Resolution Modal

**What's needed:**
- Modal to mark booking as resolved with outcome selection
- Reason code dropdown (CUSTOMER_DID_NOT_ARRIVE, CUSTOMER_CANCELLED, etc.)
- Notes field
- Follow-up checkbox and date picker
- Actor tracking (auto-populate from current user)

**Component name:** `ResolutionModal.tsx`

**Props:**
```typescript
interface ResolutionModalProps {
  isOpen: boolean;
  onClose: () => void;
  booking: Booking;
  onSuccess: () => void;
}
```

**Outcome options:**
- NO_SHOW
- CANCELLED
- RESCHEDULED
- COMPLETED_LATE
- CUSTOMER_ARRIVED

**Reason codes:**
- CUSTOMER_DID_NOT_ARRIVE
- CUSTOMER_CANCELLED
- CUSTOMER_REQUESTED_RESCHEDULE
- CUSTOMER_COULD_NOT_BE_REACHED
- PROVIDER_UNAVAILABLE
- SPECIALIST_UNAVAILABLE
- LOCATION_CLOSED
- TECHNICAL_ISSUE
- OTHER

---

### 3. Needs Resolution View

**What's needed:**
- New page/section for bookings in `needs_resolution` state
- Filter by date (yesterday, last 7 days, all)
- Group by date
- Quick resolve action
- Follow-up status display

**Route:** `/bookings/needs-resolution` or section within `/bookings`

**Example UI structure:**
```
Needs Resolution
─────────────────
Yesterday (Oct 5)

09:30  James      Missed
       Reason not recorded
       [Resolve]

14:00  Brian      Grace period expired
       [Resolve]

Last 7 Days

[...]
```

---

### 4. Activity History Timeline

**What's needed:**
- Timeline view of booking activities
- Actor display (who performed the action)
- Timestamp display
- Reason codes for resolutions
- Data payload display (for debugging)

**Component name:** `BookingActivityTimeline.tsx`

**Where to add:**
- BookingDetailsDrawer (new tab or section)
- Separate page for detailed audit trail

**Example UI:**
```
Activity History
─────────────────
Oct 6, 14:17  BOOKING_MARKED_MISSED
              Actor: Staff (John Doe)
              Reason: CUSTOMER_DID_NOT_ARRIVE

Oct 6, 14:15  GRACE_PERIOD_EXPIRED
              Actor: System

Oct 6, 14:00  GRACE_PERIOD_STARTED
              Actor: System

Oct 6, 10:00  BOOKING_CONFIRMED
              Actor: System

Oct 6, 10:00  BOOKING_CREATED
              Actor: Customer (Jane Smith)
```

---

### 5. Enhanced Status Display

**What's needed:**
- Add missing statuses to STATUS_STYLE:
  - arrived
  - waiting
  - no_show
  - rescheduled

**Update BookingDetailsDrawer STATUS_STYLE:**
```typescript
const STATUS_STYLE: Record<string, { label: string; chip: string; dot: string }> = {
  pending:      { label: 'Pending',      chip: 'bg-gray-500/20 border-gray-500/30 text-gray-400',   dot: 'bg-gray-500'    },
  pending_payment: { label: 'Awaiting Payment', chip: 'bg-amber-500/20 border-amber-500/30 text-amber-400', dot: 'bg-amber-500' },
  confirmed:    { label: 'Confirmed',    chip: 'bg-blue-500/20 border-blue-500/30 text-blue-400',    dot: 'bg-blue-500' ),
  arrived:      { label: 'Arrived',      chip: 'bg-emerald-500/20 border-emerald-500/30 text-emerald-400', dot: 'bg-emerald-500' },
  waiting:      { label: 'Waiting',      chip: 'bg-purple-500/20 border-purple-500/30 text-purple-400', dot: 'bg-purple-500' },
  in_progress:  { label: 'In Service',   chip: 'bg-gold/20 border-gold/30 text-gold',               dot: 'bg-gold'       },
  completed:    { label: 'Completed',    chip: 'bg-zinc-700/40 border-zinc-600/30 text-zinc-400',    dot: 'bg-zinc-500'    },
  cancelled:    { label: 'Cancelled',    chip: 'bg-red-500/20 border-red-500/30 text-red-400',       dot: 'bg-red-500'     },
  no_show:      { label: 'No Show',      chip: 'bg-orange-500/20 border-orange-500/30 text-orange-400', dot: 'bg-orange-500' },
  rescheduled:  { label: 'Rescheduled',  chip: 'bg-cyan-500/20 border-cyan-500/30 text-cyan-400',     dot: 'bg-cyan-500'   },
};
```

---

### 6. Follow-Up Workflow UI

**What's needed:**
- Follow-up status display (PENDING, SENT, RESPONDED, COMPLETED)
- Follow-up action buttons
- Follow-up notes field
- Follow-up date picker

**Where to add:**
- Resolution modal (follow-up section)
- BookingDetailsDrawer (if resolution exists)
- Needs Resolution view

**Example UI:**
```
Follow-up Status: PENDING
Follow-up Date: Oct 7, 2026
Notes: Call customer to reschedule

[Send Follow-up] [Mark Complete]
```

---

### 7. Grace Period Visual Indicator

**What's needed:**
- Visual countdown timer for grace period
- Color-coded urgency (green → yellow → red)
- Auto-refresh every second

**Component name:** `GracePeriodTimer.tsx`

**Props:**
```typescript
interface GracePeriodTimerProps {
  gracePeriodStartedAt: string;
  gracePeriodEndsAt: string;
  currentTime?: Date;
}
```

**Example UI:**
```
Grace Period: 04:23 remaining
[████████░░░░░░░░░░░░]
```

---

## C. API Integration Requirements

### 1. New API Endpoints Needed

| Endpoint | Purpose | Method |
|----------|---------|--------|
| `/api/v1/bookings/needs-resolution` | Get bookings needing resolution | GET |
| `/api/v1/bookings/{id}/resolve` | Resolve a booking | POST |
| `/api/v1/bookings/{id}/activities` | Get booking activity history | GET |
| `/api/v1/bookings/by-operational-state` | Get bookings by operational state | GET |

### 2. Existing API Updates Needed

**Booking API response should include:**
```typescript
{
  id: string;
  status: string;
  operational_state: string; // NEW
  grace_period_started_at: string | null; // NEW
  grace_period_ends_at: string | null; // NEW
  rescheduled_from_booking_id: string | null; // NEW
  // ... existing fields
}
```

---

## D. Implementation Priority

### P0 - Critical for Lifecycle

1. **Add missing statuses to STATUS_STYLE**
   - arrived, waiting, no_show, rescheduled
   - Update BookingDetailsDrawer
   - Update TimelineView

2. **Create ResolutionModal**
   - Outcome selection
   - Reason code dropdown
   - Notes field
   - Follow-up checkbox
   - API integration

3. **Add operational state display**
   - Grace period countdown
   - Overdue minutes
   - Update TimelineView cards
   - Update BookingDetailsDrawer

4. **Create Needs Resolution view**
   - New page or section
   - Filter by date
   - Quick resolve action
   - API integration

### P1 - Important

5. **Create BookingActivityTimeline**
   - Activity history display
   - Actor tracking
   - Timestamp display
   - Add to BookingDetailsDrawer

6. **Create GracePeriodTimer component**
   - Countdown timer
   - Color-coded urgency
   - Auto-refresh

7. **Follow-up workflow UI**
   - Follow-up status display
   - Follow-up actions
   - Add to ResolutionModal

### P2 - Polish

8. **Enhanced status animations**
   - Smooth status transitions
   - Visual feedback for state changes

9. **Empty states for new views**
   - No bookings needing resolution
   - No activity history

10. **Mobile optimization**
    - Responsive resolution modal
    - Mobile-friendly needs resolution view

---

## E. Component Architecture

### Proposed Component Structure

```
components/booking/
├── BookingDetailsDrawer.tsx (EXISTING - UPDATE)
│   ├── Add: operational state display
│   ├── Add: activity history tab
│   └── Add: resolution info section
│
├── TimelineView.tsx (EXISTING - UPDATE)
│   ├── Add: operational state indicators
│   └── Add: grace period countdown
│
├── ResolutionModal.tsx (NEW)
│   ├── Outcome selection
│   ├── Reason code dropdown
│   ├── Notes field
│   └── Follow-up section
│
├── BookingActivityTimeline.tsx (NEW)
│   ├── Activity list
│   ├── Actor display
│   └── Timestamp display
│
├── GracePeriodTimer.tsx (NEW)
│   ├── Countdown timer
│   └── Color-coded urgency
│
├── NeedsResolutionView.tsx (NEW)
│   ├── Date filter
│   ├── Booking list
│   └── Quick resolve action
│
└── [EXISTING COMPONENTS...]
```

---

## F. Status Transition UI Flow

### Current Flow (Existing)
```
confirmed → checked_in → in_service → completed
confirmed → cancelled
```

### New Flow (Required)
```
confirmed → arrived → waiting → in_progress → completed
confirmed → cancelled
confirmed → no_show (via resolution)
confirmed → rescheduled (via resolution)
```

### UI Actions Required

| From | To | UI Action | Component |
|------|-----|-----------|-----------|
| confirmed | arrived | "Check In" button | BookingDetailsDrawer |
| arrived | waiting | "Add to Queue" button | BookingDetailsDrawer |
| waiting | in_progress | "Start Service" button | BookingDetailsDrawer |
| in_progress | completed | "Complete Service" button | BookingDetailsDrawer |
| confirmed | cancelled | "Cancel" button | BookingDetailsDrawer |
| confirmed | no_show | "Mark as No-show" → ResolutionModal | NEW |
| confirmed | rescheduled | "Reschedule" → RescheduleModal | EXISTING (update) |

---

## G. Data Models

### Booking Interface (Update Required)

```typescript
interface Booking {
  id: string;
  date: string;
  time: string;
  customerName: string;
  service: string;
  staffName: string;
  status: string; // pending, pending_payment, confirmed, arrived, waiting, in_progress, completed, cancelled, no_show, rescheduled
  operational_state?: string; // upcoming, due_soon, grace_period, needs_resolution, resolved
  grace_period_started_at?: string | null;
  grace_period_ends_at?: string | null;
  rescheduled_from_booking_id?: string | null;
  endTime: string;
  duration: number;
  price: number;
  phone: string;
  notes?: string;
}
```

### BookingActivity Interface (New)

```typescript
interface BookingActivity {
  id: string;
  booking_id: string;
  type: string; // BOOKING_CREATED, BOOKING_CONFIRMED, GRACE_PERIOD_STARTED, etc.
  title: string;
  description?: string;
  data?: any;
  actor_type: string; // CUSTOMER, SPECIALIST, STAFF, PROVIDER, SYSTEM
  actor_id?: string;
  created_at: string;
}
```

### BookingResolution Interface (New)

```typescript
interface BookingResolution {
  id: string;
  booking_id: string;
  outcome: string; // NO_SHOW, CANCELLED, RESCHEDULED, COMPLETED_LATE, CUSTOMER_ARRIVED
  reason_code: string; // CUSTOMER_DID_NOT_ARRIVE, etc.
  notes?: string;
  actor_type: string;
  actor_id?: string;
  resolved_at: string;
  follow_up_required: boolean;
  follow_up_at?: string;
  follow_up_status?: string; // PENDING, SENT, RESPONDED, COMPLETED
  follow_up_notes?: string;
}
```

---

## H. Summary

### What Exists (✅)
- Booking management page with timeline/list views
- Booking details drawer with basic actions
- Reschedule modal
- Walk-in modal
- Staff assignment modal
- Basic status display (confirmed, checked_in, in_service, completed, cancelled)

### What's Missing (❌)
- Operational state display (grace period, needs resolution)
- Resolution modal with outcome/reason codes
- Needs Resolution view for unresolved bookings
- Activity history timeline
- Missing statuses (arrived, waiting, no_show, rescheduled)
- Grace period countdown timer
- Follow-up workflow UI
- API integration for new endpoints

### Implementation Order
1. Add missing statuses to STATUS_STYLE
2. Create ResolutionModal
3. Add operational state display to existing components
4. Create Needs Resolution view
5. Create BookingActivityTimeline
6. Create GracePeriodTimer
7. Add follow-up workflow UI
8. Update API client with new endpoints

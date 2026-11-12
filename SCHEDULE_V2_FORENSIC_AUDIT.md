# Schedule V2 — Forensic Audit Report

**Date**: October 7, 2026  
**Scope**: Complete Schedule/Bookings screen implementation  
**Purpose**: Understand current implementation before UX redesign

---

## Executive Summary

The Schedule screen is functionally complete with a solid technical foundation. It correctly uses the existing backend architecture (lifecycle status, operational state, resolution) and has all necessary components for booking management. However, the visual hierarchy and user experience feel like a conventional calendar rather than a living operational workspace.

**Key Strengths**:
- Proper backend integration with correct domain model usage
- Complete booking lifecycle operations
- Role-based permissions
- Responsive design (mobile/desktop)
- Real-time data updates (15s polling)

**Key Issues**:
- Visual hierarchy prioritizes statistics and alerts over the schedule itself
- "Needs Attention" section feels like an emergency dashboard
- Header is cluttered with navigation controls
- Booking cards in TimelineView are compact but could be more informative
- No clear "NOW" indicator in the main timeline
- Status legend is redundant if cards communicate state clearly

---

## Component Inventory

### 1. Main Page (`frontend/src/app/[slug]/(business)/bookings/page.tsx`)

**Data Sources**:
- `apiClient.getBookings()` — All bookings for provider/location (15s polling)
- `apiClient.getAssignableSpecialists()` — Staff/specialists for assignment
- `apiClient.getBookingsNeedingResolution()` — Bookings requiring attention (30s polling)

**State Management**:
- `selectedBooking` — Currently selected booking for drawer
- `isDrawerOpen` — Drawer visibility
- `viewMode` — 'timeline' | 'list' (list not currently used)
- `calendarView` — 'day' | 'week' | 'month'
- `selectedDate` — Current date being viewed
- Various modal states (walk-in, reschedule, resolution, assign staff)

**Current Header Structure**:
```
[‹] Bookings / Date [›]  [Today|Week|Month]  [Search input]
```

**Current Layout**:
```
┌─────────────────────────────────────────────────────────┐
│ Header with date navigation and view toggles            │
├────────────────────────────────────────────────────────–┤
│ Status line: "X bookings today • Y waiting • Z completed"│
├────────────────────────────────────────────────────────–┤
│ Needs Attention banner (red) OR "All on track" (green)   │
├────────────────────────────────────────────────────────–┤
│ ┌─────────────────────┐ ┌─────────────────────────────┐ │
│ │                     │ │                             │ │
│ │   Calendar (2/3)    │ │   Upcoming sidebar (1/3)    │ │
│ │                     │ │                             │ │
│ │   TimelineView      │ │   First 5 bookings          │ │
│ │   or Week/Month     │ │                             │ │
│ │                     │ │                             │ │
│ └─────────────────────┘ └─────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

**Operations**:
- Drag-and-drop rescheduling (day/week/month views)
- Click booking to open drawer
- Modals for: Walk-in, Reschedule, Assign Staff, Resolution
- Role-based action permissions

**Issues Identified**:
- Status line duplicates information available in the schedule
- "All appointments are on track" banner is noise when there are no issues
- Needs Attention section is visually heavy (red background, pulsing dot)
- No clear primary action button in header
- Search input is present but not connected to actual filtering

---

### 2. TimelineView (`frontend/src/components/booking/TimelineView.tsx`)

**Architecture**:
- Mobile: Single-column swipeable staff view
- Desktop: Multi-column grid with all staff
- Time slots: 9 AM → 8 PM (30-minute intervals)
- Live "NOW" indicator updates every minute

**Booking Card Display** (within timeline):
```
┌─────────────────────────────┐
│ ● Customer Name             │
│   Service                   │
│   Time · Price              │
│   [Operational state text]  │
└─────────────────────────────┘
```

**Operational State Display**:
- `upcoming`: "Starts in X min" (gray if >15min, amber if ≤15min)
- `due_soon`: "Due soon" (amber)
- `grace_period`: "X min remaining" (amber)
- `needs_resolution`: "X min overdue" (red)

**Status Legend** (bottom of timeline):
- Confirmed (green dot)
- Checked In (blue dot)
- In Service (gold dot)
- Completed (gray dot)
- Cancelled (red dot)

**Strengths**:
- Responsive design works well
- Live NOW indicator is subtle but useful
- Operational state is contextual and human-readable
- Role-based filtering (employees only see their own bookings)

**Issues Identified**:
- Status legend is redundant if cards communicate state
- Booking cards are compact but could show more context (service duration, staff name)
- No visual distinction between different operational states in the card itself
- Empty slots are just dashed borders — could be more inviting

---

### 3. BookingDetailsDrawer (`frontend/src/components/booking/BookingDetailsDrawer.tsx`)

**Structure**:
```
┌─────────────────────────────────────┐
│ [Cinematic photo header]            │
│                                     │
│ [Status chip]                       │
│                                     │
│ Customer avatar + name              │
│ Time range                          │
│ Operational state                   │
├─────────────────────────────────────┤
│ Contact (phone + message button)    │
│ Service + price                     │
│ Assigned staff                      │
│ Notes (if present)                  │
│ Resolution info (if present)        │
├─────────────────────────────────────┤
│ Actions (role-based):               │
│ - Assign Staff (if unassigned)      │
│ - Check In (if confirmed)           │
│ - Start Service (if checked_in)     │
│ - Complete Service (if in_service)  │
│ - Decide What Happened (if needs_resolution) │
│ - Reschedule                        │
│ - Cancel Booking                    │
└─────────────────────────────────────┘
```

**Strengths**:
- Cinematic header is visually appealing
- All necessary information is present
- Actions are contextual to booking state
- Role-based permissions are correctly implemented
- Resolution information is well-displayed

**Issues Identified**:
- Cinematic photo is decorative but not functional
- Could be more compact for quick decision-making
- No quick way to see booking history or previous resolutions

---

### 4. ResolutionModal (`frontend/src/components/booking/ResolutionModal.tsx`)

**Flow**:
1. Initial: "What happened?" (outcome selection)
2. Reason: "Why?" (reason code selection)
3. Follow-up: Notes + optional follow-up scheduling
4. Confirm: Save decision

**Outcomes**:
- Customer arrived (green)
- Customer didn't arrive (orange)
- Customer cancelled (red)
- Reschedule requested (cyan)
- Something else (gray)

**Strengths**:
- Multi-step flow is clear and guided
- Outcome options are user-friendly
- Follow-up support is built-in
- Calculates overdue minutes correctly

**Issues Identified**:
- None significant — this component is well-designed

---

### 5. QuickActions & FloatingQuickActions

**QuickActions** (static bar):
- Role-based action visibility
- Actions: New Booking, Walk-in, Reschedule, Cancel, Assign Staff
- Currently NOT used in the main bookings page

**FloatingQuickActions** (draggable FAB):
- Same actions as QuickActions
- Draggable around screen
- Currently NOT used in the main bookings page

**Note**: These components exist but are not integrated into the Schedule page. The page uses inline actions in the header and drawer instead.

---

### 6. BookingStats (`frontend/src/components/booking/BookingStats.tsx`)

**Displays**:
- Today's Bookings (gold)
- Confirmed (green)
- Waiting (yellow)
- In Service (blue)
- Cancelled (red)

**Note**: This component exists but is NOT used in the main bookings page. The page has a simple status line instead.

---

### 7. AppointmentCard (`frontend/src/components/booking/AppointmentCard.tsx`)

**Display**:
```
┌─────────────────────────────────────┐
│ [Avatar] Customer Name [Status icon]│
│         Time                        │
│         Service                     │
│         with Staff                  │
│                                     │
│         Price          [Status chip]│
└─────────────────────────────────────┘
```

**Note**: This component is used in ListView, which is NOT currently used in the Schedule page. The TimelineView has its own inline booking cards.

---

### 8. BookingSummary & BookingSteps

**BookingSummary**: Shows service, stylist, date, time, total price. Used in booking creation flow.

**BookingSteps**: Shows progress through booking flow (Service → Stylist → Schedule → Details). Used in booking creation flow.

**Note**: These are not relevant to the Schedule screen — they're for the customer booking flow.

---

### 9. Modals

**WalkInModal**:
- Cinematic photo panel + form
- Customer name/phone (optional)
- Service selection (required)
- Staff selection (optional, filters by specialist assignment)
- Notes (optional)
- Creates booking with `status: 'pending'` and `internal_booking: true`

**AssignStaffModal**:
- Two-step: booking card → staff selection grid
- Visual grid of assignable specialists
- Shows availability indicators
- Uses `getAssignableSpecialists` API (filters by active SpecialistAssignment)

**RescheduleModal**:
- CalendarPicker + TimePicker side-by-side
- Shows current appointment time
- Updates booking date/time

**Strengths**:
- All modals are visually consistent
- WalkInModal has excellent UX with cinematic design
- AssignStaffModal handles specialist assignment correctly

**Issues Identified**:
- WalkInModal is beautiful but may be over-designed for quick walk-ins
- RescheduleModal could show availability for the new time slot

---

### 10. Calendar Components

**CalendarPicker**:
- Month view with navigation
- Past dates disabled
- Today highlighted with gold border
- Selected date highlighted with gold gradient

**TimePicker**:
- Grid of time slots (9 AM → 8 PM, 30-min intervals)
- Mock availability indicators (green/yellow/red dots)
- "Next available" highlight

**Note**: TimePicker uses mock availability data — in production this should come from the backend.

---

### 11. ListView (`frontend/src/components/booking/ListView.tsx`)

**Features**:
- Search by customer, service, or staff
- Filter by status (All, Confirmed, Checked In, In Service, Completed, Cancelled)
- Uses AppointmentCard for each booking
- Empty state with decorative image

**Note**: This component exists but is NOT currently used in the Schedule page. The page only uses TimelineView.

---

## API Usage

### Endpoints Used

**Bookings**:
- `GET /v1/bookings` — Get all bookings for provider/location
- `GET /v1/bookings/needs-resolution` — Get bookings needing resolution
- `PUT /v1/bookings/:id` — Update booking (status, date, time, specialist)
- `POST /v1/bookings/:id/resolve` — Resolve a booking
- `POST /v1/bookings` — Create booking (walk-in)

**Specialists/Staff**:
- `GET /v1/providers/:id/assignable-specialists` — Get assignable specialists
- `GET /v1/staff` — Get staff members (for WalkInModal)

### Data Flow

```
RoleContext (providerId, locationId, role, userName)
    ↓
React Query queries (15s polling for bookings, 30s for needs-resolution)
    ↓
API Client (axios with auth token)
    ↓
Backend API
    ↓
Formatted data (Booking interface with lifecycle status, operational state, resolution)
    ↓
Components (TimelineView, BookingDetailsDrawer, etc.)
```

### Backend Architecture Compliance

**Lifecycle Status** (correctly used):
- `confirmed`, `checked_in`, `in_service`, `completed`, `cancelled`
- Frontend does NOT invent new lifecycle states

**Operational State** (correctly used):
- `upcoming`, `due_soon`, `grace_period`, `needs_resolution`
- Frontend displays human-readable text based on these states
- Frontend does NOT expose backend terminology unnecessarily

**Resolution** (correctly used):
- Uses existing `POST /v1/bookings/:id/resolve` endpoint
- Resolution modal captures outcome, reason_code, notes, follow_up
- Frontend does NOT create duplicate resolution endpoints

**Staff vs Specialist** (correctly used):
- WalkInModal uses `getStaff` but filters by `specialist_id` presence
- AssignStaffModal uses `getAssignableSpecialists` (filters by active SpecialistAssignment)
- Frontend correctly distinguishes between staff records and specialist assignments

---

## Current Visual Hierarchy Analysis

### Priority Order (Current)
1. **Header** — Date navigation, view toggles, search
2. **Status Line** — Booking counts
3. **Needs Attention Banner** — Red emergency-style alert
4. **Calendar** — TimelineView (2/3 width)
5. **Upcoming Sidebar** — First 5 bookings (1/3 width)

### Priority Order (Desired per requirements)
1. **Schedule** — The calendar itself should be dominant
2. **What's happening now** — Current time position
3. **What's next** — Upcoming appointments emerging from schedule
4. **What needs attention** — Visible but not emergency-style
5. **Actions** — Accessible without competing with schedule

### Gap Analysis

**Current**: `statistics → alerts → legend → calendar`  
**Desired**: `today → schedule → live state → attention → action`

The current implementation has the calendar buried under header elements, status lines, and alert banners. The schedule should be the first thing the user sees.

---

## Element Classification

### KEEP
- TimelineView core structure (mobile swipeable, desktop grid)
- Live NOW indicator
- Operational state display logic
- BookingDetailsDrawer (with minor refinements)
- ResolutionModal (excellent as-is)
- Role-based permissions
- Drag-and-drop rescheduling
- Week and Month views
- Real-time polling

### MOVE
- "Upcoming" sidebar — Should be integrated into the schedule or made optional
- Search input — Should be in a compact filter bar, not header
- View toggles (Today/Week/Month) — Should be secondary to date navigation

### TRANSFORM
- Header — Simplify to: Schedule + Date + Primary Action
- Status line — Remove or make much more subtle
- Needs Attention banner — Change from emergency-style to calm inbox-style
- Booking cards — Add more context (duration, staff name if not obvious)
- Status legend — Remove if cards communicate state clearly

### REMOVE
- "All appointments are on track" banner — Noise when no issues
- BookingStats component (unused)
- QuickActions component (unused)
- FloatingQuickActions component (unused)
- ListView component (unused, unless we want to add it back)

### ADD
- Compact filter bar (People, Status, Location)
- Clearer "NOW" indicator in timeline
- Visual distinction for different operational states in cards
- Quick action to jump to current time
- Staff lens (Everyone / Individual / Unassigned)

---

## Technical Debt / Issues

1. **TimePicker uses mock availability** — Should come from backend
2. **Search input not connected** — Present but doesn't filter bookings
3. **ListView unused** — Component exists but not integrated
4. **QuickActions unused** — Component exists but not integrated
5. **BookingStats unused** — Component exists but not integrated
6. **Status line duplicates info** — Counts are visible in schedule
7. **No 5-day/work-week view** — Could be useful addition

---

## Recommendations for Redesign

### 1. Header Simplification
```
Schedule
Wednesday, October 7
‹   Today   ›

[+ New booking]  [Walk-in]
```

### 2. Remove Noise
- Delete status line
- Delete "All on track" banner
- Remove or hide status legend

### 3. Transform Needs Attention
- Change from red emergency banner to calm inbox section
- Position as secondary element, not primary alert
- Use "Needs attention" language consistently

### 4. Enhance Timeline
- Add staff name to booking cards (if not obvious from column)
- Add service duration to cards
- Make operational state more visible in card design
- Improve empty slot presentation

### 5. Add Compact Filters
- People: Everyone / [Staff names] / Unassigned
- Status: All / Upcoming / Active / Needs Attention
- Location: (only if multi-location)

### 6. Improve "NOW" Indicator
- Make it more prominent in the timeline
- Add subtle pulse animation
- Consider horizontal line across all columns

### 7. Integrate Upcoming
- Either remove sidebar and show upcoming in timeline
- Or make sidebar collapsible/optional
- Consider showing "Next 3" instead of "First 5"

---

## Architecture Constraints

**DO NOT**:
- Create new booking lifecycle architecture
- Change BookingService
- Change BookingTimeStateEngine
- Change BookingResolution architecture
- Create duplicate resolution endpoints
- Create another operational-state system
- Add database tables
- Change booking status semantics
- Reintroduce Salon concepts
- Create a second calendar engine
- Introduce `resolved` as an operational state
- Turn `no_show`, `rescheduled`, etc. into fake lifecycle states

**DO**:
- Use existing APIs and domain model
- Represent backend truth in frontend
- Preserve role-based permissions
- Maintain real-time polling
- Keep drag-and-drop functionality

---

## Conclusion

The Schedule screen has a solid technical foundation and correctly implements the backend architecture. The redesign should focus on:

1. **Visual hierarchy** — Make the schedule dominant
2. **Noise reduction** — Remove redundant status displays
3. **Experience transformation** — From calendar to operational workspace
4. **Information clarity** — Better booking cards and filters
5. **Calm attention** — Needs attention as inbox, not emergency

No backend changes are required. This is purely a frontend UX task.

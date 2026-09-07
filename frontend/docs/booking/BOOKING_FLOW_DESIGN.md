# Booking Flow Design

## Current Flow Analysis

**Existing Flow:**
1. Greeting
2. Service Selection
3. Date/Time Selection
4. Specialist Selection
5. Confirmation

**Issues:**
- Specialist selection comes AFTER date/time selection
- No visibility into specialist availability upfront
- Can't see which specialists are available for a given service
- No indication of specialist relationship (favorite, followed)
- Doesn't leverage the new customer-specialist relationship architecture

## Improved Flow Design

### Flow 1: Service-First (Recommended)

**Best for:** Customers who know what service they want

```
1. Service Selection
   ↓
2. Specialist Selection (with availability indicators)
   ↓
3. Date/Time Selection (filtered by selected specialist)
   ↓
4. Confirmation
```

**Why this works:**
- Customers typically know "I want a haircut" before they know "I want it at 2pm"
- Shows specialist availability upfront
- Leverages customer-specialist relationships (favorites, followed specialists)
- Reduces backtracking if preferred specialist isn't available

### Flow 2: Specialist-First

**Best for:** Customers who have a favorite specialist

```
1. Specialist Selection (with availability indicators)
   ↓
2. Service Selection (filtered by specialist's services)
   ↓
3. Date/Time Selection (filtered by specialist's availability)
   ↓
4. Confirmation
```

**Why this works:**
- "I only cut with David" scenario
- Shows specialist's schedule upfront
- Natural for customers with strong specialist relationships

### Flow 3: Quick Book (One-Step)

**Best for:** Repeat customers booking same service with same specialist

```
1. Quick Book (pre-filled with last booking)
   ↓
2. Confirm
```

**Why this works:**
- "Book again" from booking history
- One-tap booking for regulars
- Highest conversion for loyal customers

## UI Components

### 1. Service Selection

**Layout:** Grid with cards

**Card Content:**
- Service name
- Price
- Duration
- Category icon
- Popular badge (if top service)
- **Specialist count** (e.g., "5 specialists available")

**Enhancements:**
- Filter by category (Hair, Nails, Makeup, etc.)
- Sort by popularity, price, duration
- Show "Your favorites" section
- Show "Recently booked" section

**Example:**
```
┌─────────────────────────┐
│ ✂️ Haircut              │
│ $35 • 45 min            │
│ 5 specialists available │
│ ⭐ Popular              │
└─────────────────────────┘
```

### 2. Specialist Selection

**Layout:** List with availability indicators

**Card Content:**
- Specialist photo
- Name
- Role (Stylist, Colorist, etc.)
- **Green dot** = Available today
- **Yellow dot** = Available this week
- **Gray dot** = No availability this week
- Rating (4.9 ⭐)
- **Following badge** (if customer follows)
- **Favorite badge** (if favorited)
- **Relationship score** (e.g., "Regular customer")
- **Next available slot** (e.g., "Today at 2pm")

**Sorting:**
1. Favorites/Followed specialists first
2. Highest rated
3. Most available
4. Alphabetical

**Enhancements:**
- Filter by role (Stylist, Colorist, Barber, etc.)
- Filter by availability (Today, This week, Anytime)
- Show "Your specialists" section (favorites/followed)
- Show "All specialists" section
- Quick view of their schedule

**Example:**
```
┌─────────────────────────────────┐
│ [Photo] Sarah Johnson           │
│ Colorist • ⭐ 4.9                │
│ ● Available today               │
│ ❤️ Following • 📌 Favorite      │
│ Next: Today at 2pm              │
└─────────────────────────────────┘
```

### 3. Date/Time Selection

**Layout:** Calendar + Time slots grid

**Calendar:**
- Month view
- Available dates highlighted
- Unavailable dates grayed out
- Today highlighted
- Selected date emphasized

**Time Slots:**
- Grid of time buttons
- **Green** = Available
- **Yellow** = Limited availability
- **Gray** = Unavailable
- Selected time emphasized

**Enhancements:**
- Show "Today's slots" prominently
- Show "Tomorrow's slots" 
- Show "This week" overview
- Filter by time of day (Morning, Afternoon, Evening)
- Show specialist's working hours
- Show specialist's breaks

**Example:**
```
Calendar:
┌───┬───┬───┬───┬───┬───┬───┐
│   │ 1 │ 2 │ 3 │ 4 │ 5 │ 6 │
│ 7 │ 8 │ 9 │10 │11 │12 │13 │
│14 │15 │16 │17 │18 │19 │20 │
└───┴───┴───┴───┴───┴───┴───┘
     ↑ Available dates highlighted

Time Slots:
┌─────┬─────┬─────┬─────┐
│ 9am │10am │11am │12pm │  ← Green (available)
├─────┼─────┼─────┼─────┤
│ 1pm │ 2pm │ 3pm │ 4pm │  ← Yellow (limited)
├─────┼─────┼─────┼─────┤
│ 5pm │ 6pm │ 7pm │ 8pm │  ← Gray (unavailable)
└─────┴─────┴─────┴─────┘
```

### 4. Confirmation

**Layout:** Summary card + Action buttons

**Summary Content:**
- Service (name, price, duration)
- Specialist (photo, name, rating)
- Date/Time (formatted nicely)
- Total price
- **Specialist relationship** (e.g., "Your 5th visit with Sarah")
- **Loyalty points** (e.g., "+35 points")

**Enhancements:**
- Show specialist's photo prominently
- Show "Book again" button for repeat
- Show "Add to calendar" option
- Show "Set reminder" option
- Show estimated wait time
- Show salon location/map

**Example:**
```
┌─────────────────────────────────┐
│ Booking Summary                │
├─────────────────────────────────┤
│ Service: Haircut                │
│ $35 • 45 min                   │
│                                 │
│ Specialist: [Photo] Sarah      │
│ Colorist • ⭐ 4.9               │
│ Your 5th visit with Sarah ❤️   │
│                                 │
│ Date: Friday, Aug 15           │
│ Time: 2:00 PM                  │
│                                 │
│ Total: $35                     │
│ +35 loyalty points             │
├─────────────────────────────────┤
│ [Cancel]  [Confirm Booking]    │
└─────────────────────────────────┘
```

## Availability Indicators

### Specialist-Level Indicators

**Green Dot (●)** - Available today
- Specialist has open slots today
- Can book immediately

**Yellow Dot (●)** - Available this week
- Specialist has slots this week but not today
- Can book within 7 days

**Gray Dot (●)** - No availability this week
- Specialist fully booked this week
- Can book next week or later

**Red Dot (●)** - Unavailable
- Specialist on leave/vacation
- No booking possible

### Slot-Level Indicators

**Green Slot** - Available
- Multiple slots available
- High availability

**Yellow Slot** - Limited availability
- Only 1-2 slots left
- Book soon

**Gray Slot** - Unavailable
- Already booked
- Specialist break
- Outside working hours

## Smart Features

### 1. Intelligent Defaults

**For New Customers:**
- Show most popular services first
- Show highest-rated specialists first
- Show today's availability first

**For Returning Customers:**
- Show last booked service first
- Show favorited specialists first
- Show preferred time slots first
- Pre-fill with last booking details

### 2. Relationship-Based Recommendations

**"Your Specialists" Section:**
- Specialists you follow
- Specialists you've favorited
- Specialists with high relationship score
- Specialists you've booked with 3+ times

**"Recommended for You" Section:**
- Based on your service history
- Based on your specialist preferences
- Based on your time preferences
- Based on your budget

### 3. Availability Optimization

**Smart Scheduling:**
- Suggest best time based on specialist's typical schedule
- Suggest alternative specialists if preferred is unavailable
- Suggest nearby dates if today is full
- Show "next available" for each specialist

**Real-Time Updates:**
- Live availability indicators
- Slot locking during selection
- Conflict detection
- Waitlist option for fully booked specialists

## Mobile Considerations

### Touch-Friendly Design
- Large tap targets (44px minimum)
- Swipe gestures for date selection
- Pull-to-refresh for availability
- Haptic feedback on selection

### Progressive Disclosure
- Show essential info first
- Expand for details
- Collapse for overview
- Minimize scrolling

### Performance
- Lazy load specialist photos
- Cache availability data
- Pre-fetch next step data
- Optimistic UI updates

## Implementation Priority

**Phase 1 (MVP):**
1. Service selection with specialist count
2. Specialist selection with basic availability
3. Date/time selection with slot indicators
4. Confirmation with relationship display

**Phase 2 (Enhanced):**
1. Specialist-first flow option
2. Relationship-based recommendations
3. Smart defaults for returning customers
4. Real-time availability updates

**Phase 3 (Advanced):**
1. Quick book for repeat customers
2. Multi-specialist booking
3. Waitlist functionality
4. Advanced filtering and sorting

## API Requirements

### Needed Endpoints

**Service Selection:**
- `GET /portal/services` - Enhanced with specialist count per service
- `GET /portal/services/{id}/specialists` - Specialists who perform this service

**Specialist Selection:**
- `GET /portal/specialists` - Enhanced with availability indicators
- `GET /portal/specialists/{id}/availability` - Specialist's schedule
- `GET /portal/specialists/followed` - Customer's followed specialists
- `GET /portal/specialists/favorites` - Customer's favorite specialists

**Booking Context (Read Model):**
- `GET /portal/availability` - Core availability engine results
- `GET /portal/booking-context/payment` - Payment rules (returns PaymentInstruction DTO)

**Booking Orchestration (Write Model):**
- `POST /portal/bookings` - (Maps to POST /booking-orchestrator/reserve) Execute booking and lock slots
- `POST /portal/bookings/quick` - Quick book for repeat customers

## Success Metrics

**Conversion Rate:**
- Service selection → Specialist selection: 80%
- Specialist selection → Date/time selection: 70%
- Date/time selection → Confirmation: 90%
- Overall booking completion: 50%

**User Satisfaction:**
- Time to complete booking: < 2 minutes
- Steps to complete booking: ≤ 4
- Backtracking rate: < 20%
- Abandonment rate: < 30%

**Relationship Building:**
- Favorite specialist selection rate: 40%
- Follow specialist rate: 25%
- Repeat booking rate: 60%

## Next Steps

1. **Wireframe the improved flow** - Visual mockups
2. **Implement service selection enhancements** - Add specialist counts
3. **Implement specialist selection with indicators** - Green/yellow/gray dots
4. **Enhance date/time selection** - Better slot indicators
5. **Add relationship-based features** - Favorites/followed sections
6. **A/B test flows** - Service-first vs specialist-first
7. **Measure and iterate** - Based on metrics

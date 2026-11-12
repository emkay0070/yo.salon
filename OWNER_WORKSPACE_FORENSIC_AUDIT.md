# Owner Workspace Forensic Audit

**Date:** October 6, 2026  
**Scope:** Complete Owner-facing workspace audit  
**Status:** ✅ COMPLETE  
**Purpose:** Strategic foundation for Owner Home redesign

---

## Executive Summary

The current Owner workspace contains **11 primary navigation items** with significant conceptual overlap between Home, Pulse, and Intelligence. The architecture exposes internal system concepts to business owners and uses generic SaaS/admin terminology rather than business-native language.

**Key Findings:**
- **Home/Pulse/Intelligence overlap:** HIGH - All three surfaces show operational metrics, revenue, and activity
- **Technical terminology exposed:** "provider", "operational state", "capacity", "assignment"
- **Generic SaaS language:** "Dashboard", "Manage", "Configuration"
- **Salon-specific lock-in:** "salon", "stylist", "chair" terminology throughout
- **Missing clear purpose:** Home is a KPI card wall, not a business command center

---

## A. Current Owner Information Architecture

### Navigation Hierarchy (from DashboardLayout.tsx)

**Owner/Manager Navigation:**

| Label | Route | Icon | Purpose |
|-------|-------|------|---------|
| Dashboard | /dashboard | LayoutDashboard | Home/Landing |
| Appointments | /bookings | Calendar | Calendar & booking management |
| Customers | /customers | Users | Customer CRM |
| Services | /services | Scissors | Service catalog |
| Specialists | /staff | UserCheck | Team/staff management |
| Analytics | /analytics | TrendingUp | Analytics hub |
| Intelligence | /analytics/intelligence | Brain | AI insights & recommendations |
| Payments | /payments | CreditCard | Payment methods & transactions |
| Team Finance | /finance/team | DollarSign | Team compensation & payouts |
| Pulse | /pulse | Activity | Live operational activity |
| Settings | /settings | Settings | Business configuration |

**Total:** 11 top-level navigation items

---

## B. Home / Pulse / Intelligence Comparison

### Home (OwnerDashboard.tsx)

**What it communicates:**
- Today's operational metrics (bookings, revenue, customers, waiting)
- Today's appointment timeline
- Business status (online/accepting bookings)
- Booking link copy
- Intelligence narrative snippet
- Monthly revenue trend chart
- Weekly bookings chart
- Staff performance preview

**Type:** Operational + Analytical hybrid

**Above-the-fold:**
- Greeting + date
- 4 KPI cards (Bookings, Revenue, Customers, Waiting)
- "New Walk-in" button
- Onboarding checklist banner

**Primary actions:**
- Create walk-in booking
- Copy booking link
- Explore Intelligence
- View staff performance

**Feels like:** Admin dashboard with KPI cards

---

### Pulse (pulse/page.tsx)

**What it communicates:**
- Live clock (real-time, updates every second)
- Current capacity/atmosphere (chairs active)
- Next appointment countdown
- Queue timeline (completed, in-progress, checking-in, waiting)
- Active stations grid (busy/available status per station)
- Live activity feed (notifications)

**Type:** Live operational surface

**Above-the-fold:**
- Large clock with pulse indicator
- "Today" + appointment count
- Current Atmosphere widget (capacity bar)

**Primary actions:**
- View next appointment
- Monitor station status
- View live activity

**Feels like:** Receptionist operational view

---

### Intelligence (analytics/intelligence/page.tsx)

**What it communicates:**
- Executive briefing (greeting + narrative)
- Key metrics row (appointments, revenue, specialists, net revenue)
- Attention signals (needs your attention)
- Today's schedule (placeholder)
- Business pulse (revenue trend, customer movement)
- Deep intelligence (revenue pipeline, churn radar, demand heatmap, staff leaderboard)
- Copilot chat

**Type:** Analytical + Recommendations

**Above-the-fold:**
- Executive briefing hero
- Key metrics cards

**Primary actions:**
- Explore full analytics
- Address attention signals
- Chat with Copilot

**Feels like:** Business intelligence command center

---

### Comparison Table

| Dimension | Home | Pulse | Intelligence |
|-----------|------|-------|--------------|
| **Primary purpose** | Mixed overview | Live operations | Strategic insights |
| **Time horizon** | Today + Month | Right now | Trends + Forecasts |
| **Operational data** | Yes (today's bookings) | Yes (stations, queue) | No |
| **Revenue data** | Yes (today + month) | No | Yes (deep analytics) |
| **Recommendations** | Narrative snippet | No | Yes (signals, Copilot) |
| **Real-time updates** | No | Yes (30s refresh) | No |
| **Action-oriented** | Yes (walk-in, copy link) | No (monitoring) | Yes (address signals) |
| **KPI cards** | Yes (4 cards) | No | Yes (4 cards) |
| **Charts** | Yes (revenue, bookings) | No | Yes (multiple) |
| **Unique value** | Quick operational snapshot | Live floor view | Strategic decision support |

---

### Overlap Analysis

**Home ↔ Pulse: HIGH overlap**
- Both show today's operational state
- Both show appointment information
- Pulse is the "live" version of Home's "today" section
- Home duplicates Pulse's operational awareness

**Home ↔ Intelligence: MEDIUM overlap**
- Both show revenue data (Home: month, Intelligence: deep)
- Both show staff performance (Home: preview, Intelligence: leaderboard)
- Both show narrative/insights (Home: snippet, Intelligence: full)
- Home duplicates Intelligence's analytical surface

**Pulse ↔ Intelligence: LOW overlap**
- Pulse is operational (now)
- Intelligence is strategic (trends)
- Minimal functional overlap

**Recommendation:** These three surfaces need clear separation:
- **Home:** Business command center (what matters today + what's coming)
- **Pulse:** Live operational floor view (what's happening right now)
- **Intelligence:** Strategic insights (what the data says)

---

## C. Language Audit

### A. Technical Terminology (Exposed to Owners)

| Current | Context | Recommended | Reason |
|---------|---------|-------------|--------|
| "provider" | Throughout code, API calls | "business" or "your space" | "Provider" is backend terminology; owners think of their business |
| "operational state" | Pulse page, analytics | "status" or "current state" | Too technical for business owner |
| "capacity" | Pulse page (capacity widget) | "occupancy" or "busyness" | "Capacity" sounds industrial |
| "assignment" | Staff page, bookings | "schedule" or "assigned to" | "Assignment" is HR terminology |
| "configuration" | Settings pages | "setup" or "preferences" | "Configuration" is developer language |
| "policy" | Finance, settings | "rules" or "settings" | "Policy" sounds like corporate HR |
| "settlement" | Finance pages | "payout" or "payment" | "Settlement" is financial jargon |

### B. Generic SaaS/Admin Language

| Current | Context | Recommended | Reason |
|---------|---------|-------------|--------|
| "Dashboard" | Navigation label, page titles | "Home" or "Overview" | "Dashboard" is generic admin software language |
| "Manage" | Various action buttons | Specific action verbs | "Manage" is CRUD-style language |
| "Configuration" | Settings sections | "Setup" or "Preferences" | Too technical |
| "Records" | Customer/staff listings | "People" or "Team" | "Records" sounds like database |
| "CRUD-style labels" | Create, Read, Update, Delete | Business-native actions | Owners don't think in CRUD terms |

### C. Legacy Salon-Specific Language

| Current | Context | Recommended | Reason |
|---------|---------|-------------|--------|
| "salon" | Throughout (URLs, variable names) | "business" or "space" | Product is broader than salons |
| "stylist" | Staff page, bookings | "specialist" or "professional" | "Stylist" is salon-specific |
| "chair" | Pulse page (stations) | "station" or "bay" | "Chair" is salon-specific |
| "Scissors" icon | Services, branding | Category-appropriate icon | Scissors locks to salons |
| "treatment" | Service descriptions | "service" or "session" | "Treatment" is salon/medical |

### D. Duplicate Concepts

| Concept | Current Variations | Recommended | Reason |
|---------|-------------------|-------------|--------|
| Team members | "Staff", "Specialists", "Team" | "Team" consistently | Confusing to have 3 terms |
| Booking time | "Appointments", "Bookings", "Sessions" | "Appointments" consistently | Inconsistent terminology |
| Service providers | "Provider", "Business", "Salon" | "Business" consistently | "Provider" is technical |
| Payment methods | "Channels", "Methods", "Payment options" | "Payment methods" consistently | "Channel" is technical |

---

## D. Duplicate/Overlapping Concepts

### 1. Operational Metrics

**Home shows:**
- Today's bookings count
- Today's revenue
- Today's customers
- Waiting count

**Pulse shows:**
- Total appointments today
- Active chairs
- Next appointment
- Queue timeline

**Overlap:** Both answer "what's happening today?" but with different data presentation.

**Resolution:** Pulse should be the canonical "live operations" view. Home should focus on "what matters today" (attention items + key decisions).

---

### 2. Revenue Data

**Home shows:**
- Today's revenue (KPI card)
- Monthly revenue (performance section)
- Revenue trend chart

**Intelligence shows:**
- Expected revenue (today)
- Net revenue (month)
- Revenue pipeline
- Revenue trend (deep analytics)

**Overlap:** Both show revenue at different granularities.

**Resolution:** Home should show "cash collected today" (operational). Intelligence should show "revenue trends" (strategic).

---

### 3. Staff Performance

**Home shows:**
- Staff performance preview (2 staff, bookings + revenue)

**Intelligence shows:**
- Staff leaderboard (full team)

**Analytics/Staff shows:**
- Detailed staff analytics

**Overlap:** Home duplicates Intelligence's staff performance surface.

**Resolution:** Home should not show staff performance. That belongs in Intelligence or dedicated Staff page.

---

### 4. Today's Schedule

**Home shows:**
- Today's appointment timeline (list view)

**Pulse shows:**
- Queue timeline (status-based)
- Next appointment hero

**Intelligence shows:**
- Today's schedule (placeholder, not implemented)

**Overlap:** Home and Pulse both show today's appointments.

**Resolution:** Pulse should be the canonical "today's operations" view. Home should show "attention items" (what needs action today).

---

## E. Home Diagnosis

### Current Home Structure (OwnerDashboard.tsx)

**Above-the-fold hierarchy:**
1. Greeting ("Good morning, [name]")
2. Date
3. "New Walk-in" button (primary action)
4. Onboarding checklist banner

**Major cards:**
1. **Zone 1: At a Glance** (4 KPI cards)
   - Bookings (today)
   - Revenue (today)
   - Customers (today)
   - Waiting (right now)

2. **Zone 2: Today** (2-column layout)
   - Today's appointment list (left, larger)
   - Your Space status + Intelligence snippet (right)

3. **Zone 3: Performance** (full-width)
   - Financial top bar (Revenue, Cash Received, Outstanding)
   - Revenue trend chart
   - Bookings chart + Staff performance preview

**Operational information:**
- Today's bookings count
- Today's revenue
- Waiting customers
- Today's appointment timeline
- Online status indicator

**Upcoming appointments:**
- Shown in "Today" zone
- Time-sorted list
- Status indicators (completed, in-progress, pending)

**Attention states:**
- Waiting count (KPI card)
- Onboarding checklist (if not complete)
- No explicit "needs attention" surface

**Financial information:**
- Today's revenue (KPI)
- Monthly revenue (performance section)
- Cash received (mock calculation)
- Outstanding (mock calculation)
- Revenue trend chart

**Intelligence:**
- Narrative snippet (from Intelligence API)
- "Explore Intelligence" link
- Staff performance preview (from Intelligence API)

**Quick actions:**
- New Walk-in (primary button)
- Copy booking link
- Explore Intelligence
- View staff performance

**Empty states:**
- "No appointments scheduled" (if today has none)
- "Revenue trend will appear soon" (if no data)
- "Staff performance will appear soon" (if no data)

**Visual density:**
- High: 4 KPI cards + timeline + charts + intelligence
- Competing primary actions: Walk-in button vs. explore links

---

### What Works

1. **Personal greeting** - Feels human, not robotic
2. **Today focus** - Answers "what's happening today?"
3. **Walk-in action** - Primary operational action is accessible
4. **Intelligence integration** - Links to deeper insights
5. **Visual hierarchy** - Clear zones with labels

---

### What's Weak

1. **KPI card wall** - Feels like generic admin dashboard, not business command center
2. **Mixed time horizons** - Today + month data in same view creates confusion
3. **No clear priority** - What should the owner do first?
4. **Duplicate surfaces** - Today's appointments duplicate Pulse
5. **Staff performance preview** - Doesn't belong on Home (belongs in Intelligence)
6. **Mock financial data** - "Cash received" and "Outstanding" are calculated, not real
7. **No attention surface** - What needs the owner's attention right now?
8. **Competing actions** - Walk-in button vs. multiple "explore" links

---

### What's Missing

1. **Attention items** - What needs action today? (overdue bookings, no-shows, unresolved issues)
2. **Clear priority** - What's the most important thing to do right now?
3. **Business health indicator** - Is today going well or poorly?
4. **Upcoming commitments** - What's coming in the next hour/day?
5. **Quick decision support** - Should I accept a walk-in? Do I have capacity?
6. **Customer attention** - VIPs arriving, high-value customers
7. **Team attention** - Staff on break, staff running late, capacity issues

---

### First-Experience Question

**If an Owner opens Yo.Salon for the first time this morning, what is the first thing the current Home page helps them understand?**

**Answer:** They see 4 KPI numbers (bookings, revenue, customers, waiting) and a list of today's appointments. They understand **what's scheduled today** but not **what they should do**.

The page is descriptive, not prescriptive. It tells them the state of their business but doesn't guide their first action.

---

### Missing Important Information

1. **What needs attention right now?** (no attention surface)
2. **Is today going well?** (no health indicator)
3. **What's the most important thing to do?** (no priority guidance)
4. **Do I have capacity for walk-ins?** (no quick capacity check)
5. **Are there any issues?** (no problem surface)
6. **Who are my most important customers today?** (no VIP/high-value surface)
7. **Is my team on track?** (no team status summary)

---

## F. Recommended Owner Information Architecture

### Proposed Hierarchy

**Primary Navigation (7 items):**

| Label | Route | Purpose | Question Answered |
|-------|-------|---------|-------------------|
| **Home** | /dashboard | Business command center | What matters today? |
| **Schedule** | /bookings | Calendar & appointments | When are we booked? |
| **People** | /customers | Customer relationships | Who are my customers? |
| **Team** | /staff | Team management | Who's working today? |
| **Insights** | /analytics | Business intelligence | How is my business performing? |
| **Money** | /payments | Financial operations | What's the financial state? |
| **Settings** | /settings | Business configuration | How is my business set up? |

**Removed from top-level:**
- ~~Intelligence~~ → Merged into Insights as a tab/section
- ~~Pulse~~ → Becomes a "Live Floor" view within Schedule or Team
- ~~Services~~ → Move to Settings (catalog management is configuration)
- ~~Team Finance~~ → Merge into Money as a section

**Rationale:**
- 7 items is cognitively manageable (current 11 is too many)
- Consolidates overlapping concepts
- Groups related functionality
- Uses business-native language

---

### Proposed Page Purposes

**Home:** "What matters today?"
- Attention items (what needs action)
- Today's commitments (next hour, today)
- Business health indicator
- Quick actions (walk-in, message customer)
- Intelligence highlights (1-2 key insights)

**Schedule:** "When are we booked?"
- Calendar view (day/week/month)
- Today's timeline (detailed)
- Booking management
- Live floor view (Pulse functionality)

**People:** "Who are my customers?"
- Customer list
- Customer profiles
- Customer history
- VIP/high-value customers

**Team:** "Who's working today?"
- Team roster
- Today's schedule per team member
- Team status (available, busy, on break)
- Team performance

**Insights:** "How is my business performing?"
- Revenue trends
- Customer analytics
- Staff analytics
- Service analytics
- Intelligence (AI insights, recommendations)

**Money:** "What's the financial state?"
- Payment methods
- Transactions
- Payouts (team finance)
- Financial reports

**Settings:** "How is my business set up?"
- Business profile
- Services catalog
- Pricing
- Hours/availability
- Integrations

---

## G. Recommended Home Strategy

### Home Should Ultimately Accomplish

**Primary Goal:** Be the **business command center** — not a dashboard, not a KPI wall, but a prescriptive surface that guides the owner's first action.

**Key Principles:**

1. **Attention-first:** Show what needs action before showing what's happening
2. **Prescriptive, not descriptive:** Guide what to do, don't just report state
3. **Today-focused:** Answer "what matters today?" not " what happened this month"
4. **Action-oriented:** Every section should enable a decision or action
5. **Calm, not chaotic:** Reduce visual density, increase clarity

---

### Proposed Home Structure

**Above-the-fold:**
1. **Greeting + Business Health** (single line)
   - "Good morning, Sarah. Your space is on track today."
   - Health indicator (green/yellow/red)

2. **Attention Surface** (if items exist)
   - "3 things need your attention"
   - Priority cards (e.g., "VIP arriving in 15 min", "Staff running late", "Overdue payment")

3. **Today's Commitments** (if no attention items)
   - "Next: John at 10:00 (Haircut) — 15 minutes"
   - "Today: 8 appointments, UGX 450K expected revenue"

**Below-the-fold:**
4. **Quick Actions** (3-4 actions)
   - Accept walk-in
   - Message customer
   - Adjust schedule
   - View full schedule

5. **Intelligence Highlights** (1-2 insights)
   - "Revenue is up 20% this week"
   - "Tuesday is fully booked — consider adding capacity"

6. **Team Status** (summary)
   - "3 of 4 specialists available"
   - "1 on break until 11:00"

**What's removed:**
- KPI card wall (replaced with health indicator)
- Monthly revenue chart (move to Insights)
- Staff performance preview (move to Team or Insights)
- Today's appointment list (move to Schedule)
- Booking link copy (move to Settings)

---

## H. Priority Order

### P0 — Must resolve before visual redesign

1. **Clarify Home/Pulse/Intelligence boundaries**
   - Define distinct purposes for each surface
   - Remove duplicate functionality
   - Establish canonical data sources

2. **Rename navigation to business-native language**
   - Dashboard → Home
   - Appointments → Schedule
   - Customers → People
   - Specialists → Team
   - Analytics → Insights
   - Payments → Money
   - Remove salon-specific terminology

3. **Consolidate navigation from 11 to 7 items**
   - Merge Intelligence into Insights
   - Move Pulse to Schedule (as "Live Floor" view)
   - Move Services to Settings
   - Merge Team Finance into Money

4. **Remove technical terminology from UI**
   - Replace "provider" with "business"
   - Replace "operational state" with "status"
   - Replace "capacity" with "occupancy"
   - Replace "assignment" with "schedule"

---

### P1 — Important

5. **Redesign Home as attention-first command center**
   - Add attention surface (what needs action)
   - Add business health indicator
   - Remove KPI card wall
   - Focus on today's commitments
   - Add quick action buttons

6. **Standardize terminology across pages**
   - Consistent use of "team" vs "staff" vs "specialists"
   - Consistent use of "appointments" vs "bookings"
   - Consistent use of "business" vs "salon" vs "provider"

7. **Remove salon-specific language**
   - Replace "stylist" with "specialist"
   - Replace "chair" with "station"
   - Replace "treatment" with "service"
   - Update icons to be category-agnostic

---

### P2 — Polish

8. **Improve visual density and hierarchy**
   - Reduce competing primary actions
   - Clearer visual zones
   - Better use of whitespace

9. **Add empty states that guide action**
   - When no appointments today
   - When no attention items
   - When no team members

10. **Improve mobile experience**
    - Consolidate navigation for mobile
    - Optimize Home for mobile first-visit experience

---

## Conclusion

The current Owner workspace has solid foundations but suffers from:
- **Conceptual overlap** between Home, Pulse, and Intelligence
- **Technical terminology** exposed to business owners
- **Generic SaaS language** instead of business-native terminology
- **Salon-specific lock-in** that limits platform expansion
- **KPI card wall** approach instead of prescriptive command center

**Recommended approach:**
1. Consolidate navigation (11 → 7 items)
2. Clarify page purposes (Home = command center, Pulse = live floor, Intelligence = insights)
3. Rename to business-native language
4. Redesign Home as attention-first surface
5. Remove technical/salon-specific terminology

**Next step:** Implement P0 changes before visual redesign begins.

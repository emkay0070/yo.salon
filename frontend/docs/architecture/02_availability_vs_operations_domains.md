# Availability vs Operations Domains

This document defines a critical architectural boundary within the yo.salon platform: the distinction between the **Availability Domain** and the **Operations Domain**. 

Getting this boundary wrong forces independent specialists into rigid salon-style operations, breaking the platform's multi-tenant flexibility.

---

## The Core Distinction

* **Availability** answers the question: *"When can this person work?"*
* **Operations** answers the question: *"Who is working together today, and how did they perform?"*

### 1. The Availability Domain

The Availability Domain is concerned purely with scheduling capability. It does not know about shifts, rosters, cash drawers, or attendance. It calculates whether a specific specialist, under a specific assignment, has an open slot to take a booking.

**Components:**
* **Assignment Schedules:** The recurring weekly template (e.g., "Mondays 09:00 - 18:00").
* **Schedule Overrides:** Date-specific planning changes (e.g., "Next Monday specifically: 14:00 - 22:00"). *Crucially, overrides are planning artifacts. They hold no revenue or operational data.*
* **Time Off:** Approved absences that subtract from available time.
* **Availability Generator:** The engine that reads schedules, overrides, and time-off to compute cacheable `availability_windows`.

This model perfectly serves independent specialists who don't think in "shifts" but rather just simple working hours.

### 2. The Operations Domain (Future)

The Operations Domain represents actual business execution and performance tracking on the floor. It is a Salon-level (or Branch-level) concept, not a Specialist-level concept.

**Components:**
* **Shifts:** An operational session (e.g., "Morning Shift", "Evening Shift"). A shift is a roster that contains multiple assignments.
* **Shift Assignments:** The actual record of an assignment participating in a shift, including clock-in/clock-out times (Attendance).
* **Pulse / Live Dashboards:** Real-time tracking of staff on the floor, occupancy, and delays.
* **Cash Drawer & Daily Closing:** Financial reconciliation for a specific business session.
* **Analytics & Payroll:** Revenue generated per shift, tips, commissions, and performance comparisons (e.g., "Saturdays outperform weekday evenings").

---

## Architectural Flow

The Availability Domain feeds into the Operations Domain, **not** the other way around. 

```text
Identity Domain
    │
    ├── Person
    ├── Provider
    └── Assignment

Catalog Domain
    ├── Services
    ├── Pricing
    └── Policies

Availability Domain
    ├── Assignment Schedules
    ├── Schedule Overrides   (Planning only)
    ├── Time Off
    ├── Availability Generator
    └── Availability Windows

Booking Domain
    ├── Booking Engine
    ├── Slots
    └── Reservations

Operations Domain
    ├── Shifts               (The operational session)
    ├── Rosters
    ├── Attendance
    ├── Pulse
    ├── Cash Drawer
    ├── Daily Closing
    ├── Payroll
    └── Analytics
```

## Why This Matters

If you make Availability depend on Shifts, you force independent freelancers to define "Shifts" just to generate their booking availability. 

By keeping them separate:
1. **Independent Specialists** use the Availability Domain (Schedules + Overrides) and ignore the Operations Domain entirely.
2. **Enterprise Salons** use the Availability Domain to plan, and the Operations Domain to execute, track attendance, and analyze revenue.

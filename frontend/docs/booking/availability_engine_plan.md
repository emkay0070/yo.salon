# Goal: Availability Engine Foundation & Schema Clean-up

To build a deterministic, modular `AvailabilityEngine`, we must standardize our schedules around **Specialist Assignments**, and clearly separate **Availability** (When is the specialist working?) from **Bookability** (Can this service fit?).

> **Availability is cached. Bookability is computed.**

## Phase 1 — Data Model (Migrations & Schema)

#### [NEW] `2026_08_05_000000_create_salon_schedules_table.php`
- Standardizes branch opening hours, mirroring `provider_schedules`.

#### [MODIFY] `2026_07_31_144633_create_salon_specialist_table.php` (Evolve into Assignments)
- **Rename** to `specialist_assignments`. 
- **Add** UUID `id` primary key.
- **Add** `employment_type`, `is_primary`, `starts_at`, `ends_at`, `status`.

#### [DELETE] `2026_07_31_200100_create_specialist_schedules_table.php`
- Drop the global specialist schedules table.

#### [NEW] `2026_08_05_000002_create_assignment_schedules_table.php`
- Schedules belonging to the *assignment*.
- Fields: `assignment_id`, `day_of_week`, `start_time`, `end_time`, `break_start_time`, `break_end_time`.

#### [NEW] `2026_08_05_000003_create_specialist_time_off_table.php`
- Leave management for specific assignments.
- Fields: `id`, `assignment_id`, `date`, `start_time`, `end_time`, `reason`, `status`.

#### [MODIFY] `2026_08_03_130000_create_specialist_availability_table.php` (Evolve to Windows)
- **Rename** to `availability_windows`.
- Represents continuous **Availability Windows**.
- **Add** `version` column.

#### [MODIFY] `2026_07_14_164127_create_salons_table.php`
- Remove `opening_hours` JSON column.

## Phase 2 — Domain Foundation

```text
App
└── Domain
    └── Availability
        ├── DTOs
        │   └── AvailabilityContext.php (Composed of ProviderContext, AssignmentContext, etc.)
        ├── Queries
        │   └── AvailabilityContextQuery.php
        ├── Constraints
        │   └── AvailabilityConstraint.php
        ├── Generators
        │   └── AvailabilityGenerator.php (Calculates and populates cache)
        ├── Engines
        │   └── AvailabilityEngine.php (Reads cache and applies runtime constraints)
        └── Actions
            └── RegenerateAvailabilityAction.php
```

## Phase 3 — Constraints Implementation

Constraints will accept the `AvailabilityContext` DTO.

## Phase 4 — Window Generation & Caching

* `RegenerateAvailabilityJob` triggers `AvailabilityGenerator`.
* Outputs continuous **Windows** to `availability_windows`.

## Phase 5 — Bookability
* The **Booking Engine** reads windows and splits them into **Appointment Slots**.

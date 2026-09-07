# Yo Salon: Layered Domain Architecture

To ensure the backend can scale gracefully and handle complex business rules without bloated Eloquent models, we have adopted a **Layered Domain Architecture**.

This documentation outlines the foundational pattern established during the Service Catalog & Booking Rules Refactor, which prepares the system for advanced scheduling and availability computations.

## The Core Philosophy

> **Eloquent models represent persistence, not business behavior.**
> **Availability is cached. Bookability is computed.**

Instead of relying on "Fat Models" (where Eloquent models handle complex JOINs, inheritance, and business rules), we enforce a strict separation of concerns using distinct layers in our Domain.

## The Architecture Layers

```text
Controllers (HTTP / API entry points)
        │
        ▼
Queries (Retrieve information)
        │
        ▼
DTOs (Carry normalized data)
        │
        ▼
Policies (Answer whether an operation is allowed)
        │
        ▼
Engines (Compute outcomes)
        │
        ▼
Actions / Commands (Perform state changes)
        │
        ▼
Models (Database persistence)
```

### 1. Queries (Read Data)
Queries (`App\Domain\...\Queries`) answer questions about data state and relationships. They are responsible for retrieving data from PostgreSQL, Redis, or external APIs. 
* **Example:** `BranchCatalogQuery`, `AvailabilityContextQuery`
* **Responsibility:** Get the data out. Do not make business decisions.

### 2. DTOs (Normalized Context)
Data Transfer Objects decouple our business logic from Eloquent. 
* **Example:** `AvailabilityContext` (composed of `ProviderContext`, `SalonContext`, `AssignmentContext`).
* **Responsibility:** Provide a unified interface for the Policies and Engines so they don't care where the data came from.

### 3. Policies (Decide)
Policies (`App\Domain\...\Policies`) represent boolean business rules. They don't compute prices or slots; they answer "Can this happen?".
* **Example:** `BookingPolicy` (Customer owes money? → Reject. VIP? → Allow).
* **Responsibility:** Enforce business constraints and gating logic.

### 4. Generators & Engines (Compute)
Generators and Engines execute heavy calculations.
* **CRITICAL RULE:** *Engines do not query the database themselves.* They take DTOs or collections as input. This makes them highly deterministic and perfectly testable.
* **Separation of Generation and Evaluation:**
  * `AvailabilityGenerator`: Generates `availability_windows` (the cache).
  * `AvailabilityEngine`: Reads cached windows and applies runtime constraints (e.g. sick leave added today).
  * `BookingEngine`: Reads available windows and determines **Bookable** slots based on service duration and buffers.

### 5. Actions / Commands (Write Data)
Actions (`App\Domain\...\Actions`) encapsulate state changes. They are the only layer (aside from simple seeders) that should mutate data.
* **Example:** `CreateBookingAction`, `RegenerateAvailabilityAction`.
* **Responsibility:** Execute the final database writes (via Models) after passing Queries, Policies, and Engines.

## The Availability Engine: Modular Constraint Pipeline

Because scheduling logic is highly volatile and complex, the Engine is designed as a **Constraint Pipeline**.

```text
AvailabilityGenerator
        │
        ▼
[ Constraint Pipeline ]
        │
   ├── ProviderHoursConstraint
   ├── BranchHoursConstraint
   ├── AssignmentScheduleConstraint
   ├── TimeOffConstraint
   ├── BookingConflictConstraint
   └── EquipmentConstraint
```

By using plugins/constraints, adding a new rule (like "Travel Time" or "Room Capacity") won't require rewriting the Engine. All constraints receive the immutable `AvailabilityContext` DTO.

## Summary
By isolating Reads (Queries), Normalization (DTOs), Rules (Policies), Math (Engines), and Writes (Actions), the controllers remain incredibly thin and the domain becomes highly testable.

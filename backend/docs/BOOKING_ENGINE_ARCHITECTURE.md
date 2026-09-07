# Booking Engine Architecture Plan

## Provider vs Salon Hierarchy

**Current Architecture:**

```
Provider (Business Entity)
    │
    ├── Salon (Location 1)
    ├── Salon (Location 2)
    ├── Salon (Location 3)
    │
    ├── Specialist (can work at multiple salons)
    ├── Specialist
    │
    ├── Service (can be offered at multiple salons)
    ├── Service
    │
    └── Booking (at a specific salon)
```

**Provider = Business Entity**
- Could be a single salon owner
- Could be a multi-salon chain
- Could be a franchise
- Owns the brand, billing, subscriptions
- Sets default policies

**Salon = Physical Location**
- Specific address, hours, staff
- Can override provider policies
- Has local management
- Individual customer base

## Booking Policy Configuration Strategy

### Three-Level Policy Hierarchy

**1. Provider Level (Default Policies)**
- Chain-wide policies
- Standardized customer experience
- Billing and payment rules
- Base cancellation policies

**2. Salon Level (Override Policies)**
- Location-specific adjustments
- Local market conditions
- Different operating hours
- Specialist availability patterns

**3. Service Level (Service-Specific Policies)**
- Consultation requirements
- Different deposit rules
- Duration-based policies
- Specialist requirements

**Precedence:** Service > Salon > Provider

## Configuration Schema

### Provider Booking Configuration

```json
{
  "booking_flow": {
    "default_flow": "service_first",
    "allow_specialist_first": true,
    "allow_quick_book": true,
    "require_specialist_selection": false
  },
  
  "specialist_assignment": {
    "strategy": "customer_choice", // or "auto_assign", "reception_assign", "none"
    "auto_assign_criteria": "availability", // or "rating", "load_balance"
    "show_specialist_photos": true,
    "show_specialist_ratings": true
  },
  
  "booking_rules": {
    "require_approval": false,
    "max_advance_booking_days": 30,
    "min_notice_hours": 2,
    "allow_walk_ins": false,
    "allow_same_day": true,
    "max_bookings_per_day": 5
  },
  
  "cancellation_policy": {
    "free_cancellation_hours": 24,
    "late_cancellation_fee": "deposit",
    "no_show_policy": {
      "first_offense": "warning",
      "second_offense": "deposit_required",
      "third_offense": "full_payment_required"
    }
  },
  
  "late_arrival_policy": {
    "grace_period_minutes": 10,
    "auto_cancel_after_minutes": 15,
    "reschedule_allowed": true
  },
  
  "payment_policy": {
    "strategy": "no_payment", // or "deposit", "full_payment", "pay_at_salon"
    "deposit": {
      "type": "percentage", // or "fixed"
      "amount": 20, // percentage or fixed amount
      "refundable": true,
      "refund_deadline_hours": 24,
      "transferable": false
    },
    "membership_included": false,
    "consultation_deposit": {
      "enabled": false,
      "amount": 10000,
      "deductible": true
    }
  },
  
  "availability_display": {
    "show_calendar": true,
    "show_time_slots": true,
    "group_by_day": true,
    "show_specialist_availability": true
  }
}
```

### Salon Booking Configuration (Overrides)

```json
{
  "overrides": {
    "booking_rules": {
      "max_advance_booking_days": 60, // Override provider's 30
      "min_notice_hours": 6, // Override provider's 2
      "allow_walk_ins": true // Override provider's false
    },
    
    "payment_policy": {
      "strategy": "deposit", // Override provider's no_payment
      "deposit": {
        "amount": 15000 // Override provider's 20%
      }
    },
    
    "specialist_assignment": {
      "strategy": "auto_assign" // Override provider's customer_choice
    }
  }
}
```

### Service Booking Configuration

```json
{
  "service_id": "uuid",
  "booking_requirements": {
    "require_consultation": true,
    "consultation_duration_minutes": 15,
    "require_specialist": true,
    "allowed_specialist_roles": ["stylist", "colorist"]
  },
  
  "payment_override": {
    "requires_deposit": true,
    "deposit_amount": 25000,
    "full_payment_required": false
  },
  
  "duration_options": [
    {"duration": 30, "price": 25000, "label": "Express"},
    {"duration": 45, "price": 35000, "label": "Standard"},
    {"duration": 60, "price": 45000, "label": "Extended"}
  ],
  
  "booking_flow_steps": ["service", "consultation", "specialist", "time", "payment", "confirm"]
}
```

## Booking Orchestrator Architecture

### Component Structure

```
Booking Orchestrator
    │
    ├── Booking Configuration Service
    │   ├── Get Provider Config
    │   ├── Get Salon Config
    │   ├── Get Service Config
    │   └── Merge Config (Service > Salon > Provider)
    │
    ├── Booking Rules Engine
    │   ├── Validate Booking Request
    │   ├── Check Approval Required
    │   ├── Check Notice Period
    │   ├── Check Advance Booking Limit
    │   └── Check Customer Eligibility
    │
    ├── Availability Engine
    │   ├── Get Available Dates
    │   ├── Get Available Slots
    │   ├── Get Specialist Availability
    │   └── Check Slot Availability
    │
    ├── Pricing Engine
    │   ├── Calculate Base Price
    │   ├── Apply Deposit Rules
    │   ├── Calculate Total
    │   └── Generate Price Breakdown
    │
    ├── Payment Rules Engine
    │   ├── Determine Payment Strategy
    │   ├── Calculate Deposit Amount
    │   ├── Check Refund Eligibility
    │   └── Generate Payment Instructions
    │
    ├── Specialist Assignment Engine
    │   ├── Get Available Specialists
    │   ├── Auto-Assign Best Specialist
    │   ├── Prioritize Customer's Specialists
    │   └── Filter by Service Requirements
    │
    └── Flow Generator
        ├── Generate Booking Steps
        ├── Determine Step Order
        ├── Skip Unnecessary Steps
        └── Generate UI Configuration
```

### Flow Generation Logic

**Input:** Service, Salon, Customer Context

**Process:**
1. Load merged configuration (Service > Salon > Provider)
2. Determine base flow from service configuration
3. Apply salon overrides
4. Apply provider defaults
5. Adjust based on customer context (returning customer?)
6. Generate final step sequence

**Output:** Dynamic booking flow configuration

**Example Flows:**

**Barbershop (Provider: customer_choice, no payment):**
```
Service → Specialist → Time → Confirm
```

**Spa (Salon: auto_assign, deposit required):**
```
Service → Time → Payment → Confirm
```

**Beauty Studio (Service: consultation required, full payment):**
```
Service → Consultation → Approval → Payment → Confirm
```

**Walk-in Salon (Salon: walk_ins only, no specialist selection):**
```
Service → Time → Confirm
```

**Returning Customer (Quick Book enabled):**
```
Quick Book (Pre-filled) → Confirm
```

## Database Schema

### provider_booking_config Table

```sql
CREATE TABLE provider_booking_config (
    id UUID PRIMARY KEY,
    provider_id UUID NOT NULL REFERENCES providers(id),
    
    -- Flow Configuration
    booking_flow JSON,
    
    -- Specialist Assignment
    specialist_assignment JSON,
    
    -- Booking Rules
    booking_rules JSON,
    
    -- Cancellation Policy
    cancellation_policy JSON,
    
    -- Late Arrival Policy
    late_arrival_policy JSON,
    
    -- Payment Policy
    payment_policy JSON,
    
    -- Availability Display
    availability_display JSON,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(provider_id)
);
```

### salon_booking_config Table

```sql
CREATE TABLE salon_booking_config (
    id UUID PRIMARY KEY,
    salon_id UUID NOT NULL REFERENCES salons(id),
    
    -- Override Configuration
    overrides JSON,
    
    -- Local Settings
    local_settings JSON,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(salon_id)
);
```

### service_booking_config Table

```sql
CREATE TABLE service_booking_config (
    id UUID PRIMARY KEY,
    service_id UUID NOT NULL REFERENCES services(id),
    
    -- Service-Specific Requirements
    booking_requirements JSON,
    
    -- Payment Override
    payment_override JSON,
    
    -- Duration Options
    duration_options JSON,
    
    -- Custom Flow Steps
    booking_flow_steps JSON,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(service_id)
);
```

## API Endpoints

### Configuration Management

**Provider Level:**
- `GET /providers/{id}/booking-config` - Get provider booking config
- `PUT /providers/{id}/booking-config` - Update provider booking config
- `POST /providers/{id}/booking-config/reset` - Reset to defaults

**Salon Level:**
- `GET /salons/{id}/booking-config` - Get salon booking config
- `PUT /salons/{id}/booking-config` - Update salon overrides
- `POST /salons/{id}/booking-config/reset` - Reset to provider defaults

**Service Level:**
- `GET /services/{id}/booking-config` - Get service booking config
- `PUT /services/{id}/booking-config` - Update service config
- `POST /services/{id}/booking-config/reset` - Reset to salon defaults

### Booking Orchestrator

**Flow Generation:**
- `POST /booking-orchestrator/generate-flow` - Generate booking flow for context
  ```json
  {
    "service_id": "uuid",
    "salon_id": "uuid",
    "customer_id": "uuid",
    "context": {
      "is_returning_customer": true,
      "last_booking": {...}
    }
  }
  ```

**Validation:**
- `POST /booking-orchestrator/validate` - Validate booking request against rules
- `POST /booking-orchestrator/check-availability` - Check availability with rules
- `POST /booking-orchestrator/calculate-price` - Calculate price with policies

**Specialist Assignment:**
- `GET /booking-orchestrator/specialists` - Get available specialists for booking
- `POST /booking-orchestrator/assign-specialist` - Auto-assign specialist

## Implementation Plan

### Phase 1: Configuration Foundation

1. **Create configuration tables**
   - provider_booking_config
   - salon_booking_config
   - service_booking_config

2. **Create configuration models**
   - ProviderBookingConfig
   - SalonBookingConfig
   - ServiceBookingConfig

3. **Create configuration service**
   - BookingConfigurationService
   - Merge config logic (Service > Salon > Provider)

4. **Seed default configurations**
   - Provider defaults
   - Common salon overrides
   - Service templates

### Phase 2: Rules Engine

1. **Create booking rules validator**
   - BookingRulesValidator
   - Validate notice period
   - Validate advance booking
   - Validate customer eligibility

2. **Create payment rules engine**
   - PaymentRulesEngine
   - Calculate deposit
   - Determine payment strategy
   - Generate payment instructions

3. **Create cancellation rules engine**
   - CancellationRulesEngine
   - Check cancellation eligibility
   - Calculate fees
   - Check no-show status

### Phase 3: Availability Engine

1. **Enhance availability service**
   - Add policy-aware availability
   - Filter by booking rules
   - Show specialist availability

2. **Create specialist assignment engine**
   - SpecialistAssignmentEngine
   - Auto-assign logic
   - Prioritize customer's specialists
   - Filter by service requirements

### Phase 4: Flow Orchestrator

1. **Create booking orchestrator service**
   - BookingOrchestrator
   - Flow generation logic
   - Step ordering
   - Context-aware flows

2. **Create flow configuration API**
   - Generate flow endpoints
   - Validation endpoints
   - Specialist assignment endpoints

### Phase 5: Frontend Integration

1. **Create booking flow renderer**
   - Dynamic step rendering
   - Configuration-driven UI
   - Real-time validation

2. **Update booking pages**
   - Use orchestrator API
   - Display dynamic flows
   - Show policy information

3. **Create configuration UI**
   - Provider config editor
   - Salon override editor
   - Service config editor

## Benefits of This Architecture

**For Salons:**
- Complete control over booking experience
- Ability to match brand identity
- Flexibility for different business models
- Easy policy updates without code changes

**For Customers:**
- Consistent experience within a salon
- Clear policies upfront
- No surprises
- Personalized flows

**For Platform:**
- Single booking engine serves all salon types
- Easy to add new booking patterns
- Configuration-driven development
- Scalable to thousands of salons

**For Future:**
- A/B test different flows
- Machine learning optimization
- Industry-specific templates
- Marketplace for booking patterns

## Next Steps

1. **Confirm hierarchy** - Provider > Salon > Service configuration precedence
2. **Review configuration schema** - Ensure all policies are covered
3. **Approve database schema** - Configuration tables
4. **Start Phase 1** - Configuration foundation
5. **Build orchestrator** - Flow generation logic
6. **Integrate with existing booking** - Gradual migration

This architecture transforms booking from a fixed flow into a dynamic, policy-driven engine that can serve any salon's operating model while maintaining a premium customer experience.

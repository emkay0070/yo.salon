# Journey Domain Architecture

Build Journey as a production-grade domain that supports the full vision from day one, with proper separation of concerns, event-driven compatibility, and respect for existing authoritative data sources.

---

## 1. Domain Model

### Core Entities

**JourneyMetric** (Growth Snapshot)
- Projection/read model derived from authoritative sources
- Tracks: clients_served, bookings_completed, rating, reviews_count, repeat_client_rate, services_performed, goals_completed, milestones_achieved
- Scoped by specialist_id or (specialist_id + assignment_id) for workplace context
- Refreshed on-demand or via events

**Goal**
- Numeric goals: target_value, current_value, unit, deadline
- Qualitative goals: milestones array, completion criteria
- Lifecycle: DRAFT → ACTIVE → PAUSED → COMPLETED → FAILED → CANCELLED
- Scope: SPECIALIST (career-wide) or WORKPLACE (provider-specific)
- Types: CLIENTS, BOOKINGS, REVENUE, RATING, RETENTION, CRAFT_MASTERY, CERTIFICATION, PORTFOLIO, CUSTOM

**GoalMilestone** (for qualitative goals)
- Steps within a goal (e.g., "Master Balayage" → 5 milestones)
- Each milestone: description, criteria, status, achieved_at
- References craft_taxonomy_id for craft-related milestones

**MilestoneDefinition**
- Platform-defined milestone templates
- Examples: FIRST_CLIENT, FIRST_REVIEW, FIRST_5_STAR, 100_SERVICES, 100_CLIENTS, NEW_EXPERTISE, CERTIFICATION_ACHIEVED
- Each has: trigger_criteria, icon, category, tier

**MilestoneAchievement**
- Specialist's earned milestones
- Links to MilestoneDefinition
- Stores achieved_at, context_data (e.g., which service, which provider)
- Can be auto-generated or manually verified

**GrowthOpportunity**
- Replaces hardcoded recommendations in SpecialistJourneyService
- Fields: title, description, type, priority, source (RULE/AI/SYSTEM), evidence (JSON), status (SUGGESTED/DISMISSED/ACCEPTED/COMPLETED/EXPIRED), scope, expires_at
- Source-agnostic architecture for future AI integration

### Scope Context

**JourneyScope**
- Enum: SPECIALIST_LEVEL, WORKPLACE_LEVEL
- Workplace-level goals reference specialist_assignment_id
- Specialist-level goals are career-wide

---

## 2. Database Schema / Migrations Required

### New Tables

```sql
-- Journey metrics (projection table)
journey_metrics:
  id (uuid, primary)
  specialist_id (uuid, fk specialists)
  specialist_assignment_id (uuid, nullable, fk specialist_assignments)
  scope (enum: SPECIALIST, WORKPLACE)
  metric_type (string)
  value (decimal)
  calculated_at (timestamp)
  period_start (timestamp, nullable)
  period_end (timestamp, nullable)
  indexes: [specialist_id, scope], [specialist_assignment_id]

-- Goals (refactored from specialist_goals)
journey_goals:
  id (uuid, primary)
  specialist_id (uuid, fk specialists)
  specialist_assignment_id (uuid, nullable, fk specialist_assignments)
  scope (enum: SPECIALIST, WORKPLACE)
  title (string)
  description (text, nullable)
  goal_type (enum: NUMERIC, QUALITATIVE)
  target_value (decimal, nullable)
  current_value (decimal, nullable)
  unit (string, nullable)
  deadline (timestamp, nullable)
  status (enum: DRAFT, ACTIVE, PAUSED, COMPLETED, FAILED, CANCELLED)
  priority (enum: LOW, MEDIUM, HIGH)
  created_by (enum: SPECIALIST, SYSTEM)
  started_at (timestamp, nullable)
  completed_at (timestamp, nullable)
  data (json, nullable)
  indexes: [specialist_id, status], [specialist_assignment_id]

-- Goal milestones (for qualitative goals)
journey_goal_milestones:
  id (uuid, primary)
  goal_id (uuid, fk journey_goals)
  title (string)
  description (text, nullable)
  criteria (json)
  craft_taxonomy_id (uuid, nullable, fk craft_taxonomy)
  order (integer)
  status (enum: PENDING, IN_PROGRESS, COMPLETED, SKIPPED)
  achieved_at (timestamp, nullable)
  data (json, nullable)
  indexes: [goal_id, order]

-- Milestone definitions (platform templates)
journey_milestone_definitions:
  id (uuid, primary)
  code (string, unique) -- e.g., FIRST_CLIENT, FIRST_5_STAR
  title (string)
  description (text)
  icon (string, nullable)
  category (enum: CLIENT, SERVICE, REPUTATION, CRAFT, BUSINESS)
  tier (enum: BRONZE, SILVER, GOLD, PLATINUM)
  trigger_criteria (json) -- rules for auto-detection
  is_active (boolean, default true)
  indexes: [code], [category]

-- Milestone achievements (specialist's earned milestones)
journey_milestone_achievements:
  id (uuid, primary)
  specialist_id (uuid, fk specialists)
  milestone_definition_id (uuid, fk journey_milestone_definitions)
  specialist_assignment_id (uuid, nullable, fk specialist_assignments)
  achieved_at (timestamp)
  context_data (json) -- e.g., service_id, booking_id
  verification_method (enum: AUTO, MANUAL)
  indexes: [specialist_id, milestone_definition_id]

-- Growth opportunities (replaces hardcoded recommendations)
journey_growth_opportunities:
  id (uuid, primary)
  specialist_id (uuid, fk specialists)
  specialist_assignment_id (uuid, nullable, fk specialist_assignments)
  scope (enum: SPECIALIST, WORKPLACE)
  title (string)
  description (text, nullable)
  opportunity_type (enum: RETENTION, AVAILABILITY, CRAFT_EXPANSION, PRICING, MARKETING, SKILL_DEVELOPMENT)
  priority (enum: LOW, MEDIUM, HIGH, URGENT)
  source (enum: RULE, AI, SYSTEM)
  evidence (json) -- data backing the recommendation
  action_suggestion (text, nullable)
  estimated_impact (json, nullable)
  status (enum: SUGGESTED, DISMISSED, ACCEPTED, IN_PROGRESS, COMPLETED, EXPIRED)
  dismissed_at (timestamp, nullable)
  accepted_at (timestamp, nullable)
  completed_at (timestamp, nullable)
  expires_at (timestamp, nullable)
  indexes: [specialist_id, status], [specialist_assignment_id]
```

### Table Modifications

```sql
-- Refactor specialist_goals → journey_goals (migration)
-- Drop specialist_goals table after data migration
```

---

## 3. Model Relationships

```
Specialist
  ├─ journeyMetrics (hasMany, scope: SPECIALIST)
  ├─ goals (hasMany, scope: SPECIALIST)
  ├─ milestoneAchievements (hasMany)
  └─ growthOpportunities (hasMany)

SpecialistAssignment
  ├─ journeyMetrics (hasMany, scope: WORKPLACE)
  ├─ goals (hasMany, scope: WORKPLACE)
  └─ growthOpportunities (hasMany)

Goal
  └─ milestones (hasMany)

MilestoneDefinition
  └─ achievements (hasMany via MilestoneAchievement)

MilestoneAchievement
  ├─ specialist (belongsTo)
  ├─ milestoneDefinition (belongsTo)
  └─ specialistAssignment (belongsTo, nullable)

GrowthOpportunity
  ├─ specialist (belongsTo)
  └─ specialistAssignment (belongsTo, nullable)

CraftTaxonomy
  └─ goalMilestones (hasMany)
```

---

## 4. Goal Lifecycle / State Machine

```
DRAFT
  ↓ (activate)
ACTIVE
  ↓ (pause)        ↓ (complete)    ↓ (fail)      ↓ (cancel)
PAUSED            COMPLETED        FAILED         CANCELLED
  ↓ (resume)                         ↓ (retry)
ACTIVE                                    ACTIVE
```

**Transitions:**
- DRAFT → ACTIVE: Specialist activates or system auto-activates
- ACTIVE → PAUSED: Specialist pauses goal
- PAUSED → ACTIVE: Specialist resumes
- ACTIVE → COMPLETED: Target reached (numeric) or all milestones complete (qualitative)
- ACTIVE → FAILED: Deadline passed without completion
- ACTIVE → CANCELLED: Specialist cancels
- FAILED → ACTIVE: Specialist retries (new deadline)

**State Guards:**
- Cannot complete if current_value < target_value (numeric)
- Cannot complete if any milestone not achieved (qualitative)
- Cannot activate if deadline is in the past

---

## 5. Milestone Definition + Achievement Architecture

**Separation of Concerns:**

```
MilestoneDefinition (Platform)
├─ code: "FIRST_CLIENT"
├─ title: "First Client"
├─ trigger_criteria: {"event": "booking_completed", "count": 1}
└─ tier: BRONZE

MilestoneAchievement (Specialist)
├─ specialist_id: Emma
├─ milestone_definition_id: FIRST_CLIENT
├─ achieved_at: 2025-01-15
└─ context_data: {"booking_id": "..."}
```

**Auto-Detection Strategy:**
- Event listeners (future): BookingCompleted → check milestone criteria
- Synchronous polling (V1): JourneyMetricService evaluates on page load
- Manual verification: Specialist uploads certification, admin verifies

**Milestone Categories:**
- CLIENT: First client, 10 clients, 100 clients, 1000 clients
- SERVICE: First booking, 100 services, 500 services
- REPUTATION: First review, First 5-star, 4.9+ rating
- CRAFT: New expertise, Certification achieved, Skill mastery
- BUSINESS: First revenue milestone, Fully booked week

---

## 6. Growth Opportunity Architecture

**Source-Agnostic Design:**

```php
interface GrowthOpportunityGenerator {
    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array;
}

class RuleBasedGenerator implements GrowthOpportunityGenerator {
    // V1: Rule-based recommendations
}

class AIGenerator implements GrowthOpportunityGenerator {
    // Future: AI-powered recommendations
}
```

**Opportunity Lifecycle:**
```
SUGGESTED
  ↓ (dismiss)    ↓ (accept)
DISMISSED       IN_PROGRESS
                 ↓ (complete)
                 COMPLETED
```

**Evidence Structure:**
```json
{
  "metric": "repeat_client_rate",
  "current_value": 0.31,
  "benchmark": 0.45,
  "gap": -0.14,
  "trend": "declining",
  "period": "last_90_days"
}
```

**Estimated Impact:**
```json
{
  "metric": "returning_clients",
  "range": [8, 12],
  "unit": "per_month",
  "confidence": "high"
}
```

---

## 7. Metric / Projection Strategy

**Consumer of Truth Pattern:**

```
Authoritative Sources:
  ├─ Bookings → bookings_completed, services_performed
  ├─ CustomerSpecialist → clients_served, repeat_client_rate
  ├─ Reviews → rating, reviews_count
  ├─ SpecialistGoals → goals_completed
  └─ JourneyMilestoneAchievements → milestones_achieved

JourneyMetricService:
  ├─ Queries authoritative sources
  ├─ Calculates derived metrics (e.g., repeat rate)
  ├─ Caches in journey_metrics table
  └─ Refreshes on-demand or via events
```

**Metric Types:**
- **COUNT**: Simple aggregation (e.g., COUNT(bookings))
- **RATE**: Derived calculation (e.g., repeat_clients / total_clients)
- **AVERAGE**: Aggregation (e.g., AVG(reviews.rating))
- **SUM**: Financial (e.g., SUM(bookings.amount))

**Scope Handling:**
- Specialist-level: Query all bookings/reviews for specialist
- Workplace-level: Query bookings/reviews filtered by provider_id + specialist_id

**Refresh Strategy (V1):**
- On-demand: Calculate when Journey page loads
- Lazy cache: Store in journey_metrics with TTL
- No queue workers: Synchronous calculation acceptable for V1

**Event-Driven (Future):**
- BookingCompleted → increment bookings_completed metric
- ReviewSubmitted → update rating/reviews_count metric
- GoalCompleted → increment goals_completed metric

---

## 8. Workplace vs Specialist Scope Rules

**Scope Determination:**

```php
class JourneyScopeResolver {
    public function resolve(Specialist $specialist, ?SpecialistAssignment $assignment): string {
        if ($assignment && $assignment->status === 'active') {
            return 'WORKPLACE';
        }
        return 'SPECIALIST';
    }
}
```

**Data Isolation:**
- Specialist-level goals: No assignment_id, career-wide
- Workplace-level goals: Has assignment_id, provider-specific
- UI route: `/specialist-portal/journey` → specialist-level
- UI route: `/{slug}/specialist/journey` → workplace-level (requires active assignment)

**Security:**
- Workplace journey data must verify specialist has active assignment to provider
- Cannot access another provider's journey by changing slug
- SpecialistPortalController already handles this via context endpoint

**Metric Aggregation:**
- Specialist-level metrics: Aggregate across all providers
- Workplace-level metrics: Filter by provider_id

---

## 9. Event / Integration Points

**Current Synchronous Integration (V1):**

```php
class JourneyMetricService {
    public function calculateMetrics(Specialist $specialist, ?SpecialistAssignment $assignment) {
        $scope = $this->scopeResolver->resolve($specialist, $assignment);
        
        $bookingsQuery = Booking::where('specialist_id', $specialist->id);
        if ($scope === 'WORKPLACE' && $assignment) {
            $bookingsQuery->where('provider_id', $assignment->provider_id);
        }
        
        $metrics = [
            'clients_served' => CustomerSpecialist::where('specialist_id', $specialist->id)->count(),
            'bookings_completed' => $bookingsQuery->where('status', 'completed')->count(),
            'rating' => Review::where('specialist_id', $specialist->id)->avg('rating') ?? 0,
            // ... other metrics
        ];
        
        $this->storeMetrics($specialist, $assignment, $metrics);
        return $metrics;
    }
}
```

**Future Event-Driven Integration:**

```php
// Event Listeners (not implemented in V1)
class BookingCompletedListener {
    public function handle(BookingCompleted $event) {
        $specialistId = $event->booking->specialist_id;
        $providerId = $event->booking->provider_id;
        
        // Update metrics
        JourneyMetricService::increment($specialistId, 'bookings_completed', $providerId);
        
        // Check milestones
        MilestoneEvaluator::check($specialistId, 'FIRST_CLIENT', $providerId);
        
        // Update goal progress
        GoalProgressService::update($specialistId, 'CLIENTS', $providerId);
    }
}
```

**Integration Points:**
- Booking: bookings_completed, services_performed, revenue
- Review: rating, reviews_count, reputation milestones
- CustomerSpecialist: clients_served, repeat_client_rate
- SpecialistExpertise: craft milestones, skill mastery
- SpecialistGoal: goals_completed
- SpecialistCareerEvent: manual milestone achievements

---

## 10. API Contract

**GET /v1/specialist-portal/journey**
```json
{
  "growth_snapshot": {
    "clients_served": 184,
    "bookings_completed": 327,
    "rating": 4.9,
    "reviews_count": 156,
    "repeat_client_rate": 0.68,
    "services_performed": 412,
    "goals_completed": 5,
    "milestones_achieved": 12,
    "experience_years": 4
  },
  "goals": [
    {
      "id": "uuid",
      "title": "Reach 250 clients",
      "goal_type": "NUMERIC",
      "current_value": 184,
      "target_value": 250,
      "unit": "clients",
      "deadline": "2026-12-31",
      "status": "ACTIVE",
      "scope": "SPECIALIST",
      "progress_percent": 73.6
    }
  ],
  "milestones": [
    {
      "id": "uuid",
      "definition_code": "FIRST_CLIENT",
      "title": "First Client",
      "achieved_at": "2025-01-15",
      "tier": "BRONZE",
      "context": {"provider_name": "Em Cuts"}
    }
  ],
  "growth_opportunities": [
    {
      "id": "uuid",
      "title": "Improve client retention",
      "description": "Your repeat rate has dropped 7%.",
      "type": "RETENTION",
      "priority": "HIGH",
      "source": "RULE",
      "evidence": {
        "current_rate": 0.31,
        "benchmark": 0.45
      },
      "action_suggestion": "Send follow-up reminders 2-3 weeks after service.",
      "status": "SUGGESTED"
    }
  ]
}
```

**GET /v1/specialist-portal/journey?provider_slug=em-cuts**
- Same structure, but all metrics/goals scoped to workplace

**POST /v1/specialist-portal/journey/goals**
```json
{
  "title": "Master Balayage",
  "goal_type": "QUALITATIVE",
  "scope": "SPECIALIST",
  "milestones": [
    {"title": "Add Balayage to Craft", "criteria": {"craft_taxonomy_id": "uuid"}},
    {"title": "Complete 10 Balayage bookings", "criteria": {"service_count": 10}},
    {"title": "Upload certification", "criteria": {"verification": "manual"}}
  ]
}
```

**POST /v1/specialist-portal/journey/opportunities/{id}/dismiss**
**POST /v1/specialist-portal/journey/opportunities/{id}/accept**

---

## 11. Migration Strategy from Existing Code

**Phase 1: Create New Tables**
- Create journey_metrics, journey_goals, journey_goal_milestones
- Create journey_milestone_definitions, journey_milestone_achievements
- Create journey_growth_opportunities
- Run migrations

**Phase 2: Seed Milestone Definitions**
- Insert platform milestone definitions (FIRST_CLIENT, FIRST_REVIEW, etc.)
- Use a seeder: `JourneyMilestoneDefinitionSeeder`

**Phase 3: Migrate Existing Goals**
```php
// Migration script
$oldGoals = DB::table('specialist_goals')->get();
foreach ($oldGoals as $old) {
    DB::table('journey_goals')->insert([
        'id' => $old->id,
        'specialist_id' => $old->specialist_id,
        'scope' => 'SPECIALIST',
        'title' => mapGoalTypeToTitle($old->goal_type),
        'goal_type' => 'NUMERIC',
        'target_value' => $old->target_value,
        'status' => mapStatus($old->status),
        'deadline' => $old->ends_at,
        // ... other fields
    ]);
}
```

**Phase 4: Migrate Career Events to Milestones**
- Existing SpecialistCareerEvent with type 'milestone' → JourneyMilestoneAchievement
- Create milestone definitions for existing events if needed

**Phase 5: Update SpecialistJourneyService**
- Replace hardcoded arrays with queries to new tables
- Implement GoalProgressService for real progress calculation
- Implement MilestoneEvaluator for auto-detection
- Implement GrowthOpportunityGenerator for rule-based recommendations

**Phase 6: Update Controller**
- SpecialistPortalController::journey() uses new JourneyService
- Support provider_slug parameter for workplace scope

**Phase 7: Drop Old Tables**
- After verification, drop specialist_goals table
- Remove hardcoded logic from SpecialistJourneyService

**Rollback Plan:**
- Keep specialist_goals table until V1 is stable
- Feature flag: use old service if new service fails
- Database backups before migration

---

## 12. Test Strategy

**Unit Tests:**
- Goal lifecycle state transitions
- Milestone criteria evaluation
- Growth opportunity generation (rule-based)
- Metric calculation (scope isolation)
- Scope resolution logic

**Integration Tests:**
- JourneyMetricService with real Booking/Review data
- GoalProgressService updates on booking completion
- MilestoneEvaluator detects first client/review
- Workplace vs specialist scope isolation
- API endpoints return correct structure

**Edge Cases:**
- Specialist with no bookings → metrics return 0
- Specialist with multiple assignments → correct scope isolation
- Goal deadline passed → auto-fail transition
- Qualitative goal with no milestones → validation error
- Growth opportunity expires → status change

**Performance Tests:**
- Metric calculation with 10,000+ bookings
- Milestone evaluation for specialist with 100+ achievements
- Concurrent goal updates

**Data Integrity Tests:**
- Foreign key constraints
- Cascade deletes (specialist deleted → journey data deleted)
- Scope validation (workplace goal without assignment_id)

**Migration Tests:**
- All existing goals migrated correctly
- Career events converted to milestones
- No data loss during migration
- Rollback restores original state

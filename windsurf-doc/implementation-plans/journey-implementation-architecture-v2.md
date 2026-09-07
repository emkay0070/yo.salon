# Journey Implementation Architecture (V5 Quality Gate)

This document shows the exact files, migrations, models, and services that will be built to implement the Journey domain architecture according to the specification, with V5 quality gate improvements for data integrity, scope isolation, concurrency, historical metrics, idempotency, extensibility, and authorization.

---

## V5 Quality Gate Requirements

**DO NOT implement blindly.** This architecture must be reviewed against existing Specialist, SpecialistAssignment, Craft Taxonomy, Booking, Review, and workplace-context architecture. Key improvements:

1. **Historical metrics**: journey_metrics stores time-series data, not overwriting snapshots
2. **Layer separation**: calculation → persistence → presentation are distinct
3. **Strict scope**: PERSONAL career data vs WORKPLACE-specific data with assignment status validation
4. **Assignment awareness**: Distinguish current vs old/ended assignments
5. **Idempotency**: Database-level unique constraints for milestone achievements
6. **Stable identifiers**: Growth opportunities use rule keys, not fragile title matching
7. **Provider interface**: Growth Opportunity Engine supports future AI without redesign
8. **Authoritative progress**: Goals derive from metric_key, not manually maintained current_value
9. **Loose coupling**: Journey references Craft Taxonomy but isn't tightly coupled
10. **Facade pattern**: JourneyQueryService assembles response, controller stays thin

---

## File Structure

### New Models
```
backend/app/Models/JourneyMetric.php
backend/app/Models/JourneyGoal.php
backend/app/Models/JourneyGoalMilestone.php
backend/app/Models/JourneyMilestoneDefinition.php
backend/app/Models/JourneyMilestoneAchievement.php
backend/app/Models/JourneyGrowthOpportunity.php
```

### New Migrations
```
backend/database/migrations/YYYY_MM_DD_HHMMSS_create_journey_metrics_table.php
backend/database/migrations/YYYY_MM_DD_HHMMSS_create_journey_goals_table.php
backend/database/migrations/YYYY_MM_DD_HHMMSS_create_journey_goal_milestones_table.php
backend/database/migrations/YYYY_MM_DD_HHMMSS_create_journey_milestone_definitions_table.php
backend/database/migrations/YYYY_MM_DD_HHMMSS_create_journey_milestone_achievements_table.php
backend/database/migrations/YYYY_MM_DD_HHMMSS_create_journey_growth_opportunities_table.php
backend/database/migrations/YYYY_MM_DD_HHMMSS_migrate_specialist_goals_to_journey_goals.php
backend/database/migrations/YYYY_MM_DD_HHMMSS_drop_specialist_goals_table.php
```

### New Services
```
backend/app/Domain/Journey/Services/JourneyMetricCalculator.php (calculation)
backend/app/Domain/Journey/Services/JourneyMetricRepository.php (persistence)
backend/app/Domain/Journey/Services/JourneyGoalService.php
backend/app/Domain/Journey/Services/JourneyMilestoneService.php
backend/app/Domain/Journey/Services/JourneyGrowthOpportunityService.php
backend/app/Domain/Journey/Services/JourneyScopeResolver.php
backend/app/Domain/Journey/Services/JourneyQueryService.php (facade)
backend/app/Domain/Journey/Services/GrowthOpportunityEngine.php (orchestrator)
backend/app/Domain/Journey/Services/GrowthOpportunityGenerator.php (interface)
backend/app/Domain/Journey/Rules/RetentionRule.php
backend/app/Domain/Journey/Rules/ReputationRule.php
backend/app/Domain/Journey/Rules/AvailabilityRule.php
backend/app/Domain/Journey/Rules/CraftExpansionRule.php
backend/app/Domain/Journey/Rules/PricingRule.php
backend/app/Domain/Journey/Rules/ClientGrowthRule.php
```

### New Seeders
```
backend/database/seeders/JourneyMilestoneDefinitionSeeder.php
```

### Modified Files
```
backend/app/Domain/Specialist/Services/SpecialistJourneyService.php (refactor)
backend/app/Http/Controllers/SpecialistPortalController.php (add journey endpoint)
backend/app/Models/Specialist.php (add relationships)
backend/app/Models/SpecialistAssignment.php (add relationships)
```

---

## Migration Details

### 1. journey_metrics (historical time-series)
```php
Schema::create('journey_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('specialist_id');
    $table->uuid('specialist_assignment_id')->nullable();
    $table->enum('scope', ['SPECIALIST', 'WORKPLACE'])->default('SPECIALIST');
    $table->string('metric_key'); // clients_served, bookings_completed, etc.
    $table->decimal('value', 15, 2)->default(0);
    $table->timestamp('recorded_at')->useCurrent(); // When this metric was recorded
    $table->timestamp('period_start')->nullable(); // For aggregated periods (e.g., monthly)
    $table->timestamp('period_end')->nullable();
    $table->timestamps();

    $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
    $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
    $table->index(['specialist_id', 'scope', 'metric_key', 'recorded_at']);
    $table->index(['specialist_assignment_id', 'metric_key', 'recorded_at']);
    $table->index(['period_start', 'period_end']);
});
```

### 2. journey_goals (with metric_key for authoritative progress)
```php
Schema::create('journey_goals', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('specialist_id');
    $table->uuid('specialist_assignment_id')->nullable();
    $table->enum('scope', ['SPECIALIST', 'WORKPLACE'])->default('SPECIALIST');
    $table->string('title');
    $table->text('description')->nullable();
    $table->enum('goal_type', ['NUMERIC', 'QUALITATIVE']);
    $table->string('metric_key')->nullable(); // Derives progress from authoritative source
    $table->decimal('target_value', 15, 2)->nullable();
    $table->decimal('current_value', 15, 2)->nullable(); // Only for manual/custom goals
    $table->string('unit')->nullable();
    $table->timestamp('deadline')->nullable();
    $table->enum('status', ['DRAFT', 'ACTIVE', 'PAUSED', 'COMPLETED', 'FAILED', 'CANCELLED'])->default('DRAFT');
    $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH'])->default('MEDIUM');
    $table->enum('created_by', ['SPECIALIST', 'SYSTEM'])->default('SPECIALIST');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->json('metadata')->nullable(); // Extensible storage
    $table->timestamps();

    $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
    $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
    $table->index(['specialist_id', 'status']);
    $table->index(['specialist_assignment_id']);
    $table->index(['metric_key']);
});
```

### 3. journey_goal_milestones
```php
Schema::create('journey_goal_milestones', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('goal_id');
    $table->string('title');
    $table->text('description')->nullable();
    $table->json('criteria');
    $table->uuid('craft_taxonomy_id')->nullable();
    $table->integer('order')->default(0);
    $table->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'SKIPPED'])->default('PENDING');
    $table->timestamp('achieved_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->foreign('goal_id')->references('id')->on('journey_goals')->onDelete('cascade');
    $table->foreign('craft_taxonomy_id')->references('id')->on('craft_taxonomy')->onDelete('set null');
    $table->index(['goal_id', 'order']);
});
```

### 4. journey_milestone_definitions
```php
Schema::create('journey_milestone_definitions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('code')->unique();
    $table->string('title');
    $table->text('description');
    $table->string('icon')->nullable();
    $table->enum('category', ['CLIENT', 'SERVICE', 'REPUTATION', 'CRAFT', 'BUSINESS']);
    $table->enum('tier', ['BRONZE', 'SILVER', 'GOLD', 'PLATINUM'])->default('BRONZE');
    $table->json('trigger_criteria');
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['code']);
    $table->index(['category']);
});
```

### 5. journey_milestone_achievements (with unique constraint for idempotency)
```php
Schema::create('journey_milestone_achievements', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('specialist_id');
    $table->uuid('milestone_definition_id');
    $table->uuid('specialist_assignment_id')->nullable();
    $table->timestamp('achieved_at');
    $table->json('context_data')->nullable();
    $table->enum('verification_method', ['AUTO', 'MANUAL'])->default('AUTO');
    $table->timestamps();

    $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
    $table->foreign('milestone_definition_id')->references('id')->on('journey_milestone_definitions')->onDelete('cascade');
    $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
    
    // Unique constraint for idempotency - prevents duplicate achievements
    $table->unique(['specialist_id', 'milestone_definition_id', 'specialist_assignment_id'], 'unique_achievement');
    $table->index(['specialist_id']);
});
```

### 6. journey_growth_opportunities (with rule_key for stable identification)
```php
Schema::create('journey_growth_opportunities', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('specialist_id');
    $table->uuid('specialist_assignment_id')->nullable();
    $table->enum('scope', ['SPECIALIST', 'WORKPLACE'])->default('SPECIALIST');
    $table->string('rule_key')->nullable(); // Stable identifier for rule-based opportunities
    $table->string('title');
    $table->text('description')->nullable();
    $table->enum('opportunity_type', ['RETENTION', 'AVAILABILITY', 'CRAFT_EXPANSION', 'PRICING', 'MARKETING', 'SKILL_DEVELOPMENT']);
    $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])->default('MEDIUM');
    $table->enum('source', ['RULE', 'AI', 'SYSTEM'])->default('RULE');
    $table->json('evidence')->nullable();
    $table->text('action_suggestion')->nullable();
    $table->json('estimated_impact')->nullable();
    $table->enum('status', ['SUGGESTED', 'DISMISSED', 'ACCEPTED', 'IN_PROGRESS', 'COMPLETED', 'EXPIRED'])->default('SUGGESTED');
    $table->timestamp('dismissed_at')->nullable();
    $table->timestamp('accepted_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();

    $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
    $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
    
    // Unique constraint on rule_key to prevent duplicate opportunities from same rule
    $table->unique(['specialist_id', 'rule_key', 'specialist_assignment_id'], 'unique_opportunity');
    $table->index(['specialist_id', 'status']);
    $table->index(['specialist_assignment_id']);
});
```

---

## Model Details

### JourneyMetric.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JourneyMetric extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'specialist_assignment_id',
        'scope',
        'metric_key',
        'value',
        'recorded_at',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'recorded_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function specialistAssignment(): BelongsTo
    {
        return $this->belongsTo(SpecialistAssignment::class);
    }

    public function scopeForSpecialist($query, string $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }

    public function scopeForWorkplace($query, string $assignmentId)
    {
        return $query->where('specialist_assignment_id', $assignmentId);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    public function scopeForMetric($query, string $metricKey)
    {
        return $query->where('metric_key', $metricKey);
    }
}
```

### JourneyGoal.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JourneyGoal extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'specialist_assignment_id',
        'scope',
        'title',
        'description',
        'goal_type',
        'metric_key',
        'target_value',
        'current_value',
        'unit',
        'deadline',
        'status',
        'priority',
        'created_by',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'deadline' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function specialistAssignment(): BelongsTo
    {
        return $this->belongsTo(SpecialistAssignment::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(JourneyGoalMilestone::class)->orderBy('order');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeForSpecialist($query, string $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }

    public function scopeForWorkplace($query, string $assignmentId)
    {
        return $query->where('specialist_assignment_id', $assignmentId);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->goal_type === 'NUMERIC' && $this->target_value > 0) {
            return ($this->current_value / $this->target_value) * 100;
        }
        return 0;
    }

    public function hasCurrentAssignment(): bool
    {
        if (!$this->specialist_assignment_id) return false;
        
        $assignment = $this->specialistAssignment;
        return $assignment && $assignment->status === 'active';
    }
}
```

### JourneyGoalMilestone.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JourneyGoalMilestone extends Model
{
    use HasUuids;

    protected $fillable = [
        'goal_id',
        'title',
        'description',
        'criteria',
        'craft_taxonomy_id',
        'order',
        'status',
        'achieved_at',
        'metadata',
    ];

    protected $casts = [
        'criteria' => 'array',
        'achieved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(JourneyGoal::class);
    }

    public function craftTaxonomy(): BelongsTo
    {
        return $this->belongsTo(CraftTaxonomy::class);
    }
}
```

### JourneyMilestoneDefinition.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JourneyMilestoneDefinition extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'title',
        'description',
        'icon',
        'category',
        'tier',
        'trigger_criteria',
        'is_active',
    ];

    protected $casts = [
        'trigger_criteria' => 'array',
        'is_active' => 'boolean',
    ];

    public function achievements(): HasMany
    {
        return $this->hasMany(JourneyMilestoneAchievement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
```

### JourneyMilestoneAchievement.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JourneyMilestoneAchievement extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'milestone_definition_id',
        'specialist_assignment_id',
        'achieved_at',
        'context_data',
        'verification_method',
    ];

    protected $casts = [
        'achieved_at' => 'datetime',
        'context_data' => 'array',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function milestoneDefinition(): BelongsTo
    {
        return $this->belongsTo(JourneyMilestoneDefinition::class);
    }

    public function specialistAssignment(): BelongsTo
    {
        return $this->belongsTo(SpecialistAssignment::class);
    }
}
```

### JourneyGrowthOpportunity.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JourneyGrowthOpportunity extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'specialist_assignment_id',
        'scope',
        'rule_key',
        'title',
        'description',
        'opportunity_type',
        'priority',
        'source',
        'evidence',
        'action_suggestion',
        'estimated_impact',
        'status',
        'dismissed_at',
        'accepted_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'estimated_impact' => 'array',
        'dismissed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function specialistAssignment(): BelongsTo
    {
        return $this->belongsTo(SpecialistAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['SUGGESTED', 'ACCEPTED', 'IN_PROGRESS']);
    }

    public function scopeForSpecialist($query, string $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }
}
```

---

## Service Architecture (Separated Layers)

### Layer 1: Calculation - JourneyMetricCalculator
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Booking;
use App\Models\Review;
use App\Models\CustomerSpecialist;

class JourneyMetricCalculator
{
    /**
     * Calculate current metrics from authoritative sources
     * Does NOT persist - that's the repository's job
     */
    public function calculate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $scope = $assignment ? 'WORKPLACE' : 'SPECIALIST';
        $assignmentId = $assignment?->id;

        // Build base queries with scope filtering
        $bookingsQuery = Booking::where('specialist_id', $specialist->id);
        $reviewsQuery = Review::where('specialist_id', $specialist->id);

        if ($scope === 'WORKPLACE' && $assignment) {
            $bookingsQuery->where('provider_id', $assignment->provider_id);
            $reviewsQuery->where('provider_id', $assignment->provider_id);
        }

        // Calculate from authoritative sources
        return [
            'clients_served' => CustomerSpecialist::where('specialist_id', $specialist->id)->count(),
            'bookings_completed' => $bookingsQuery->where('status', 'completed')->count(),
            'rating' => $reviewsQuery->avg('rating') ?? 0,
            'reviews_count' => $reviewsQuery->count(),
            'repeat_client_rate' => $this->calculateRepeatRate($specialist),
            'services_performed' => $bookingsQuery->where('status', 'completed')->count(),
            'experience_years' => $specialist->years_experience ?? 0,
        ];
    }

    private function calculateRepeatRate(Specialist $specialist): float
    {
        $totalClients = CustomerSpecialist::where('specialist_id', $specialist->id)->count();
        if ($totalClients === 0) return 0;

        $repeatClients = CustomerSpecialist::where('specialist_id', $specialist->id)
            ->where('total_bookings', '>', 1)
            ->count();

        return $repeatClients / $totalClients;
    }
}
```

### Layer 2: Persistence - JourneyMetricRepository
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyMetric;

class JourneyMetricRepository
{
    /**
     * Store metrics as time-series data (historical)
     * Does NOT calculate - that's the calculator's job
     */
    public function store(Specialist $specialist, ?SpecialistAssignment $assignment, array $metrics): void
    {
        $scope = $assignment ? 'WORKPLACE' : 'SPECIALIST';
        $assignmentId = $assignment?->id;

        foreach ($metrics as $metricKey => $value) {
            JourneyMetric::create([
                'specialist_id' => $specialist->id,
                'specialist_assignment_id' => $assignmentId,
                'scope' => $scope,
                'metric_key' => $metricKey,
                'value' => $value,
                'recorded_at' => now(),
            ]);
        }
    }

    /**
     * Get latest metric value
     */
    public function getLatest(Specialist $specialist, ?SpecialistAssignment $assignment, string $metricKey): ?float
    {
        $query = JourneyMetric::where('specialist_id', $specialist->id)
            ->where('metric_key', $metricKey);

        if ($assignment) {
            $query->where('specialist_assignment_id', $assignment->id);
        }

        return $query->latest()->first()?->value;
    }

    /**
     * Get historical metrics for a period
     */
    public function getHistorical(Specialist $specialist, ?SpecialistAssignment $assignment, string $metricKey, \DateTime $start, \DateTime $end): array
    {
        $query = JourneyMetric::where('specialist_id', $specialist->id)
            ->where('metric_key', $metricKey)
            ->whereBetween('recorded_at', [$start, $end]);

        if ($assignment) {
            $query->where('specialist_assignment_id', $assignment->id);
        }

        return $query->orderBy('recorded_at')->get()->toArray();
    }
}
```

### Layer 3: Scope Resolution - JourneyScopeResolver
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;

class JourneyScopeResolver
{
    /**
     * Resolve scope based on assignment status
     * Only active assignments represent current workplace context
     */
    public function resolve(Specialist $specialist, ?SpecialistAssignment $assignment): string
    {
        if ($assignment && $assignment->status === 'active') {
            return 'WORKPLACE';
        }
        return 'SPECIALIST';
    }

    /**
     * Validate access to workplace journey
     * Ensures specialist has active assignment to provider
     */
    public function validateAccess(Specialist $specialist, string $providerSlug): ?SpecialistAssignment
    {
        return $specialist->assignments()
            ->where('status', 'active')
            ->whereHas('provider', fn($q) => $q->where('slug', $providerSlug))
            ->first();
    }

    /**
     * Check if assignment is current (active)
     * Prevents treating old/ended assignments as current workplace
     */
    public function isCurrentAssignment(?SpecialistAssignment $assignment): bool
    {
        return $assignment && $assignment->status === 'active';
    }
}
```

### JourneyGoalService (with metric_key support)
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyGoal;
use App\Models\JourneyGoalMilestone;
use App\Domain\Journey\Services\JourneyMetricRepository;

class JourneyGoalService
{
    private JourneyMetricRepository $metricRepository;

    public function __construct(JourneyMetricRepository $metricRepository)
    {
        $this->metricRepository = $metricRepository;
    }

    public function createGoal(array $data, Specialist $specialist, ?SpecialistAssignment $assignment): JourneyGoal
    {
        $scope = $assignment ? 'WORKPLACE' : 'SPECIALIST';

        $goal = JourneyGoal::create([
            'specialist_id' => $specialist->id,
            'specialist_assignment_id' => $assignment?->id,
            'scope' => $scope,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'goal_type' => $data['goal_type'],
            'metric_key' => $data['metric_key'] ?? null, // For authoritative progress
            'target_value' => $data['target_value'] ?? null,
            'unit' => $data['unit'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'priority' => $data['priority'] ?? 'MEDIUM',
            'created_by' => 'SPECIALIST',
            'status' => 'DRAFT',
        ]);

        // Create milestones for qualitative goals
        if ($data['goal_type'] === 'QUALITATIVE' && isset($data['milestones'])) {
            foreach ($data['milestones'] as $index => $milestoneData) {
                JourneyGoalMilestone::create([
                    'goal_id' => $goal->id,
                    'title' => $milestoneData['title'],
                    'description' => $milestoneData['description'] ?? null,
                    'criteria' => $milestoneData['criteria'],
                    'craft_taxonomy_id' => $milestoneData['craft_taxonomy_id'] ?? null,
                    'order' => $index,
                    'status' => 'PENDING',
                ]);
            }
        }

        return $goal;
    }

    /**
     * Update goal progress from authoritative metric if metric_key is set
     */
    public function updateProgress(JourneyGoal $goal): JourneyGoal
    {
        if ($goal->metric_key) {
            $currentValue = $this->metricRepository->getLatest(
                $goal->specialist,
                $goal->specialistAssignment,
                $goal->metric_key
            );

            if ($currentValue !== null) {
                $goal->update(['current_value' => $currentValue]);
            }
        }

        return $goal;
    }

    public function activateGoal(JourneyGoal $goal): JourneyGoal
    {
        if ($goal->deadline && $goal->deadline->isPast()) {
            throw new \InvalidArgumentException('Cannot activate a goal with a past deadline');
        }

        $goal->update([
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        return $goal;
    }

    public function pauseGoal(JourneyGoal $goal): JourneyGoal
    {
        $goal->update(['status' => 'PAUSED']);
        return $goal;
    }

    public function completeGoal(JourneyGoal $goal): JourneyGoal
    {
        if ($goal->goal_type === 'NUMERIC' && $goal->current_value < $goal->target_value) {
            throw new \InvalidArgumentException('Goal target not reached');
        }

        if ($goal->goal_type === 'QUALITATIVE') {
            $pendingMilestones = $goal->milestones()->where('status', '!=', 'COMPLETED')->count();
            if ($pendingMilestones > 0) {
                throw new \InvalidArgumentException('Not all milestones completed');
            }
        }

        $goal->update([
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        return $goal;
    }
}
```

### JourneyMilestoneService (with idempotent award)
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyMilestoneDefinition;
use App\Models\JourneyMilestoneAchievement;
use Illuminate\Support\Facades\DB;

class JourneyMilestoneService
{
    /**
     * Check for milestones and award them
     * Uses database-level unique constraint for idempotency
     */
    public function checkForMilestones(Specialist $specialist, ?SpecialistAssignment $assignment): void
    {
        $definitions = JourneyMilestoneDefinition::active()->get();

        foreach ($definitions as $definition) {
            if ($this->evaluateCriteria($definition, $specialist, $assignment)) {
                $this->awardMilestone($definition, $specialist, $assignment);
            }
        }
    }

    private function evaluateCriteria(JourneyMilestoneDefinition $definition, Specialist $specialist, ?SpecialistAssignment $assignment): bool
    {
        $criteria = $definition->trigger_criteria;

        // Example criteria evaluation
        if ($criteria['event'] === 'booking_completed') {
            $bookingsCount = \App\Models\Booking::where('specialist_id', $specialist->id)
                ->when($assignment, fn($q) => $q->where('provider_id', $assignment->provider_id))
                ->where('status', 'completed')
                ->count();

            return $bookingsCount >= ($criteria['count'] ?? 1);
        }

        if ($criteria['event'] === 'review_received') {
            $reviewsCount = \App\Models\Review::where('specialist_id', $specialist->id)
                ->when($assignment, fn($q) => $q->where('provider_id', $assignment->provider_id))
                ->count();

            return $reviewsCount >= ($criteria['count'] ?? 1);
        }

        return false;
    }

    /**
     * Award milestone with database-level idempotency
     * Unique constraint prevents duplicates under concurrent requests
     */
    private function awardMilestone(JourneyMilestoneDefinition $definition, Specialist $specialist, ?SpecialistAssignment $assignment): void
    {
        try {
            DB::beginTransaction();

            JourneyMilestoneAchievement::create([
                'specialist_id' => $specialist->id,
                'milestone_definition_id' => $definition->id,
                'specialist_assignment_id' => $assignment?->id,
                'achieved_at' => now(),
                'verification_method' => 'AUTO',
            ]);

            DB::commit();
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint violation - already awarded, ignore
            if (strpos($e->getMessage(), 'unique_achievement') !== false) {
                DB::rollBack();
                return;
            }
            throw $e;
        }
    }
}
```

### Growth Opportunity Engine (Rule-based with future AI support)
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Illuminate\Support\Collection;

class GrowthOpportunityEngine
{
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new \App\Domain\Journey\Rules\RetentionRule(),
            new \App\Domain\Journey\Rules\ReputationRule(),
            new \App\Domain\Journey\Rules\AvailabilityRule(),
            new \App\Domain\Journey\Rules\CraftExpansionRule(),
            new \App\Domain\Journey\Rules\PricingRule(),
            new \App\Domain\Journey\Rules\ClientGrowthRule(),
        ];
    }

    /**
     * Generate opportunities from all active rules
     * Each rule returns opportunities with stable rule_key
     */
    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): Collection
    {
        $opportunities = collect();

        foreach ($this->rules as $rule) {
            if ($rule->isActive()) {
                $ruleOpportunities = $rule->evaluate($specialist, $assignment);
                $opportunities = $opportunities->merge($ruleOpportunities);
            }
        }

        return $opportunities;
    }

    /**
     * Future: Add AI generator without changing engine architecture
     */
    public function registerAIGenerator(GrowthOpportunityGenerator $aiGenerator): void
    {
        // AI generator will implement same interface as rules
    }
}
```

### GrowthOpportunityGenerator Interface
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;

interface GrowthOpportunityGenerator
{
    /**
     * Generate opportunities with stable identifiers
     * Each opportunity must have a unique rule_key
     */
    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array;
}
```

### Example Rule - RetentionRule
```php
<?php

namespace App\Domain\Journey\Rules;

use App\Domain\Journey\Services\GrowthOpportunityGenerator;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Review;
use App\Models\CustomerSpecialist;

class RetentionRule implements GrowthOpportunityGenerator
{
    private string $ruleKey = 'retention_improvement';

    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $opportunities = [];

        $repeatRate = $this->calculateRepeatRate($specialist);
        
        if ($repeatRate < 0.45) {
            $opportunities[] = [
                'rule_key' => $this->ruleKey,
                'title' => 'Improve client retention',
                'description' => 'Your repeat rate is below 45%. Focus on client follow-ups.',
                'opportunity_type' => 'RETENTION',
                'priority' => 'HIGH',
                'source' => 'RULE',
                'evidence' => [
                    'current_rate' => $repeatRate,
                    'benchmark' => 0.45,
                    'gap' => 0.45 - $repeatRate,
                ],
                'action_suggestion' => 'Send follow-up reminders 2-3 weeks after service.',
            ];
        }

        return $opportunities;
    }

    private function calculateRepeatRate(Specialist $specialist): float
    {
        $totalClients = CustomerSpecialist::where('specialist_id', $specialist->id)->count();
        if ($totalClients === 0) return 0;

        $repeatClients = CustomerSpecialist::where('specialist_id', $specialist->id)
            ->where('total_bookings', '>', 1)
            ->count();

        return $repeatClients / $totalClients;
    }

    public function isActive(): bool
    {
        return true;
    }
}
```

### JourneyGrowthOpportunityService (with rule_key support)
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyGrowthOpportunity;

class JourneyGrowthOpportunityService
{
    private GrowthOpportunityEngine $engine;

    public function __construct(GrowthOpportunityEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Generate and persist opportunities using rule_key for stability
     * Unique constraint prevents duplicate opportunities from same rule
     */
    public function generateOpportunities(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $generated = $this->engine->generate($specialist, $assignment);

        foreach ($generated as $opportunityData) {
            try {
                JourneyGrowthOpportunity::updateOrCreate(
                    [
                        'specialist_id' => $specialist->id,
                        'specialist_assignment_id' => $assignment?->id,
                        'rule_key' => $opportunityData['rule_key'],
                    ],
                    array_merge($opportunityData, [
                        'scope' => $assignment ? 'WORKPLACE' : 'SPECIALIST',
                        'expires_at' => now()->addDays(30),
                    ])
                );
            } catch (\Exception $e) {
                // Unique constraint violation - opportunity already exists
                continue;
            }
        }

        return JourneyGrowthOpportunity::where('specialist_id', $specialist->id)
            ->when($assignment, fn($q) => $q->where('specialist_assignment_id', $assignment->id))
            ->active()
            ->get()
            ->toArray();
    }

    public function dismissOpportunity(JourneyGrowthOpportunity $opportunity): JourneyGrowthOpportunity
    {
        $opportunity->update([
            'status' => 'DISMISSED',
            'dismissed_at' => now(),
        ]);

        return $opportunity;
    }

    public function acceptOpportunity(JourneyGrowthOpportunity $opportunity): JourneyGrowthOpportunity
    {
        $opportunity->update([
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        return $opportunity;
    }
}
```

### Layer 4: Facade - JourneyQueryService
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyGoal;
use App\Models\JourneyMilestoneAchievement;

class JourneyQueryService
{
    private JourneyMetricCalculator $calculator;
    private JourneyMetricRepository $repository;
    private JourneyScopeResolver $scopeResolver;
    private JourneyGoalService $goalService;
    private JourneyMilestoneService $milestoneService;
    private JourneyGrowthOpportunityService $opportunityService;

    public function __construct(
        JourneyMetricCalculator $calculator,
        JourneyMetricRepository $repository,
        JourneyScopeResolver $scopeResolver,
        JourneyGoalService $goalService,
        JourneyMilestoneService $milestoneService,
        JourneyGrowthOpportunityService $opportunityService
    ) {
        $this->calculator = $calculator;
        $this->repository = $repository;
        $this->scopeResolver = $scopeResolver;
        $this->goalService = $goalService;
        $this->milestoneService = $milestoneService;
        $this->opportunityService = $opportunityService;
    }

    /**
     * Assemble complete Journey response
     * Controller stays thin - this service orchestrates
     */
    public function getJourney(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        // Validate scope
        $scope = $this->scopeResolver->resolve($specialist, $assignment);

        // Calculate and store metrics (separate layers)
        $metrics = $this->calculator->calculate($specialist, $assignment);
        $this->repository->store($specialist, $assignment, $metrics);

        // Update goal progress from authoritative metrics
        $goals = JourneyGoal::where('specialist_id', $specialist->id)
            ->when($assignment, fn($q) => $q->where('specialist_assignment_id', $assignment->id))
            ->whereIn('status', ['ACTIVE', 'PAUSED'])
            ->get();

        foreach ($goals as $goal) {
            $this->goalService->updateProgress($goal);
        }

        // Check for new milestones
        $this->milestoneService->checkForMilestones($specialist, $assignment);

        // Generate growth opportunities
        $opportunities = $this->opportunityService->generateOpportunities($specialist, $assignment);

        // Assemble response
        return [
            'growth_snapshot' => $metrics,
            'goals' => $goals->map(fn($goal) => [
                'id' => $goal->id,
                'title' => $goal->title,
                'goal_type' => $goal->goal_type,
                'current_value' => $goal->current_value,
                'target_value' => $goal->target_value,
                'unit' => $goal->unit,
                'deadline' => $goal->deadline?->format('Y-m-d'),
                'status' => $goal->status,
                'scope' => $goal->scope,
                'progress_percent' => $goal->progress_percent,
            ])->toArray(),
            'milestones' => JourneyMilestoneAchievement::where('specialist_id', $specialist->id)
                ->when($assignment, fn($q) => $q->where('specialist_assignment_id', $assignment->id))
                ->with('milestoneDefinition')
                ->orderBy('achieved_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($achievement) {
                    return [
                        'id' => $achievement->id,
                        'definition_code' => $achievement->milestoneDefinition->code,
                        'title' => $achievement->milestoneDefinition->title,
                        'achieved_at' => $achievement->achieved_at->format('Y-m-d'),
                        'tier' => $achievement->milestoneDefinition->tier,
                    ];
                })
                ->toArray(),
            'growth_opportunities' => $opportunities,
        ];
    }
}
```

---

## Model Relationship Updates

### Specialist.php additions
```php
public function journeyMetrics()
{
    return $this->hasMany(\App\Models\JourneyMetric::class);
}

public function goals()
{
    return $this->hasMany(\App\Models\JourneyGoal::class);
}

public function milestoneAchievements()
{
    return $this->hasMany(\App\Models\JourneyMilestoneAchievement::class);
}

public function growthOpportunities()
{
    return $this->hasMany(\App\Models\JourneyGrowthOpportunity::class);
}
```

### SpecialistAssignment.php additions
```php
public function journeyMetrics()
{
    return $this->hasMany(\App\Models\JourneyMetric::class, 'specialist_assignment_id');
}

public function goals()
{
    return $this->hasMany(\App\Models\JourneyGoal::class, 'specialist_assignment_id');
}

public function growthOpportunities()
{
    return $this->hasMany(\App\Models\JourneyGrowthOpportunity::class, 'specialist_assignment_id');
}
```

---

## Controller Updates (Thin Controller)

### SpecialistPortalController.php - journey() method
```php
public function journey(Request $request): JsonResponse
{
    $account = auth('specialist')->user();
    $specialist = $account->specialist;

    if (!$specialist) {
        return response()->json(['message' => 'Specialist not found'], 404);
    }

    // Resolve scope with strict validation
    $scopeResolver = new \App\Domain\Journey\Services\JourneyScopeResolver();
    $assignment = null;
    $providerSlug = $request->query('provider_slug');

    if ($providerSlug) {
        $assignment = $scopeResolver->validateAccess($specialist, $providerSlug);

        if (!$assignment) {
            return response()->json(['message' => 'You do not have access to this workplace'], 403);
        }
    }

    // Use facade - controller stays thin
    $journeyService = new \App\Domain\Journey\Services\JourneyQueryService(
        new \App\Domain\Journey\Services\JourneyMetricCalculator(),
        new \App\Domain\Journey\Services\JourneyMetricRepository(),
        $scopeResolver,
        new \App\Domain\Journey\Services\JourneyGoalService(
            new \App\Domain\Journey\Services\JourneyMetricRepository()
        ),
        new \App\Domain\Journey\Services\JourneyMilestoneService(),
        new \App\Domain\Journey\Services\JourneyGrowthOpportunityService(
            new \App\Domain\Journey\Services\GrowthOpportunityEngine()
        )
    );

    $journeyData = $journeyService->getJourney($specialist, $assignment);

    return response()->json($journeyData);
}
```

---

## Seeder

### JourneyMilestoneDefinitionSeeder.php
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JourneyMilestoneDefinition;

class JourneyMilestoneDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $milestones = [
            [
                'code' => 'FIRST_CLIENT',
                'title' => 'First Client',
                'description' => 'Complete your first booking',
                'category' => 'CLIENT',
                'tier' => 'BRONZE',
                'trigger_criteria' => ['event' => 'booking_completed', 'count' => 1],
            ],
            [
                'code' => 'FIRST_REVIEW',
                'title' => 'First Review',
                'description' => 'Receive your first client review',
                'category' => 'REPUTATION',
                'tier' => 'BRONZE',
                'trigger_criteria' => ['event' => 'review_received', 'count' => 1],
            ],
            [
                'code' => 'FIRST_5_STAR',
                'title' => 'First 5-Star Review',
                'description' => 'Receive your first 5-star rating',
                'category' => 'REPUTATION',
                'tier' => 'SILVER',
                'trigger_criteria' => ['event' => 'review_5_star', 'count' => 1],
            ],
            [
                'code' => '100_CLIENTS',
                'title' => '100 Clients',
                'description' => 'Serve 100 unique clients',
                'category' => 'CLIENT',
                'tier' => 'GOLD',
                'trigger_criteria' => ['event' => 'clients_count', 'count' => 100],
            ],
            [
                'code' => '100_SERVICES',
                'title' => '100 Services',
                'description' => 'Complete 100 service bookings',
                'category' => 'SERVICE',
                'tier' => 'SILVER',
                'trigger_criteria' => ['event' => 'booking_completed', 'count' => 100],
            ],
            [
                'code' => 'NEW_EXPERTISE',
                'title' => 'New Expertise',
                'description' => 'Add a new expertise to your craft',
                'category' => 'CRAFT',
                'tier' => 'BRONZE',
                'trigger_criteria' => ['event' => 'expertise_added'],
            ],
        ];

        foreach ($milestones as $milestone) {
            JourneyMilestoneDefinition::updateOrCreate(
                ['code' => $milestone['code']],
                $milestone
            );
        }
    }
}
```

---

## Migration Strategy

### Phase 1: Create new tables
Run migrations for all 6 new tables in order:
1. journey_milestone_definitions
2. journey_milestone_achievements
3. journey_goal_milestones
4. journey_goals
5. journey_metrics
6. journey_growth_opportunities

### Phase 2: Seed milestone definitions
Run: `php artisan db:seed --class=JourneyMilestoneDefinitionSeeder`

### Phase 3: Migrate existing goals with metric_key mapping
Migration script copies data from specialist_goals to journey_goals with field mapping and metric_key assignment

### Phase 4: Migrate career events to milestones
Convert SpecialistCareerEvent with type='milestone' to JourneyMilestoneAchievement

### Phase 5: Update services
Refactor SpecialistJourneyService to use new domain services with separated layers

### Phase 6: Update controller
Add journey() endpoint to SpecialistPortalController using JourneyQueryService facade

### Phase 7: Drop old tables
After verification, drop specialist_goals table

---

## Implementation Order

1. Create migrations with V5 improvements (historical metrics, unique constraints, metric_key, rule_key)
2. Create models with metadata support
3. Update Specialist and SpecialistAssignment relationships
4. Create calculation layer (JourneyMetricCalculator)
5. Create persistence layer (JourneyMetricRepository)
6. Create scope resolver with strict validation
7. Create rule classes for Growth Opportunity Engine
8. Create Growth Opportunity Engine
9. Create other services (JourneyGoalService, JourneyMilestoneService, etc.)
10. Create JourneyQueryService facade
11. Create seeder
12. Run migrations and seeder
13. Update controller to use facade
14. Refactor SpecialistJourneyService
15. Test API endpoints
16. Migrate existing data
17. Drop old tables

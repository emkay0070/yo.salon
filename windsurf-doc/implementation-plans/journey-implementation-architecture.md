# Journey Implementation Architecture

This document shows the exact files, migrations, models, and services that will be built to implement the Journey domain architecture according to the specification.

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
backend/app/Domain/Journey/Services/JourneyMetricService.php
backend/app/Domain/Journey/Services/JourneyGoalService.php
backend/app/Domain/Journey/Services/JourneyMilestoneService.php
backend/app/Domain/Journey/Services/JourneyGrowthOpportunityService.php
backend/app/Domain/Journey/Services/JourneyScopeResolver.php
backend/app/Domain/Journey/Services/GoalProgressService.php
backend/app/Domain/Journey/Services/MilestoneEvaluator.php
backend/app/Domain/Journey/Services/GrowthOpportunityGenerator.php
backend/app/Domain/Journey/Services/RuleBasedGrowthOpportunityGenerator.php
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

### 1. journey_metrics
```php
Schema::create('journey_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('specialist_id');
    $table->uuid('specialist_assignment_id')->nullable();
    $table->enum('scope', ['SPECIALIST', 'WORKPLACE'])->default('SPECIALIST');
    $table->string('metric_type'); // clients_served, bookings_completed, etc.
    $table->decimal('value', 15, 2)->default(0);
    $table->timestamp('calculated_at');
    $table->timestamp('period_start')->nullable();
    $table->timestamp('period_end')->nullable();
    $table->timestamps();

    $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
    $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
    $table->index(['specialist_id', 'scope']);
    $table->index(['specialist_assignment_id']);
    $table->index(['metric_type']);
});
```

### 2. journey_goals
```php
Schema::create('journey_goals', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('specialist_id');
    $table->uuid('specialist_assignment_id')->nullable();
    $table->enum('scope', ['SPECIALIST', 'WORKPLACE'])->default('SPECIALIST');
    $table->string('title');
    $table->text('description')->nullable();
    $table->enum('goal_type', ['NUMERIC', 'QUALITATIVE']);
    $table->decimal('target_value', 15, 2)->nullable();
    $table->decimal('current_value', 15, 2)->default(0);
    $table->string('unit')->nullable();
    $table->timestamp('deadline')->nullable();
    $table->enum('status', ['DRAFT', 'ACTIVE', 'PAUSED', 'COMPLETED', 'FAILED', 'CANCELLED'])->default('DRAFT');
    $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH'])->default('MEDIUM');
    $table->enum('created_by', ['SPECIALIST', 'SYSTEM'])->default('SPECIALIST');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->json('data')->nullable();
    $table->timestamps();

    $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
    $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
    $table->index(['specialist_id', 'status']);
    $table->index(['specialist_assignment_id']);
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
    $table->json('data')->nullable();
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

### 5. journey_milestone_achievements
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
    $table->index(['specialist_id', 'milestone_definition_id']);
});
```

### 6. journey_growth_opportunities
```php
Schema::create('journey_growth_opportunities', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('specialist_id');
    $table->uuid('specialist_assignment_id')->nullable();
    $table->enum('scope', ['SPECIALIST', 'WORKPLACE'])->default('SPECIALIST');
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
        'metric_type',
        'value',
        'calculated_at',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'calculated_at' => 'datetime',
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
        'target_value',
        'current_value',
        'unit',
        'deadline',
        'status',
        'priority',
        'created_by',
        'started_at',
        'completed_at',
        'data',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'deadline' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'data' => 'array',
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
        'data',
    ];

    protected $casts = [
        'criteria' => 'array',
        'achieved_at' => 'datetime',
        'data' => 'array',
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
        return $-query->whereIn('status', ['SUGGESTED', 'ACCEPTED', 'IN_PROGRESS']);
    }

    public function scopeForSpecialist($query, string $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }
}
```

---

## Service Architecture

### JourneyMetricService
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyMetric;
use App\Models\Booking;
use App\Models\Review;
use App\Models\CustomerSpecialist;
use App\Models\JourneyGoal;
use App\Models\JourneyMilestoneAchievement;

class JourneyMetricService
{
    public function calculateMetrics(Specialist $specialist, ?SpecialistAssignment $assignment): array
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

        // Calculate metrics from authoritative sources
        $metrics = [
            'clients_served' => CustomerSpecialist::where('specialist_id', $specialist->id)->count(),
            'bookings_completed' => $bookingsQuery->where('status', 'completed')->count(),
            'rating' => $reviewsQuery->avg('rating') ?? 0,
            'reviews_count' => $reviewsQuery->count(),
            'repeat_client_rate' => $this->calculateRepeatRate($specialist, $assignment),
            'services_performed' => $bookingsQuery->where('status', 'completed')->count(),
            'goals_completed' => JourneyGoal::where('specialist_id', $specialist->id)
                ->when($assignmentId, fn($q) => $q->where('specialist_assignment_id', $assignmentId))
                ->where('status', 'COMPLETED')
                ->count(),
            'milestones_achieved' => JourneyMilestoneAchievement::where('specialist_id', $specialist->id)
                ->when($assignmentId, fn($q) => $q->where('specialist_assignment_id', $assignmentId))
                ->count(),
            'experience_years' => $specialist->years_experience ?? 0,
        ];

        // Store metrics for caching
        $this->storeMetrics($specialist, $assignment, $metrics);

        return $metrics;
    }

    private function calculateRepeatRate(Specialist $specialist, ?SpecialistAssignment $assignment): float
    {
        // Calculate repeat client rate from CustomerSpecialist
        $totalClients = CustomerSpecialist::where('specialist_id', $specialist->id)->count();
        if ($totalClients === 0) return 0;

        $repeatClients = CustomerSpecialist::where('specialist_id', $specialist->id)
            ->where('total_bookings', '>', 1)
            ->count();

        return $repeatClients / $totalClients;
    }

    private function storeMetrics(Specialist $specialist, ?SpecialistAssignment $assignment, array $metrics): void
    {
        $scope = $assignment ? 'WORKPLACE' : 'SPECIALIST';
        $assignmentId = $assignment?->id;

        foreach ($metrics as $metricType => $value) {
            JourneyMetric::updateOrCreate(
                [
                    'specialist_id' => $specialist->id,
                    'specialist_assignment_id' => $assignmentId,
                    'scope' => $scope,
                    'metric_type' => $metricType,
                ],
                [
                    'value' => $value,
                    'calculated_at' => now(),
                ]
            );
        }
    }
}
```

### JourneyScopeResolver
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;

class JourneyScopeResolver
{
    public function resolve(Specialist $specialist, ?SpecialistAssignment $assignment): string
    {
        if ($assignment && $assignment->status === 'active') {
            return 'WORKPLACE';
        }
        return 'SPECIALIST';
    }

    public function validateAccess(Specialist $specialist, string $providerSlug): ?SpecialistAssignment
    {
        return $specialist->assignments()
            ->where('status', 'active')
            ->whereHas('provider', fn($q) => $q->where('slug', $providerSlug))
            ->first();
    }
}
```

### JourneyGoalService
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyGoal;
use App\Models\JourneyGoalMilestone;

class JourneyGoalService
{
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

### JourneyMilestoneService
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyMilestoneDefinition;
use App\Models\JourneyMilestoneAchievement;

class JourneyMilestoneService
{
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

    private function awardMilestone(JourneyMilestoneDefinition $definition, Specialist $specialist, ?SpecialistAssignment $assignment): void
    {
        $existing = JourneyMilestoneAchievement::where('specialist_id', $specialist->id)
            ->where('milestone_definition_id', $definition->id)
            ->when($assignment, fn($q) => $q->where('specialist_assignment_id', $assignment->id))
            ->first();

        if ($existing) return; // Already awarded

        JourneyMilestoneAchievement::create([
            'specialist_id' => $specialist->id,
            'milestone_definition_id' => $definition->id,
            'specialist_assignment_id' => $assignment?->id,
            'achieved_at' => now(),
            'verification_method' => 'AUTO',
        ]);
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
    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array;
}
```

### RuleBasedGrowthOpportunityGenerator
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyGrowthOpportunity;

class RuleBasedGrowthOpportunityGenerator implements GrowthOpportunityGenerator
{
    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $opportunities = [];

        // Rule: Low rating
        $rating = \App\Models\Review::where('specialist_id', $specialist->id)->avg('rating') ?? 0;
        if ($rating < 4.8) {
            $opportunities[] = [
                'title' => 'Improve client satisfaction',
                'description' => 'Your rating is below 4.8. Focus on client follow-ups.',
                'opportunity_type' => 'RETENTION',
                'priority' => 'HIGH',
                'source' => 'RULE',
                'evidence' => ['current_rating' => $rating, 'benchmark' => 4.8],
                'action_suggestion' => 'Send follow-up messages after services.',
            ];
        }

        // Rule: Low client count
        $clientCount = \App\Models\CustomerSpecialist::where('specialist_id', $specialist->id)->count();
        if ($clientCount < 50) {
            $opportunities[] = [
                'title' => 'Expand your client base',
                'description' => 'You have fewer than 50 clients.',
                'opportunity_type' => 'MARKETING',
                'priority' => 'MEDIUM',
                'source' => 'RULE',
                'evidence' => ['current_clients' => $clientCount],
                'action_suggestion' => 'Increase weekend availability.',
            ];
        }

        return $opportunities;
    }
}
```

### JourneyGrowthOpportunityService
```php
<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\JourneyGrowthOpportunity;

class JourneyGrowthOpportunityService
{
    private GrowthOpportunityGenerator $generator;

    public function __construct(GrowthOpportunityGenerator $generator)
    {
        $this->generator = $generator;
    }

    public function generateOpportunities(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $generated = $this->generator->generate($specialist, $assignment);

        foreach ($generated as $opportunityData) {
            JourneyGrowthOpportunity::updateOrCreate(
                [
                    'specialist_id' => $specialist->id,
                    'specialist_assignment_id' => $assignment?->id,
                    'title' => $opportunityData['title'],
                    'status' => 'SUGGESTED',
                ],
                array_merge($opportunityData, [
                    'scope' => $assignment ? 'WORKPLACE' : 'SPECIALIST',
                    'expires_at' => now()->addDays(30),
                ])
            );
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

## Controller Updates

### SpecialistPortalController.php - journey() method
```php
public function journey(Request $request): JsonResponse
{
    $account = auth('specialist')->user();
    $specialist = $account->specialist;

    if (!$specialist) {
        return response()->json(['message' => 'Specialist not found'], 404);
    }

    // Resolve scope
    $assignment = null;
    $providerSlug = $request->query('provider_slug');

    if ($providerSlug) {
        $scopeResolver = new \App\Domain\Journey\Services\JourneyScopeResolver();
        $assignment = $scopeResolver->validateAccess($specialist, $providerSlug);

        if (!$assignment) {
            return response()->json(['message' => 'You do not have access to this workplace'], 403);
        }
    }

    // Get growth snapshot
    $metricService = new \App\Domain\Journey\Services\JourneyMetricService();
    $growthSnapshot = $metricService->calculateMetrics($specialist, $assignment);

    // Get goals
    $goals = \App\Models\JourneyGoal::where('specialist_id', $specialist->id)
        ->when($assignment, fn($q) => $q->where('specialist_assignment_id', $assignment->id))
        ->whereIn('status', ['ACTIVE', 'PAUSED'])
        ->get()
        ->map(fn($goal) => [
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
        ])
        ->toArray();

    // Get milestones
    $milestones = \App\Models\JourneyMilestoneAchievement::where('specialist_id', $specialist->id)
        ->when($assignment, fn($q) => $q->where('specialist_assignment_id', $assignment->id))
        ->with('milestoneDefinition')
        ->orderBy('achieved_at', 'desc')
        ->limit(20)
        ->get()
        ->map(fn($achievement) => [
            'id' => $achievement->id,
            'definition_code' => $achievement->milestoneDefinition->code,
            'title' => $achievement->milestoneDefinition->title,
            'achieved_at' => $achievement->achieved_at->format('Y-m-d'),
            'tier' => $achievement->milestoneDefinition->tier,
        ])
        ->toArray();

    // Get growth opportunities
    $opportunityService = new \App\Domain\Journey\Services\JourneyGrowthOpportunityService(
        new \App\Domain\Journey\Services\RuleBasedGrowthOpportunityGenerator()
    );
    $opportunities = $opportunityService->generateOpportunities($specialist, $assignment);

    return response()->json([
        'growth_snapshot' => $growthSnapshot,
        'goals' => $goals,
        'milestones' => $milestones,
        'growth_opportunities' => $opportunities,
    ]);
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

### Phase 3: Migrate existing goals
Migration script copies data from specialist_goals to journey_goals with field mapping

### Phase 4: Migrate career events
Convert SpecialistCareerEvent with type='milestone' to JourneyMilestoneAchievement

### Phase 5: Update services
Refactor SpecialistJourneyService to use new domain services

### Phase 6: Update controller
Add journey() endpoint to SpecialistPortalController

### Phase 7: Drop old tables
After verification, drop specialist_goals table

---

## Implementation Order

1. Create migrations
2. Create models
3. Update Specialist and SpecialistAssignment relationships
4. Create services (JourneyScopeResolver first, then others)
5. Create seeder
6. Run migrations and seeder
7. Update controller
8. Refactor SpecialistJourneyService
9. Test API endpoints
10. Migrate existing data
11. Drop old tables

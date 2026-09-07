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

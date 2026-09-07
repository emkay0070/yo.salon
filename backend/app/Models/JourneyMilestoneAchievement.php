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

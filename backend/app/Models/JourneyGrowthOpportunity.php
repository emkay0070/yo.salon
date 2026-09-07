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

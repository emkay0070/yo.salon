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

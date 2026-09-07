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

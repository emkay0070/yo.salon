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
            'metric_key' => $data['metric_key'] ?? null,
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

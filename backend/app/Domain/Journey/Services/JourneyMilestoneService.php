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

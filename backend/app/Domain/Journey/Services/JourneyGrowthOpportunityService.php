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

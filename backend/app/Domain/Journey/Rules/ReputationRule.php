<?php

namespace App\Domain\Journey\Rules;

use App\Domain\Journey\Services\GrowthOpportunityGenerator;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Review;

class ReputationRule implements GrowthOpportunityGenerator
{
    private string $ruleKey = 'reputation_improvement';

    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $opportunities = [];

        $rating = Review::where('specialist_id', $specialist->id)->avg('rating') ?? 0;
        
        if ($rating < 4.8) {
            $opportunities[] = [
                'rule_key' => $this->ruleKey,
                'title' => 'Improve client satisfaction',
                'description' => 'Your rating is below 4.8. Focus on client follow-ups.',
                'opportunity_type' => 'RETENTION',
                'priority' => 'HIGH',
                'source' => 'RULE',
                'evidence' => [
                    'current_rating' => $rating,
                    'benchmark' => 4.8,
                    'gap' => 4.8 - $rating,
                ],
                'action_suggestion' => 'Send follow-up messages after services.',
            ];
        }

        return $opportunities;
    }

    public function isActive(): bool
    {
        return true;
    }
}

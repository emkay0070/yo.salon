<?php

namespace App\Domain\Journey\Rules;

use App\Domain\Journey\Services\GrowthOpportunityGenerator;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;

class CraftExpansionRule implements GrowthOpportunityGenerator
{
    private string $ruleKey = 'craft_expansion';

    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $opportunities = [];

        // Placeholder for craft expansion logic
        // Can analyze booking requests vs current expertise
        
        return $opportunities;
    }

    public function isActive(): bool
    {
        return true;
    }
}

<?php

namespace App\Domain\Journey\Rules;

use App\Domain\Journey\Services\GrowthOpportunityGenerator;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;

class AvailabilityRule implements GrowthOpportunityGenerator
{
    private string $ruleKey = 'availability_optimization';

    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $opportunities = [];

        // Placeholder for availability logic
        // Can be expanded based on actual availability data
        
        return $opportunities;
    }

    public function isActive(): bool
    {
        return true;
    }
}

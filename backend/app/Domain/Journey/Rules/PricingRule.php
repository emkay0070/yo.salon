<?php

namespace App\Domain\Journey\Rules;

use App\Domain\Journey\Services\GrowthOpportunityGenerator;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;

class PricingRule implements GrowthOpportunityGenerator
{
    private string $ruleKey = 'pricing_optimization';

    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $opportunities = [];

        // Placeholder for pricing optimization logic
        // Can analyze pricing vs market rates
        
        return $opportunities;
    }

    public function isActive(): bool
    {
        return true;
    }
}

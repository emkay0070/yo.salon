<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;

interface GrowthOpportunityGenerator
{
    /**
     * Generate opportunities with stable identifiers
     * Each opportunity must have a unique rule_key
     */
    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array;
}

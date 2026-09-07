<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Illuminate\Support\Collection;

class GrowthOpportunityEngine
{
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new \App\Domain\Journey\Rules\RetentionRule(),
            new \App\Domain\Journey\Rules\ReputationRule(),
            new \App\Domain\Journey\Rules\AvailabilityRule(),
            new \App\Domain\Journey\Rules\CraftExpansionRule(),
            new \App\Domain\Journey\Rules\PricingRule(),
            new \App\Domain\Journey\Rules\ClientGrowthRule(),
        ];
    }

    /**
     * Generate opportunities from all active rules
     * Each rule returns opportunities with stable rule_key
     */
    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): Collection
    {
        $opportunities = collect();

        foreach ($this->rules as $rule) {
            if ($rule->isActive()) {
                $ruleOpportunities = $rule->generate($specialist, $assignment);
                $opportunities = $opportunities->merge($ruleOpportunities);
            }
        }

        return $opportunities;
    }

    /**
     * Future: Add AI generator without changing engine architecture
     */
    public function registerAIGenerator(GrowthOpportunityGenerator $aiGenerator): void
    {
        // AI generator will implement same interface as rules
    }
}

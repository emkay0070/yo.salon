<?php

namespace App\Domain\Journey\Rules;

use App\Domain\Journey\Services\GrowthOpportunityGenerator;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\CustomerSpecialist;

class ClientGrowthRule implements GrowthOpportunityGenerator
{
    private string $ruleKey = 'client_growth';

    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $opportunities = [];

        $query = CustomerSpecialist::where('specialist_id', $specialist->id);

        if ($assignment) {
            $query->where('provider_id', $assignment->provider_id);
        }

        $clientCount = $query->count();
        
        if ($clientCount < 50) {
            $opportunities[] = [
                'rule_key' => $this->ruleKey,
                'title' => 'Expand your client base',
                'description' => 'You have fewer than 50 clients.',
                'opportunity_type' => 'MARKETING',
                'priority' => 'MEDIUM',
                'source' => 'RULE',
                'evidence' => [
                    'current_clients' => $clientCount,
                    'benchmark' => 50,
                    'gap' => 50 - $clientCount,
                ],
                'action_suggestion' => 'Increase weekend availability.',
            ];
        }

        return $opportunities;
    }

    public function isActive(): bool
    {
        return true;
    }
}

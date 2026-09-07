<?php

namespace App\Domain\Journey\Rules;

use App\Domain\Journey\Services\GrowthOpportunityGenerator;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\CustomerSpecialist;

class RetentionRule implements GrowthOpportunityGenerator
{
    private string $ruleKey = 'retention_improvement';

    public function generate(Specialist $specialist, ?SpecialistAssignment $assignment): array
    {
        $opportunities = [];

        $repeatRate = $this->calculateRepeatRate($specialist, $assignment);
        
        if ($repeatRate < 0.45) {
            $opportunities[] = [
                'rule_key' => $this->ruleKey,
                'title' => 'Improve client retention',
                'description' => 'Your repeat rate is below 45%. Focus on client follow-ups.',
                'opportunity_type' => 'RETENTION',
                'priority' => 'HIGH',
                'source' => 'RULE',
                'evidence' => [
                    'current_rate' => $repeatRate,
                    'benchmark' => 0.45,
                    'gap' => 0.45 - $repeatRate,
                ],
                'action_suggestion' => 'Send follow-up reminders 2-3 weeks after service.',
            ];
        }

        return $opportunities;
    }

    private function calculateRepeatRate(Specialist $specialist, ?SpecialistAssignment $assignment): float
    {
        $query = CustomerSpecialist::where('specialist_id', $specialist->id);

        if ($assignment) {
            $query->where('provider_id', $assignment->provider_id);
        }

        $totalClients = $query->count();
        if ($totalClients === 0) return 0;

        $repeatClients = $query->where('total_bookings', '>', 1)->count();

        return $repeatClients / $totalClients;
    }

    public function isActive(): bool
    {
        return true;
    }
}

<?php

namespace App\Services\Intelligence;

class RuleEngine
{
    /**
     * Evaluates intelligence data against rules to generate prioritized signals.
     */
    public function generateSignals(array $data): array
    {
        $signals = [];

        // 1. Churn Risk Rule
        if (!empty($data['analytics']['customers']['churn_risks'])) {
            $churnRisks = $data['analytics']['customers']['churn_risks'];
            $criticalCount = collect($churnRisks)->where('risk_label', 'critical')->count();
            
            if ($criticalCount > 0) {
                $signals[] = [
                    'id' => 'churn_risk',
                    'priority' => 'critical',
                    'title' => "{$criticalCount} VIP customers at high churn risk",
                    'summary' => "These customers haven't visited in over 60 days.",
                    'impact' => "Estimated LTV at risk: UGX " . number_format($data['forecast']['churn_risk_revenue_at_stake']),
                    'recommended_action' => 'View Churn Radar',
                    'deep_link' => '/analytics/intelligence#churn',
                    'is_predictive' => true,
                    'dismissible' => false,
                ];
            }
        }

        // 2. Slow Day Rule
        $demandByDay = $data['analytics']['demand']['by_day_of_week'] ?? [];
        if (!empty($demandByDay)) {
            $sortedByIntensity = collect($demandByDay)->sortBy('intensity')->values();
            $slowestDay = $sortedByIntensity->first();
            
            if ($slowestDay && $slowestDay['intensity'] < 0.4) {
                $signals[] = [
                    'id' => 'low_demand_' . strtolower($slowestDay['day']),
                    'priority' => 'high',
                    'title' => "{$slowestDay['day']} occupancy is low",
                    'summary' => "Only a fraction of available slots are booked on {$slowestDay['day']}s.",
                    'impact' => 'Underutilized staff capacity',
                    'recommended_action' => "Launch {$slowestDay['day']} Flash Deal",
                    'deep_link' => '/analytics/intelligence#demand',
                    'is_predictive' => false,
                    'dismissible' => true,
                ];
            }
        }

        // 3. Upcoming Demand Spike
        $spikeDays = $data['forecast']['demand_spike_days'] ?? [];
        if (!empty($spikeDays)) {
            $signals[] = [
                'id' => 'demand_spike',
                'priority' => 'medium',
                'title' => 'Upcoming end-of-month demand spike',
                'summary' => 'Payday rush is approaching in the coming days.',
                'impact' => 'Potential lost revenue if understaffed',
                'recommended_action' => 'Review Staff Roster',
                'deep_link' => '/analytics/intelligence#demand',
                'is_predictive' => true,
                'dismissible' => true,
            ];
        }

        // 4. Gateway Fees Rule
        $gatewayPct = $data['analytics']['fees']['gateway_pct'] ?? 0;
        if ($gatewayPct > 5) {
             $signals[] = [
                'id' => 'high_gateway_fees',
                'priority' => 'low',
                'title' => 'High gateway fees detected',
                'summary' => "Gateway fees are currently consuming {$gatewayPct}% of gross revenue.",
                'impact' => 'Margin compression',
                'recommended_action' => 'Review Payment Mix',
                'deep_link' => '/analytics/intelligence#fees',
                'is_predictive' => false,
                'dismissible' => true,
            ];
        }
        
        // Sort signals by priority
        $priorityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        
        usort($signals, function($a, $b) use ($priorityOrder) {
            return $priorityOrder[$b['priority']] <=> $priorityOrder[$a['priority']];
        });

        return $signals;
    }
}

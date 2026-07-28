<?php

namespace App\Services\Intelligence;

use Carbon\Carbon;
use App\Models\Salon;

class BriefingBuilder
{
    /**
     * Builds the Executive Briefing narrative based on the analyzed data.
     */
    public function build(array $data, Salon $salon): array
    {
        // Find owner or manager from pivot table
        $ownerUser = \DB::table('salon_users')
            ->join('users', 'users.id', '=', 'salon_users.user_id')
            ->where('salon_users.salon_id', $salon->id)
            ->whereIn('salon_users.role', ['owner', 'manager'])
            ->select('users.name')
            ->first();

        $ownerName = $ownerUser ? $ownerUser->name : 'Owner';
        $firstName = explode(' ', $ownerName)[0];
        
        $greeting = $this->getGreeting($firstName);
        $narrative = $this->generateNarrative($data);
        
        return [
            'greeting' => $greeting,
            'narrative' => $narrative
        ];
    }
    
    private function getGreeting(string $name): string
    {
        $hour = Carbon::now()->hour;
        if ($hour < 12) {
            $timeOfDay = 'Good morning';
        } elseif ($hour < 17) {
            $timeOfDay = 'Good afternoon';
        } else {
            $timeOfDay = 'Good evening';
        }
        
        return "{$timeOfDay}, {$name}.";
    }
    
    private function generateNarrative(array $data): string
    {
        $parts = [];
        
        // 1. Revenue
        $net = $data['analytics']['revenue']['net'] ?? 0;
        $formattedNet = number_format($net / 1000000, 1) . 'M';
        $parts[] = "Your salon has generated **UGX {$formattedNet}** in net revenue recently.";
        
        // 2. Staff performance
        $staff = $data['analytics']['staff'] ?? [];
        if (!empty($staff)) {
            $topStaff = $staff[0];
            $parts[] = "**{$topStaff['name']}** is leading the team with an Intelligence Score of **{$topStaff['intelligence_score']}**.";
        }
        
        // 3. Demand / Slow days
        $demandByDay = $data['analytics']['demand']['by_day_of_week'] ?? [];
        if (!empty($demandByDay)) {
            $sortedByIntensity = collect($demandByDay)->sortBy('intensity')->values();
            $slowestDay = $sortedByIntensity->first();
            // Only show if there are actual bookings (not all zeros)
            if ($slowestDay && ($slowestDay['bookings'] ?? 0) > 0) {
                $parts[] = "**{$slowestDay['day']}** remains your weakest-performing day.";
            }
        }
        
        // 4. Churn
        $churnRisks = $data['analytics']['customers']['churn_risks'] ?? [];
        if (!empty($churnRisks)) {
            $criticalCount = collect($churnRisks)->where('risk_label', 'critical')->count();
            if ($criticalCount > 0) {
                $atStake = number_format(($data['forecast']['churn_risk_revenue_at_stake'] ?? 0) / 1000000, 1) . 'M';
                $parts[] = "**{$criticalCount} VIP customers** are at risk of churn, representing **UGX {$atStake}** in lifetime value.";
            }
        }
        
        // 5. Settlement
        $pending = $data['analytics']['revenue']['settlement_pending'] ?? 0;
        if ($pending > 0) {
            $formattedPending = number_format($pending / 1000000, 1) . 'M';
            $parts[] = "You have **UGX {$formattedPending}** awaiting settlement.";
        }
        
        return implode(' ', $parts);
    }
}

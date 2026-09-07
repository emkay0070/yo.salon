<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Collection;

class StaffAnalyzer implements AnalyzerInterface
{
    public function analyze(array $financialFacts, array $operationalFacts, Collection $bookings): array
    {
        $staffMembers = [];
        // Use specialist_id instead of staff_id for the new architecture
        $staffBookings = $bookings->whereNotNull('specialist_id')->groupBy('specialist_id');

        // Fallback to staff_id if no specialist_id exists (for backward compatibility)
        if ($staffBookings->isEmpty()) {
            $staffBookings = $bookings->whereNotNull('staff_id')->groupBy('staff_id');
        }

        // Get revenue by specialist from financial facts
        $bySpecialist = $financialFacts['by_specialist'];

        foreach ($staffBookings as $staffId => $bks) {
            // Try to get specialist first, then fallback to staff
            $staff = $bks->first()->specialist ?? $bks->first()->staff;
            if (!$staff) continue;

            $bookingCount = $bks->count();
            
            // Use canonical financial facts for revenue (from Ledger)
            $revenueGenerated = $bySpecialist[$staffId]['revenue'] ?? 0;

            // Calculate Repeat Clients (placeholder logic: unique customers > 1 booking with this staff)
            $customerBookings = $bks->groupBy('customer_id');
            $repeatClientsCount = $customerBookings->filter(fn($cbs) => $cbs->count() > 1)->count();
            $totalUniqueClients = $customerBookings->count();
            $repeatRate = $totalUniqueClients > 0 ? ($repeatClientsCount / $totalUniqueClients) * 100 : 0;

            // Upsells and Rating are placeholders for now
            $upsellScore = 50; 
            $ratingScore = 80;

            // Intelligence Score: revenue 40%, repeats 25%, upsells 20%, rating 15%
            // Normalize revenue score (assuming 1M is excellent for this demo, adjust dynamically later)
            $revScore = min(100, ($revenueGenerated / max(1, 1000000)) * 100); 

            $intelligenceScore = round(
                ($revScore * 0.40) +
                ($repeatRate * 0.25) +
                ($upsellScore * 0.20) +
                ($ratingScore * 0.15)
            );

            $staffMembers[] = [
                'id' => $staff->id,
                'name' => $staff->name,
                'intelligence_score' => $intelligenceScore,
                'score_breakdown' => [
                    'revenue' => round($revScore),
                    'repeat_clients' => round($repeatRate),
                    'upsells' => $upsellScore,
                    'rating' => $ratingScore
                ],
                'bookings' => $bookingCount,
                'revenue_generated' => $revenueGenerated
            ];
        }

        // Sort by intelligence score descending
        usort($staffMembers, fn($a, $b) => $b['intelligence_score'] <=> $a['intelligence_score']);

        // Assign ranks
        foreach ($staffMembers as $index => &$sm) {
            $sm['rank'] = $index + 1;
        }

        // Build bookings-by-day for the dashboard bar chart
        $bookingsByDay = [];
        foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day) {
            $bookingsByDay[$day] = $bookings->filter(
                fn($b) => \Carbon\Carbon::parse($b->date ?? 'now')->format('l') === $day
            )->count();
        }

        return [
            'analytics' => [
                'staff' => $staffMembers,
                'bookings_by_day' => $bookingsByDay,
            ]
        ];
    }
}

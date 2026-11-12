<?php

namespace App\Services\Intelligence;

class StaffAnalyzer implements AnalyzerInterface
{
    public function analyze(array $financialFacts, array $operationalFacts): array
    {
        $staffMembers = [];

        // Get staff bookings from operational facts
        $bySpecialistOps = $operationalFacts['by_specialist'] ?? [];

        // Get revenue by specialist from financial facts
        $bySpecialistFinance = $financialFacts['by_specialist'] ?? [];

        // Merge operational and financial data by specialist_id
        foreach ($bySpecialistOps as $specialistData) {
            $specialistId = $specialistData['specialist_id'];
            $financeData = $bySpecialistFinance[$specialistId] ?? null;

            $bookingCount = $specialistData['booking_count'] ?? 0;
            $revenueGenerated = $financeData['revenue'] ?? 0;

            // Calculate Repeat Clients from operational facts
            $repeatClientsCount = 0;
            $totalUniqueClients = $bookingCount; // Simplified: assume 1 client per booking for now
            $repeatRate = 0;

            // Get customer data from operational facts to calculate repeat rate
            $byCustomer = $operationalFacts['by_customer'] ?? [];
            foreach ($byCustomer as $customer) {
                if ($customer['is_repeat_customer']) {
                    $repeatClientsCount++;
                }
            }
            $totalUniqueClients = count($byCustomer);
            $repeatRate = $totalUniqueClients > 0 ? ($repeatClientsCount / $totalUniqueClients) * 100 : 0;

            // Upsells and Rating - insufficient data for now
            $upsellScore = null;
            $ratingScore = null;

            // Intelligence Score: only calculate if we have sufficient data
            $intelligenceScore = null;
            $scoreBreakdown = null;

            if ($bookingCount >= 5) { // Minimum threshold for meaningful score
                // Normalize revenue score (assuming 1M is excellent for this demo, adjust dynamically later)
                $revScore = min(100, ($revenueGenerated / max(1, 1000000)) * 100);

                // Use actual values if available, otherwise null
                $upsellScore = 50; // Placeholder until tracking is implemented
                $ratingScore = 80; // Placeholder until rating system is implemented

                $intelligenceScore = round(
                    ($revScore * 0.40) +
                    ($repeatRate * 0.25) +
                    ($upsellScore * 0.20) +
                    ($ratingScore * 0.15)
                );

                $scoreBreakdown = [
                    'revenue' => round($revScore),
                    'repeat_clients' => round($repeatRate),
                    'upsells' => $upsellScore,
                    'rating' => $ratingScore
                ];
            }

            $staffMembers[] = [
                'id' => $specialistId,
                'name' => $specialistData['specialist_name'] ?? 'Unknown',
                'intelligence_score' => $intelligenceScore,
                'score_breakdown' => $scoreBreakdown,
                'confidence' => $bookingCount >= 5 ? 'medium' : 'insufficient_data',
                'sample_size' => $bookingCount,
                'bookings' => $bookingCount,
                'revenue_generated' => $revenueGenerated
            ];
        }

        // Sort by intelligence score descending (nulls last)
        usort($staffMembers, function($a, $b) {
            if ($a['intelligence_score'] === null && $b['intelligence_score'] === null) {
                return 0;
            }
            if ($a['intelligence_score'] === null) return 1;
            if ($b['intelligence_score'] === null) return -1;
            return $b['intelligence_score'] <=> $a['intelligence_score'];
        });

        // Assign ranks
        foreach ($staffMembers as $index => &$sm) {
            $sm['rank'] = $index + 1;
        }

        // Build bookings-by-day from operational facts
        $temporal = $operationalFacts['temporal'] ?? [];
        $weeklyBookings = $temporal['weekly_bookings'] ?? [];

        return [
            'analytics' => [
                'staff' => $staffMembers,
                'bookings_by_day' => $weeklyBookings,
            ]
        ];
    }
}

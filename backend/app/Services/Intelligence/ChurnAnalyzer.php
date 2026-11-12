<?php

namespace App\Services\Intelligence;

class ChurnAnalyzer implements AnalyzerInterface
{
    public function analyze(array $financialFacts, array $operationalFacts): array
    {
        $churnRisks = [];
        $now = now();

        // Get customer data from operational facts
        $byCustomer = $operationalFacts['by_customer'] ?? [];
        $totalCustomers = count($byCustomer);

        // Get revenue by customer from financial facts
        $byCustomerFinance = $financialFacts['by_customer'] ?? [];

        // Get temporal data for unique this month calculation
        $temporal = $operationalFacts['temporal'] ?? [];
        $dailyTrend = $temporal['daily_trend'] ?? [];
        $uniqueThisMonth = 0;

        // Calculate unique customers this month from daily trend
        foreach ($dailyTrend as $date => $count) {
            if (\Carbon\Carbon::parse($date)->isCurrentMonth()) {
                $uniqueThisMonth += $count;
            }
        }

        $retainedCustomers = 0;

        foreach ($byCustomer as $customerData) {
            $customerId = $customerData['customer_id'];
            if (!$customerId) continue;

            $bookingCount = $customerData['booking_count'] ?? 0;
            $isRepeat = $customerData['is_repeat_customer'] ?? false;

            if ($bookingCount > 1 || $isRepeat) {
                $retainedCustomers++;

                // Calculate days since last booking from operational data
                // For now, we'll use a simplified approach since we don't have last booking date in facts
                // TODO: Add last_booking_date to operational facts
                $daysSinceLastBooking = 30; // Placeholder

                // If they haven't booked in 45-90 days, flag as risk
                if ($daysSinceLastBooking >= 45 && $daysSinceLastBooking <= 90) {
                    // Use canonical financial facts for LTV (from Ledger)
                    $financeData = $byCustomerFinance[$customerId] ?? null;
                    $ltv = $financeData['net_revenue'] ?? 0;

                    // Risk Score 0-100 (90 days = 100%, 45 days = 50%)
                    $riskScore = min(100, round(($daysSinceLastBooking / 90) * 100));

                    $riskLabel = $riskScore > 80 ? 'critical' : ($riskScore > 60 ? 'high' : 'medium');

                    $churnRisks[] = [
                        'customer_id' => $customerId,
                        'name' => $customerData['customer_name'] ?? 'Unknown',
                        'risk_score' => $riskScore,
                        'risk_label' => $riskLabel,
                        'days_since_visit' => $daysSinceLastBooking,
                        'lifetime_value' => $ltv,
                        'recommended_action' => 'Offer Executive Package',
                        'confidence' => 'low' // Placeholder until we have actual last booking date
                    ];
                }
            }
        }

        $retentionRate = $totalCustomers > 0 ? round(($retainedCustomers / $totalCustomers), 2) : 0;

        // Sort churn risks by LTV (most valuable first)
        usort($churnRisks, fn($a, $b) => $b['lifetime_value'] <=> $a['lifetime_value']);

        return [
            'analytics' => [
                'customers' => [
                    'total' => $totalCustomers,
                    'unique_this_month' => $uniqueThisMonth,
                    'retention_rate' => $retentionRate,
                    'churn_risks' => $churnRisks
                ]
            ],
            'forecast' => [
                'churn_risk_revenue_at_stake' => array_sum(array_column($churnRisks, 'lifetime_value'))
            ]
        ];
    }
}

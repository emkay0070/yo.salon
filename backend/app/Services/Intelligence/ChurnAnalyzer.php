<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Collection;

class ChurnAnalyzer implements AnalyzerInterface
{
    public function analyze(Collection $transactions, Collection $bookings): array
    {
        $customerGroups = $bookings->groupBy('customer_id');
        $churnRisks = [];
        $now = now();
        $totalCustomers = $customerGroups->count();
        $uniqueThisMonth = $bookings->filter(fn($b) => \Carbon\Carbon::parse($b->date)->isCurrentMonth())->pluck('customer_id')->unique()->count();
        $retainedCustomers = 0;

        foreach ($customerGroups as $customerId => $customerBookings) {
            if (!$customerId) continue;

            $customer = $customerBookings->first()->customer;
            if (!$customer) continue;

            $bookingCount = $customerBookings->count();
            if ($bookingCount > 1) {
                $retainedCustomers++;
                
                $lastBookingDate = collect($customerBookings)->max('date');
                $daysSinceLastBooking = $now->diffInDays(\Carbon\Carbon::parse($lastBookingDate));
                
                // If they haven't booked in 45-90 days, flag as risk
                if ($daysSinceLastBooking >= 45 && $daysSinceLastBooking <= 90) {
                    
                    // Calculate LTV (Lifetime Value)
                    $bookingIds = $customerBookings->pluck('id')->toArray();
                    $ltv = $transactions->whereIn('booking_id', $bookingIds)->sum('net_amount');
                    
                    // Risk Score 0-100 (90 days = 100%, 45 days = 50%)
                    $riskScore = min(100, round(($daysSinceLastBooking / 90) * 100));
                    
                    $riskLabel = $riskScore > 80 ? 'critical' : ($riskScore > 60 ? 'high' : 'medium');
                    
                    $churnRisks[] = [
                        'customer_id' => $customer->id,
                        'name' => $customer->name,
                        'risk_score' => $riskScore,
                        'risk_label' => $riskLabel,
                        'days_since_visit' => $daysSinceLastBooking,
                        'lifetime_value' => $ltv,
                        'recommended_action' => 'Offer Executive Package' // Static for now, could be dynamic
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

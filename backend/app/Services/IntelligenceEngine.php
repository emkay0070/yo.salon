<?php

namespace App\Services;

use Illuminate\Support\Collection;

class IntelligenceEngine
{
    /**
     * Generate business insights based on ledger and booking data.
     *
     * @param Collection $transactions
     * @param Collection $bookings
     * @return array
     */
    public function generateInsights(Collection $transactions, Collection $bookings): array
    {
        $insights = [];
        $uniqueCustomers = $bookings->pluck('customer_id')->unique()->count();
        $totalBookings = $bookings->count();

        // Base metrics
        $totalGross = $transactions->sum('gross_amount');
        $totalNet = $transactions->sum('net_amount');
        $totalFees = $transactions->sum(function ($tx) {
            return $tx->gateway_fee + $tx->platform_fee + $tx->tax_amount;
        });

        // 1. Profit Margin Rule
        $marginAlert = null;
        if ($totalGross > 0) {
            $feePercentage = ($totalFees / $totalGross) * 100;
            if ($feePercentage > 5) {
                $marginAlert = [
                    'title' => 'High Payment Fees Detected',
                    'description' => sprintf(
                        'You are losing %s%% of revenue to gateway and platform fees. Consider promoting cash payments or reviewing your digital pricing.',
                        round($feePercentage, 1)
                    ),
                    'type' => 'warning',
                    'action_text' => 'Review Payment Methods',
                ];
            } else {
                $marginAlert = [
                    'title' => 'Healthy Profit Margins',
                    'description' => sprintf(
                        'Your fee ratio is well optimized at %s%%. You are keeping most of your gross revenue.',
                        round($feePercentage, 1)
                    ),
                    'type' => 'success',
                    'action_text' => 'View Ledger',
                ];
            }
        }
        if ($marginAlert) $insights[] = $marginAlert;

        // 2. Slow Days Rule
        // Group bookings by Day of Week
        $bookingsByDay = $bookings->groupBy(function ($booking) {
            return date('l', strtotime($booking->date)); // 'Monday', 'Tuesday'
        });

        $slowestDay = null;
        $lowestCount = PHP_INT_MAX;
        $totalDaysWithBookings = $bookingsByDay->count();
        $averageDaily = $totalDaysWithBookings > 0 ? $bookings->count() / $totalDaysWithBookings : 0;

        foreach ($bookingsByDay as $day => $dayBookings) {
            $count = $dayBookings->count();
            if ($count < $lowestCount) {
                $lowestCount = $count;
                $slowestDay = $day;
            }
        }

        if ($slowestDay && $lowestCount < ($averageDaily * 0.5) && $totalDaysWithBookings >= 3) {
            $insights[] = [
                'title' => 'Slow Day Detected: ' . $slowestDay,
                'description' => sprintf(
                    '%ss are consistently underutilized, averaging significantly below your daily volume. Consider introducing a %s Executive Package.',
                    $slowestDay,
                    $slowestDay
                ),
                'type' => 'opportunity',
                'action_text' => 'Create Promotion',
            ];
        }

        // 3. Forecasting Rule
        // Basic linear projection based on 30-day run rate
        $daysInDataset = 30; // Assuming the query pulls 30 days
        $projectedMonthlyNet = 0;
        if ($totalNet > 0) {
            $projectedMonthlyNet = ($totalNet / $daysInDataset) * 30; // standardise to 30 days
            
            $insights[] = [
                'title' => 'Revenue Forecast',
                'description' => sprintf(
                    'Based on your current run rate, you are projected to net %s this month.',
                    'UGX ' . number_format($projectedMonthlyNet)
                ),
                'type' => 'info',
                'action_text' => 'View Full Forecast',
            ];
        }

        // 4. Staff Utilization Rule
        $staffStats = $bookings->whereNotNull('staff_id')->groupBy('staff_id');
        if ($staffStats->count() >= 2) {
            $topStaff = null;
            $topCount = 0;
            foreach ($staffStats as $staffId => $group) {
                if ($group->count() > $topCount) {
                    $topCount = $group->count();
                    $topStaff = $group->first()->staff->name ?? 'A staff member';
                }
            }
            if ($topStaff && $topCount > 0) {
                $insights[] = [
                    'title' => 'Top Performing Staff',
                    'description' => sprintf(
                        '%s is leading with %d bookings. Consider a reward or having them mentor others.',
                        $topStaff,
                        $topCount
                    ),
                    'type' => 'success',
                    'action_text' => 'View Staff Analytics',
                ];
            }
        }

        // 5. Customer Retention Rule
        if ($totalBookings > 10) {
            $retentionRate = ($uniqueCustomers / $totalBookings) * 100;
            if ($retentionRate > 80) { // High unique to total ratio means low repeat
                $insights[] = [
                    'title' => 'Low Repeat Customer Rate',
                    'description' => 'Most of your bookings are from new customers. Consider launching a loyalty program to increase retention.',
                    'type' => 'opportunity',
                    'action_text' => 'Create Loyalty Program',
                ];
            } else {
                $insights[] = [
                    'title' => 'Strong Customer Loyalty',
                    'description' => 'You have a healthy base of repeat customers driving consistent revenue.',
                    'type' => 'success',
                    'action_text' => 'View Customer Insights',
                ];
            }
        }

        // 6. Churn Predictor (Predictive)
        // Find customers who have > 1 booking, but their last booking was > 45 days ago
        $customerGroups = $bookings->groupBy('customer_id');
        $atRiskCustomers = 0;
        $now = now();
        
        foreach ($customerGroups as $customerId => $customerBookings) {
            if ($customerId && $customerBookings->count() > 1) {
                // Get the most recent booking date
                $lastBookingDate = collect($customerBookings)->max('date');
                $daysSinceLastBooking = $now->diffInDays(\Carbon\Carbon::parse($lastBookingDate));
                
                if ($daysSinceLastBooking >= 45 && $daysSinceLastBooking <= 90) {
                    $atRiskCustomers++;
                }
            }
        }

        if ($atRiskCustomers > 0) {
            $insights[] = [
                'title' => 'Churn Risk Detected',
                'description' => sprintf(
                    '%d of your regular customers haven\'t booked in over 45 days. They are at risk of churning.',
                    $atRiskCustomers
                ),
                'type' => 'warning',
                'action_text' => 'Send "We Miss You" Campaign',
                'is_predictive' => true,
            ];
        }

        // 7. Demand Spike Forecaster (Predictive)
        // Check if we are approaching end of month (payday rush) e.g., dates 25-28
        $currentDayOfMonth = (int)$now->format('d');
        if ($currentDayOfMonth >= 25 && $currentDayOfMonth <= 28) {
            $insights[] = [
                'title' => 'Upcoming Demand Spike',
                'description' => 'End-of-month (payday) rush is approaching. Ensure you have maximum staff availability for the upcoming weekend.',
                'type' => 'info',
                'action_text' => 'Review Staff Roster',
                'is_predictive' => true,
            ];
        }

        return collect($insights)->sortByDesc(function ($insight) {
            // Sort to bring warnings/opportunities to the top, and prioritize predictive
            $score = 0;
            $weights = ['warning' => 4, 'opportunity' => 3, 'info' => 2, 'success' => 1];
            $score += $weights[$insight['type']] ?? 0;
            if ($insight['is_predictive'] ?? false) {
                $score += 5; // highly surface predictive insights
            }
            return $score;
        })->values()->toArray();
    }

    /**
     * Generate a top-level executive summary narrative.
     */
    public function generateExecutiveSummary(Collection $transactions, Collection $bookings): string
    {
        $totalNet = $transactions->sum('net_amount');
        $totalBookings = $bookings->count();
        
        if ($totalBookings === 0) {
            return 'Your business intelligence hub is ready. Start accepting bookings to generate AI-driven insights.';
        }

        $slowestDay = null;
        $lowestCount = PHP_INT_MAX;
        $bookingsByDay = $bookings->groupBy(function ($booking) {
            return date('l', strtotime($booking->date));
        });
        
        foreach ($bookingsByDay as $day => $dayBookings) {
            if ($dayBookings->count() < $lowestCount) {
                $lowestCount = $dayBookings->count();
                $slowestDay = $day;
            }
        }

        $topService = $bookings->groupBy('service_id')->sortByDesc(function ($group) {
            return $group->count();
        })->first();
        
        $topServiceName = $topService ? ($topService->first()->service->name ?? 'your services') : 'your services';

        return sprintf(
            'Your business has generated UGX %s in net profit across %d bookings. %s is currently driving the most volume, but %s remains your slowest day. Review the actionable insights below to optimize your operations.',
            number_format($totalNet),
            $totalBookings,
            $topServiceName,
            $slowestDay ?? 'the weekend'
        );
    }
}

<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Collection;
use Carbon\Carbon;

class ForecastService implements AnalyzerInterface
{
    public function analyze(array $financialFacts, array $operationalFacts): array
    {
        // 1. Demand Heatmap from operational facts
        $temporal = $operationalFacts['temporal'] ?? [];
        $weeklyBookings = $temporal['weekly_bookings'] ?? [];

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $maxBookings = max(array_values($weeklyBookings)) ?: 1; // avoid div by zero

        $demandByDay = [];
        foreach ($days as $day) {
            $count = $weeklyBookings[$day] ?? 0;
            $demandByDay[] = [
                'day' => $day,
                'bookings' => $count,
                'intensity' => round($count / $maxBookings, 2)
            ];
        }

        // 2. Projected Monthly Net (Simple run-rate projection)
        // Use canonical financial facts for current month revenue
        $now = now();
        $daysInMonth = $now->daysInMonth;
        $currentDay = $now->day;

        // Calculate current month net from trend data
        $trend = $financialFacts['trend'] ?? [];
        $currentMonthNet = collect($trend)
            ->filter(function ($day) use ($now) {
                return Carbon::parse($day['date'])->isSameMonth($now);
            })
            ->sum('net_revenue');

        $dailyRunRate = $currentDay > 0 ? $currentMonthNet / $currentDay : 0;
        $projectedMonthlyNet = round($currentMonthNet + ($dailyRunRate * ($daysInMonth - $currentDay)));

        // 3. Demand Spike Days - removed hardcoded 25th-31st logic
        // TODO: Implement historical pattern-based spike detection
        $spikeDays = [];

        return [
            'analytics' => [
                'demand' => [
                    'by_day_of_week' => $demandByDay
                ]
            ],
            'forecast' => [
                'projected_monthly_net' => $projectedMonthlyNet,
                'demand_spike_days' => $spikeDays,
                'confidence' => 'low' // Placeholder until historical pattern detection is implemented
            ]
        ];
    }
}

<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Collection;
use Carbon\Carbon;

class ForecastService implements AnalyzerInterface
{
    public function analyze(array $financialFacts, array $operationalFacts, Collection $bookings): array
    {
        // 1. Demand Heatmap
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $byDay = array_fill_keys($days, 0);
        
        foreach ($bookings as $booking) {
            $dayName = Carbon::parse($booking->date)->format('l');
            if (isset($byDay[$dayName])) {
                $byDay[$dayName]++;
            }
        }
        
        $maxBookings = max($byDay) ?: 1; // avoid div by zero
        
        $demandByDay = [];
        foreach ($days as $day) {
            $count = $byDay[$day];
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
        $trend = $financialFacts['trend'];
        $currentMonthNet = collect($trend)
            ->filter(function ($day) use ($now) {
                return Carbon::parse($day['date'])->isSameMonth($now);
            })
            ->sum('net_revenue');
        
        $dailyRunRate = $currentDay > 0 ? $currentMonthNet / $currentDay : 0;
        $projectedMonthlyNet = round($currentMonthNet + ($dailyRunRate * ($daysInMonth - $currentDay)));

        // 3. Demand Spike Days (End of month 25th-31st)
        $spikeDays = [];
        for ($i = 25; $i <= $daysInMonth; $i++) {
            $date = Carbon::create($now->year, $now->month, $i);
            if ($date >= $now) {
                $spikeDays[] = $date->toDateString();
            }
        }

        return [
            'analytics' => [
                'demand' => [
                    'by_day_of_week' => $demandByDay
                ]
            ],
            'forecast' => [
                'projected_monthly_net' => $projectedMonthlyNet,
                'demand_spike_days' => $spikeDays
            ]
        ];
    }
}

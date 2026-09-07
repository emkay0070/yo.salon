<?php

namespace App\Domain\Specialist\Services;

use App\Models\Specialist;
use App\Models\Booking;
use App\Domain\Finance\Ledger\LedgerAccount;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class SpecialistDashboardService
{
    public function __construct(
        private readonly SpecialistCalendarService $calendarService
    ) {}

    /**
     * Get aggregated stats for the specialist dashboard
     * 
     * @param Specialist $specialist
     * @return array
     */
    public function getDashboardStats(Specialist $specialist): array
    {
        $today = Carbon::today();

        // 1. Earnings (Finance Domain)
        $ledgerAccount = LedgerAccount::getOrCreateFor(
            Specialist::class,
            $specialist->id,
            'receivable',
            'UGX'
        );

        $earningsToday = $ledgerAccount->entries()
            ->credits()
            ->where('created_at', '>=', $today)
            ->sum('amount');

        // 2. Completed Appointments (Booking Domain)
        $completedToday = Booking::where('specialist_id', $specialist->id)
            ->where('date', $today->format('Y-m-d'))
            ->where('status', 'completed')
            ->count();

        // 3. Waiting Customers (Queue/Booking Domain)
        // For V1, checking current queue/booking state
        $waitingCustomers = Booking::where('specialist_id', $specialist->id)
            ->where('date', $today->format('Y-m-d'))
            ->whereIn('status', ['confirmed', 'in-progress', 'in_progress', 'waiting', 'checked_in'])
            ->count();

        // 4. Rating (Review Domain / Specialist Identity)
        $rating = $specialist->rating ?? 0.0;

        // 5. Today's Schedule (Availability Domain)
        $weekStart = CarbonImmutable::today()->startOfWeek(Carbon::MONDAY);
        $calendarData = $this->calendarService->getWeekCalendar($specialist, $weekStart);
        $todayString = $today->toDateString();
        $todaySchedule = null;
        
        foreach ($calendarData['days'] as $day) {
            if ($day['date'] === $todayString) {
                $todaySchedule = $day;
                break;
            }
        }

        return [
            'earnings' => (float) $earningsToday,
            'rating' => (float) $rating,
            'waitingCustomers' => $waitingCustomers,
            'completedToday' => $completedToday,
            'todaySchedule' => $todaySchedule,
        ];
    }
}

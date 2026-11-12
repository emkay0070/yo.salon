<?php

namespace App\Domain\Specialist\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
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
     * Get aggregated stats for the specialist dashboard.
     *
     * Two modes:
     *   GLOBAL (no $assignment): aggregates across every active workspace.
     *   WORKSPACE ($assignment set): restricts all counts to the specific provider.
     *
     * @param  Specialist               $specialist
     * @param  SpecialistAssignment|null $assignment  Null = global mode
     * @return array
     */
    public function getDashboardStats(Specialist $specialist, ?SpecialistAssignment $assignment = null): array
    {
        $today = Carbon::today();

        // 1. Earnings (Finance Domain) — always global (ledger is per-specialist)
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
        // Count bookings where either the booking status is completed OR the session status is completed
        $completedQuery = Booking::where('specialist_id', $specialist->id)
            ->where('date', $today->format('Y-m-d'))
            ->where(function ($query) {
                $query->where('status', 'completed')
                    ->orWhereHas('bookingServices', function ($q) {
                        $q->where('session_status', 'completed');
                    });
            });

        // In workspace mode: scope to this provider only
        if ($assignment) {
            $completedQuery->where('provider_id', $assignment->provider_id);
        }

        $completedToday = $completedQuery->count();

        // 3. Waiting Customers (Queue/Booking Domain)
        $waitingQuery = Booking::where('specialist_id', $specialist->id)
            ->where('date', $today->format('Y-m-d'))
            ->whereIn('status', ['confirmed', 'in-progress', 'in_progress', 'waiting', 'checked_in']);

        // In workspace mode: scope to this provider only
        if ($assignment) {
            $waitingQuery->where('provider_id', $assignment->provider_id);
        }

        $waitingCustomers = $waitingQuery->count();

        // 4. Rating (Review Domain / Specialist Identity) — always global
        $rating = $specialist->rating ?? 0.0;

        // 5. Today's Schedule (Availability Domain)
        // In workspace mode the calendar resolves the schedule for this assignment.
        // In global mode it resolves from the primary assignment.
        $weekStart = CarbonImmutable::today()->startOfWeek(Carbon::MONDAY);
        $calendarData = $this->calendarService->getWeekCalendar($specialist, $weekStart, $assignment);
        $todayString = $today->toDateString();
        $todaySchedule = null;

        foreach ($calendarData['days'] as $day) {
            if ($day['date'] === $todayString) {
                $todaySchedule = $day;
                break;
            }
        }

        return [
            'earnings'         => (float) $earningsToday,
            'rating'           => (float) $rating,
            'waitingCustomers' => $waitingCustomers,
            'completedToday'   => $completedToday,
            'todaySchedule'    => $todaySchedule,
            'context'          => $assignment ? 'workspace' : 'global',
            'provider_id'      => $assignment?->provider_id,
        ];
    }
}

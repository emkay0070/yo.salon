<?php

namespace App\Domain\Specialist\Services;

use App\Domain\Availability\Queries\AvailabilityContextBuilder;
use App\Models\Booking;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Carbon\CarbonImmutable;

/**
 * SpecialistCalendarService
 *
 * Answers the question: "What is happening around this specialist for a given week?"
 *
 * This is NOT an availability engine.
 * It does NOT generate bookable slots.
 * It does NOT re-implement schedule resolution.
 *
 * Instead it:
 *   1. Asks the Scheduling Domain (AvailabilityContextBuilder) for the resolved
 *      schedule per day — including overrides, exceptions, and time-off.
 *   2. Fetches the actual bookings for the week (assignment-aware, with salon context).
 *   3. Assembles a read-model calendar the Specialist OS can display.
 *
 * The Availability Domain remains the single source of truth for what a specialist
 * can be booked for. This service only answers what IS happening, not what COULD happen.
 */
class SpecialistCalendarService
{
    public function __construct(
        private readonly AvailabilityContextBuilder $contextBuilder,
    ) {}

    /**
     * Build the weekly calendar read-model for a specialist.
     *
     * @param  Specialist       $specialist
     * @param  CarbonImmutable  $weekStart  Monday of the target week
     * @return array{
     *     week_start: string,
     *     week_end:   string,
     *     days:       array,
     *     bookings:   array,
     * }
     */
    public function getWeekCalendar(Specialist $specialist, CarbonImmutable $weekStart): array
    {
        $weekEnd = $weekStart->addDays(6)->endOfDay();

        // ── 1. Resolve the active assignment(s) ────────────────────────────
        // A specialist may work at multiple salons. Fetch all active assignments
        // so each booking can be associated to the correct salon context.
        $assignments = SpecialistAssignment::where('specialist_id', $specialist->id)
            ->with('salon')
            ->get()
            ->keyBy('id');

        // Use the primary (or first) assignment for schedule resolution.
        // In a future multi-salon view this loop would be per-assignment.
        $primaryAssignment = $assignments->firstWhere('is_primary', true)
            ?? $assignments->first();

        // ── 2. Resolve schedule per day via Scheduling Domain ───────────────
        $days = [];
        if ($primaryAssignment) {
            for ($i = 0; $i < 7; $i++) {
                $date = $weekStart->addDays($i);
                $dayName = $date->format('l'); // Monday, Tuesday…

                // Delegate entirely to AvailabilityContextBuilder.
                // This handles: assignment schedule, overrides, exceptions,
                // salon mode, provider mode — exactly as the booking engine does.
                $context = $this->contextBuilder->build(
                    (string) $primaryAssignment->id,
                    $date
                );

                $resolved    = $context->resolvedSchedule;
                $hasTimeOff  = $context->timeOff->isNotEmpty();

                $days[] = [
                    'date'         => $date->toDateString(),
                    'day_name'     => $dayName,
                    'is_working'   => $resolved !== null && !$resolved->isClosed && !$hasTimeOff,
                    'is_closed'    => $resolved === null || $resolved->isClosed,
                    'has_time_off' => $hasTimeOff,
                    'open_time'    => $resolved?->openTime,
                    'close_time'   => $resolved?->closeTime,
                    'break_start'  => $resolved?->breakStart,
                    'break_end'    => $resolved?->breakEnd,
                    'time_off'     => $context->timeOff->map(fn ($t) => [
                        'id'     => $t->id,
                        'reason' => $t->reason,
                        'all_day'=> is_null($t->start_time),
                    ])->values(),
                    'has_override' => $context->scheduleOverride !== null,
                    'salon'        => $primaryAssignment->salon
                        ? ['id' => $primaryAssignment->salon->id, 'name' => $primaryAssignment->salon->name]
                        : null,
                ];
            }
        }

        // ── 3. Fetch bookings — assignment-aware, with salon context ────────
        // Filter by specialist_id to respect auth boundary.
        // Include salon and service so the UI can show "where" and "what".
        $bookings = Booking::where('specialist_id', $specialist->id)
            ->whereBetween('date', [
                $weekStart->toDateString(),
                $weekEnd->toDateString(),
            ])
            ->with(['customer', 'service', 'salon'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(fn ($b) => [
                'id'       => $b->id,
                'date'     => $b->date->format('Y-m-d'),
                'time'     => $b->time,
                'status'   => $b->status,
                'customer' => $b->customer?->name ?? 'Walk-in',
                'service'  => $b->service?->name ?? 'Service',
                // Salon context — essential for multi-salon specialists
                'salon'    => $b->salon
                    ? ['id' => $b->salon->id, 'name' => $b->salon->name]
                    : null,
            ]);

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end'   => $weekEnd->toDateString(),
            'days'       => $days,
            'bookings'   => $bookings,
        ];
    }
}

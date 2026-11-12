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
    /**
     * Build the weekly calendar read-model for a specialist.
     *
     * Two modes:
     *   GLOBAL (no $workspaceAssignment): aggregates all bookings across every
     *     active workplace. Schedule is resolved from the primary assignment.
     *   WORKSPACE ($workspaceAssignment set): restricts schedule resolution AND
     *     bookings to the specific provider of the verified assignment.
     *
     * @param  Specialist              $specialist
     * @param  CarbonImmutable         $weekStart          Monday of the target week
     * @param  SpecialistAssignment|null $workspaceAssignment  Null = global mode
     * @return array
     */
    public function getWeekCalendar(
        Specialist $specialist,
        CarbonImmutable $weekStart,
        ?SpecialistAssignment $workspaceAssignment = null
    ): array {
        $weekEnd = $weekStart->addDays(6)->endOfDay();

        // ── 1. Pick which assignment drives schedule resolution ─────────────
        // In workspace mode: use the verified assignment passed from the controller.
        // In global mode: use the primary (or first) assignment.
        if ($workspaceAssignment) {
            // Workspace mode — one assignment only
            $assignmentForSchedule = $workspaceAssignment;
        } else {
            // Global mode — derive primary
            $allAssignments = SpecialistAssignment::where('specialist_id', $specialist->id)
                ->with('location')
                ->get()
                ->keyBy('id');

            $assignmentForSchedule = $allAssignments->firstWhere('is_primary', true)
                ?? $allAssignments->first();
        }

        // ── 2. Resolve schedule per day via Scheduling Domain ───────────────
        $days = [];
        if ($assignmentForSchedule) {
            for ($i = 0; $i < 7; $i++) {
                $date    = $weekStart->addDays($i);
                $dayName = $date->format('l'); // Monday, Tuesday…

                // Delegate entirely to AvailabilityContextBuilder.
                // This handles: assignment schedule, overrides, exceptions,
                // salon mode, provider mode — exactly as the booking engine does.
                $context = $this->contextBuilder->build(
                    (string) $assignmentForSchedule->id,
                    $date
                );

                $resolved   = $context->resolvedSchedule;
                $hasTimeOff = $context->timeOff->isNotEmpty();

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
                        'id'      => $t->id,
                        'reason'  => $t->reason,
                        'all_day' => is_null($t->start_time),
                    ])->values(),
                    'has_override' => $context->scheduleOverride !== null,
                    'salon'        => $assignmentForSchedule->location
                        ? ['id' => $assignmentForSchedule->location->salon->id ?? null, 'name' => $assignmentForSchedule->location->name]
                        : null,
                ];
            }
        }

        // ── 3. Fetch bookings — provider-scoped in workspace mode ───────────
        $bookingQuery = Booking::where('specialist_id', $specialist->id)
            ->whereBetween('date', [
                $weekStart->toDateString(),
                $weekEnd->toDateString(),
            ])
            ->with(['customer', 'service', 'location'])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('date')
            ->orderBy('time');

        // In workspace mode: scope bookings to this provider only.
        // In global mode: return all bookings across every workspace.
        if ($workspaceAssignment) {
            $bookingQuery->where('provider_id', $workspaceAssignment->provider_id);
        }

        $bookings = $bookingQuery->get()->map(fn ($b) => [
            'id'       => $b->id,
            'date'     => $b->date->format('Y-m-d'),
            'time'     => $b->time,
            'status'   => $b->status,
            'customer' => $b->customer?->name ?? 'Walk-in',
            'service'  => $b->service?->name ?? 'Service',
            // Salon context — essential for multi-salon specialists in global mode
            'salon'    => $b->location
                ? ['id' => $b->location->salon->id ?? null, 'name' => $b->location->name]
                : null,
        ]);

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end'   => $weekEnd->toDateString(),
            'days'       => $days,
            'bookings'   => $bookings,
            'context'    => $workspaceAssignment ? 'workspace' : 'global',
            'provider_id'=> $workspaceAssignment?->provider_id,
        ];
    }
}

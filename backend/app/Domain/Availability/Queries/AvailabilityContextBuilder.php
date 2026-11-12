<?php

namespace App\Domain\Availability\Queries;

use App\Domain\Availability\DTOs\AvailabilityContext;
use App\Domain\Availability\Resolvers\ScheduleResolver;
use App\Models\LocationSchedule;
use App\Models\ProviderSchedule;
use App\Models\AssignmentSchedule;
use App\Models\SpecialistTimeOff;
use App\Models\Booking;
use App\Models\SpecialistAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * AvailabilityContextBuilder
 *
 * THIS IS THE ONLY CLASS IN THE AVAILABILITY DOMAIN THAT QUERIES THE DATABASE.
 *
 * Loads all the facts:
 *   - Provider, Salon, Assignment entities
 *   - Schedules for each layer
 *   - Schedule overrides for the specific date
 *   - Time-off entries
 *   - Existing bookings
 *
 * ... then hands those fully-loaded objects to ScheduleResolver, and assembles
 * the final immutable AvailabilityContext DTO that flows through the engines.
 */
class AvailabilityContextBuilder
{
    public function __construct(
        private readonly ScheduleResolver $scheduleResolver,
    ) {}

    public function build(string $assignmentId, CarbonImmutable $date, ?string $serviceId = null): AvailabilityContext
    {
        $assignment = SpecialistAssignment::findOrFail($assignmentId);
        $dayOfWeekString  = $date->format('l'); // Monday, Tuesday, etc.
        $dayOfWeekInt = $date->dayOfWeek; // 0-6 (Sunday-Saturday)

        // --- 1. Load ALL OBJECTS (no partial data) ---

        // Provider schedule for this day (only ACTIVE schedules are applied to availability)
        $providerSched = ProviderSchedule::where('provider_id', $assignment->provider_id)
            ->where('day_of_week', $dayOfWeekInt)
            ->where('status', 'ACTIVE')
            ->first();

        // Location schedule for this day
        $locationSched = null;
        if ($assignment->location_id) {
            $locationSched = \App\Models\LocationSchedule::where('location_id', $assignment->location_id)
                ->where('day_of_week', $dayOfWeekString)
                ->first();
        }

        // Assignment schedule for this day
        $assignmentSched = AssignmentSchedule::where('assignment_id', $assignmentId)
            ->where('day_of_week', $dayOfWeekString)
            ->where('status', 'ACTIVE')
            ->first();

        // Schedule override for this specific date
        $scheduleOverride = \App\Models\ScheduleOverride::where('assignment_id', $assignmentId)
            ->where('date', $date->toDateString())
            ->first();

        // Schedule exceptions (hierarchical, for this date)
        // Applies provider -> salon -> assignment cascades. Narrowest scope wins downstream.
        $scheduleExceptions = \App\Models\ScheduleException::forAssignment($assignmentId)
            ->onDate($date)
            ->orderByRaw("CASE scope_level WHEN 'assignment' THEN 1 WHEN 'salon' THEN 2 WHEN 'provider' THEN 3 ELSE 4 END")
            ->get();

        // Time-off for this date
        $timeOff = SpecialistTimeOff::where('assignment_id', $assignmentId)
            ->where('date', $date->toDateString())
            ->where('status', 'APPROVED')
            ->get();

        // Existing bookings for this assignment/specialist on this date
        $existingBookings = Booking::where('specialist_id', $assignment->specialist_id)
            ->where('provider_id', $assignment->provider_id)
            ->when($assignment->location_id, fn($q) => $q->where('location_id', $assignment->location_id))
            ->where('date', $date->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get();

        // --- 2. Hand OBJECTS to Resolver (no arrays, no DB calls inside Resolver) ---
        $resolvedSchedule = $this->scheduleResolver->resolve(
            providerSchedule:  $providerSched,
            locationSchedule:  $locationSched,
            locationMode:       'INHERIT',
            assignmentSchedule: $assignmentSched,
            assignmentMode:    $assignment->schedule_mode ?? 'INHERIT',
        );

        return new AvailabilityContext(
            assignmentId:       $assignmentId,
            specialistId:       $assignment->specialist_id,
            providerId:         $assignment->provider_id,
            locationId:         $assignment->location_id,
            date:               $date,
            serviceId:          $serviceId,
            resolvedSchedule:   $resolvedSchedule,
            scheduleOverride:   $scheduleOverride?->toArray(),
            scheduleExceptions: $scheduleExceptions,
            timeOff:            $timeOff,
            existingBookings:   $existingBookings,
        );
    }
}

<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\Location;
// Salon model removed — legacy salonId params now represent Location IDs
use App\Models\Service;
use App\Models\Staff;
use App\Models\Booking;
use App\Domain\Availability\DTOs\ProviderLocationContext;
use App\Domain\Availability\Statuses\AvailabilityDomainStatus;
use Carbon\Carbon;

class CustomerAvailabilityService
{
    // -------------------------------------------------------------------------
    // PRIMARY API — Provider + Location
    // -------------------------------------------------------------------------

    public function getAvailableSlots(
        string $providerId,
        ?string $locationId,
        ?string $serviceId,
        ?string $staffId,
        string $date
    ): array {
        $ctx      = new ProviderLocationContext($providerId, $locationId);
        $provider = Provider::findOrFail($providerId);
        $location = $locationId ? Location::find($locationId) : null;
        $service  = $serviceId ? Service::withoutGlobalScope('salon')->find($serviceId) : null;

        // Resolve location-specific buffer overrides to match AvailabilityEngine and BookingConflictService
        $bufferBefore = 0;
        $bufferAfter = 0;
        if ($service && $location) {
            $catalogResolver = new LocationServiceCatalogResolver();
            $resolvedService = $catalogResolver->queryAllServices($location)
                ->where('services.id', $service->id)
                ->first();
            $bufferBefore = $resolvedService?->pivot_buffer_before_override ?? $service->buffer_before ?? 0;
            $bufferAfter = $resolvedService?->pivot_buffer_after_override ?? $service->buffer_after ?? 0;
        } elseif ($service) {
            $bufferBefore = $service->buffer_before ?? 0;
            $bufferAfter = $service->buffer_after ?? 0;
        }

        $duration    = $service ? $service->duration : 60;
        $totalDuration = $duration + $bufferBefore + $bufferAfter;
        $dayOfWeek   = Carbon::parse($date)->dayOfWeek;
        $openingHours = $this->resolveScheduleByContext($ctx, $dayOfWeek);

        $allStatuses = [];
        $timezone    = $location?->timezone ?? $provider->timezone ?? 'Africa/Kampala';

        if (!$openingHours) {
            return [
                'provider_id' => $providerId,
                'location_id' => $locationId,
                'date'        => $date,
                'service_id'  => $serviceId,
                'staff_id'    => $staffId,
                'duration'    => $duration,
                'is_closed'   => true,
                'slots'       => [],
                'first_available_slot'  => null,
                'total_available_slots' => 0,
                'domain_status'  => AvailabilityDomainStatus::BRANCH_CLOSED->value,
                'domain_statuses' => [AvailabilityDomainStatus::BRANCH_CLOSED->value],
                'timezone'       => $timezone,
                'operating_hours' => null,
            ];
        }

        $existingBookings = $this->getExistingBookingsByContext($ctx, $staffId, $date);
        $slots = $this->generateTimeSlots($openingHours, $totalDuration, $existingBookings);

        $primary = count($slots) > 0
            ? AvailabilityDomainStatus::AVAILABLE
            : AvailabilityDomainStatus::NO_WINDOWS;

        if ($primary === AvailabilityDomainStatus::NO_WINDOWS) {
            $allStatuses[] = AvailabilityDomainStatus::NO_WINDOWS;
        }

        return [
            'provider_id' => $providerId,
            'location_id' => $locationId,
            'date'        => $date,
            'service_id'  => $serviceId,
            'staff_id'    => $staffId,
            'duration'    => $duration,
            'timezone'    => $timezone,
            'operating_hours' => [
                'open'  => $openingHours['open'],
                'close' => $openingHours['close'],
            ],
            'is_closed'   => false,
            'slots'       => $slots,
            'first_available_slot'  => count($slots) > 0 ? $slots[0] : null,
            'total_available_slots' => count($slots),
            'domain_status'  => $primary->value,
            'domain_statuses' => array_values(array_unique(array_map(
                static fn (AvailabilityDomainStatus $s) => $s->value,
                array_merge([$primary], $allStatuses),
            ))),
        ];
    }

    public function getAvailableDates(
        string $providerId,
        ?string $locationId,
        string $year,
        string $month
    ): array {
        $ctx           = new ProviderLocationContext($providerId, $locationId);
        $availableDates = [];
        $startDate = Carbon::create($year, $month, 1);
        $endDate   = $startDate->copy()->endOfMonth();

        while ($startDate->lte($endDate)) {
            $dayOfWeek    = $startDate->dayOfWeek;
            $openingHours = $this->resolveScheduleByContext($ctx, $dayOfWeek);

            if ($openingHours) {
                $availableDates[] = [
                    'date'         => $startDate->toDateString(),
                    'day_of_week'  => $dayOfWeek,
                    'is_available' => true,
                    'opening_hours' => $openingHours,
                ];
            }

            $startDate->addDay();
        }

        return [
            'year'            => $year,
            'month'           => $month,
            'available_dates' => $availableDates,
        ];
    }

    /**
     * Resolve opening hours for a given day via Provider+Location context.
     *
     * - locationId set  → LocationSchedule (CUSTOM) falling back to ProviderSchedule (INHERIT)
     * - locationId null → ProviderSchedule only
     */
    private function resolveScheduleByContext(ProviderLocationContext $ctx, int $dayOfWeek): ?array
    {
        // Try LocationSchedule first if we have a location
        if ($ctx->locationId) {
            $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $dayName  = ucfirst($dayNames[$dayOfWeek]);

            $locationSchedule = \App\Models\LocationSchedule::where('location_id', $ctx->locationId)
                ->where('day_of_week', $dayName)
                ->first();

            if ($locationSchedule && !$locationSchedule->is_closed) {
                return [
                    'open'  => substr($locationSchedule->open_time, 0, 5),
                    'close' => substr($locationSchedule->close_time, 0, 5),
                    'break_start' => null,
                    'break_end'   => null,
                ];
            }

            if ($locationSchedule && $locationSchedule->is_closed) {
                return null; // Location explicitly closed this day
            }
            // No LocationSchedule — fall through to ProviderSchedule
        }

        // Provider-level schedule (integer day_of_week)
        $providerSchedule = \App\Models\ProviderSchedule::where('provider_id', $ctx->providerId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if ($providerSchedule && !$providerSchedule->is_closed) {
            return [
                'open'  => substr($providerSchedule->open_time, 0, 5),
                'close' => substr($providerSchedule->close_time, 0, 5),
                'break_start' => null,
                'break_end'   => null,
            ];
        }

        return null;
    }

    private function getExistingBookingsByContext(
        ProviderLocationContext $ctx,
        ?string $staffId,
        string $date
    ): array {
        $query = \App\Models\BookingService::with(['booking', 'service'])
            ->whereHas('booking', function ($q) use ($ctx, $date) {
                $q->where('provider_id', $ctx->providerId)
                  ->where('date', $date)
                  ->whereIn('status', ['confirmed', 'pending']);

                if ($ctx->locationId) {
                    $q->where('location_id', $ctx->locationId);
                }
            });

        if ($staffId) {
            $query->whereHas('booking', function ($q) use ($staffId) {
                $q->where('specialist_id', $staffId);
            });
        }

        $bookingServices = $query->get();

        $location = $ctx->locationId ? \App\Models\Location::find($ctx->locationId) : null;

        return $bookingServices->map(function ($bs) use ($location) {
            $start = $bs->start_time ? Carbon::parse($bs->start_time)->toTimeString() : null;
            $end = $bs->end_time ? Carbon::parse($bs->end_time)->toTimeString() : null;

            if (!$end && $bs->service) {
                // Calculate end time with the booking's own service buffers
                $bufferBefore = $bs->service->buffer_before ?? 0;
                $bufferAfter = $bs->service->buffer_after ?? 0;

                // Apply location-specific buffer overrides if available
                if ($location && $bs->service) {
                    $catalogResolver = new LocationServiceCatalogResolver();
                    $resolvedService = $catalogResolver->queryAllServices($location)
                        ->where('services.id', $bs->service->id)
                        ->first();
                    $bufferBefore = $resolvedService?->pivot_buffer_before_override ?? $bufferBefore;
                    $bufferAfter = $resolvedService?->pivot_buffer_after_override ?? $bufferAfter;
                }

                $totalDuration = ($bs->service->duration ?? 60) + $bufferBefore + $bufferAfter;
                $end = Carbon::parse($bs->start_time)->addMinutes($totalDuration)->toTimeString();
            }

            return [
                'start' => $start ? substr($start, 0, 5) : null,
                'end' => $end ? substr($end, 0, 5) : null,
                'staff_id' => $bs->booking->specialist_id,
            ];
        })->toArray();
    }

    private function generateTimeSlots(array $openingHours, int $duration, array $existingBookings): array
    {
        $slots = [];
        $currentTime = Carbon::parse($openingHours['open']);
        $closeTime = Carbon::parse($openingHours['close']);

        $breakStart = isset($openingHours['break_start']) ? Carbon::parse($openingHours['break_start']) : null;
        $breakEnd = isset($openingHours['break_end']) ? Carbon::parse($openingHours['break_end']) : null;

        while ($currentTime->copy()->addMinutes($duration)->lte($closeTime)) {
            if ($breakStart && $breakEnd) {
                if ($currentTime->between($breakStart, $breakEnd)) {
                    $currentTime = $breakEnd->copy();
                    continue;
                }
            }

            $slotStart = $currentTime->toTimeString();
            $slotEnd = $currentTime->copy()->addMinutes($duration)->toTimeString();

            if ($breakStart && $breakEnd
                && Carbon::parse($slotStart)->lt($breakEnd)
                && Carbon::parse($slotEnd)->gt($breakStart)) {
                $currentTime->addMinutes(15);
                continue;
            }

            $isAvailable = !$this->hasConflict($slotStart, $slotEnd, $existingBookings);

            $slots[] = [
                'start' => substr($slotStart, 0, 5),
                'end' => substr($slotEnd, 0, 5),
                'duration' => $duration,
                'available' => $isAvailable,
                'time' => substr($slotStart, 0, 5),
            ];

            $currentTime->addMinutes(15);
        }

        return $slots;
    }

    private function hasConflict(string $slotStart, string $slotEnd, array $existingBookings): bool
    {
        $slotStartCarbon = Carbon::parse($slotStart);
        $slotEndCarbon = Carbon::parse($slotEnd);

        foreach ($existingBookings as $booking) {
            $bookingStart = Carbon::parse($booking['start']);
            $bookingEnd = Carbon::parse($booking['end'] ?? $booking['start'])->addMinutes($booking['duration'] ?? 60);

            if ($slotStartCarbon->lt($bookingEnd) && $slotEndCarbon->gt($bookingStart)) {
                return true;
            }
        }

        return false;
    }

    public function getAvailableStaff(string $salonId, string $serviceId, string $date, string $time): array
    {
        $service = Service::withoutGlobalScope('salon')->find($serviceId);
        if (!$service) {
            throw new \Exception('Service not found');
        }

        $duration = $service->duration;
        $slotStart = Carbon::parse($time);
        $slotEnd = $slotStart->copy()->addMinutes($duration);

        // Resolve provider_id and location_id from Location (salonId is a legacy Location ID)
        $location = \App\Models\Location::find($salonId);
        if (!$location) {
            throw new \Exception('Location not found');
        }

        $allStaff = Staff::withoutGlobalScope('salon')
            ->where('provider_id', $location->provider_id)
            ->where('location_id', $location->id)
            ->where('active', true)
            ->get();

        $existingBookings = Booking::where('location_id', $salonId)
            ->where('date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->get();

        $availableStaff = [];

        foreach ($allStaff as $staff) {
            if (!$this->staffOffersService($staff, $service)) {
                continue;
            }

            $isAvailable = true;
            foreach ($existingBookings as $booking) {
                // Match booking to staff by staff_id only (canonical local identity)
                $bookingStaffId = $booking->staff_id;
                if ($bookingStaffId !== $staff->id) {
                    continue;
                }

                $bookingStart = Carbon::parse($booking->start_time ?? $booking->time);
                $bookingEnd = $booking->end_time
                    ? Carbon::parse($booking->end_time)
                    : $bookingStart->copy()->addMinutes($booking->service->duration ?? 60);

                if ($slotStart->lt($bookingEnd) && $slotEnd->gt($bookingStart)) {
                    $isAvailable = false;
                    break;
                }
            }

            if ($isAvailable) {
                $availableStaff[] = [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'avatar' => $staff->avatar,
                ];
            }
        }

        return [
            'date' => $date,
            'time' => $time,
            'service_id' => $serviceId,
            'available_staff' => $availableStaff,
        ];
    }

    private function staffOffersService(Staff $staff, Service $service): bool
    {
        // Resolve specialist_id from staff via SpecialistAssignment
        $assignment = \App\Models\SpecialistAssignment::where('staff_id', $staff->id)
            ->where('provider_id', $staff->provider_id)
            ->whereIn('status', ['active', 'ACTIVE'])
            ->first();

        if (!$assignment) {
            return false; // No specialist assignment found
        }

        // Check if specialist offers this service via specialist_service pivot
        return \DB::table('specialist_service')
            ->where('specialist_id', $assignment->specialist_id)
            ->where('service_id', $service->id)
            ->exists();
    }

    public function getSpecialistsForService(string $salonId, string $serviceId): array
    {
        $service = Service::withoutGlobalScope('salon')->find($serviceId);
        if (!$service) {
            throw new \Exception('Service not found');
        }

        // Resolve provider_id and location_id from Location (salonId is a legacy Location ID)
        $location = \App\Models\Location::find($salonId);
        if (!$location) {
            throw new \Exception('Location not found');
        }

        $allStaff = Staff::withoutGlobalScope('salon')
            ->where('provider_id', $location->provider_id)
            ->where('location_id', $location->id)
            ->where('active', true)
            ->get();

        \Log::info('getSpecialistsForService', [
            'location_id' => $salonId,
            'service_id' => $serviceId,
            'staff_count' => $allStaff->count(),
        ]);

        $specialists = [];

        foreach ($allStaff as $staff) {
            if (!$this->staffOffersService($staff, $service)) {
                continue;
            }

            $specialists[] = [
                'id' => $staff->id,
                'name' => $staff->name,
                'avatar' => $staff->avatar,
                'available' => true,
            ];
        }

        return [
            'service_id' => $serviceId,
            'location_id' => $salonId, // semantically a Location ID
            'available_staff' => $specialists,
        ];
    }
}

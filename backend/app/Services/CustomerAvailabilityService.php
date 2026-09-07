<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Booking;
use App\Domain\Availability\Statuses\AvailabilityDomainStatus;
use Carbon\Carbon;

class CustomerAvailabilityService
{
    public function getAvailableSlots(string $salonId, ?string $serviceId, ?string $staffId, string $date): array
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            throw new \Exception('Salon not found');
        }

        $service = $serviceId ? Service::withoutGlobalScope('salon')->find($serviceId) : null;
        $staff = $staffId ? Staff::withoutGlobalScope('salon')->find($staffId) : null;

        $duration = $service ? $service->duration : 60;

        $dayOfWeek = Carbon::parse($date)->dayOfWeek;
        $openingHours = $this->getOpeningHoursForDay($salon, $dayOfWeek);

        $allStatuses = [];

        if (!$openingHours) {
            return [
                'salon_id' => $salonId,
                'date' => $date,
                'service_id' => $serviceId,
                'staff_id' => $staffId,
                'duration' => $duration,
                'is_closed' => true,
                'slots' => [],
                'first_available_slot' => null,
                'total_available_slots' => 0,
                'domain_status' => AvailabilityDomainStatus::BRANCH_CLOSED->value,
                'domain_statuses' => [AvailabilityDomainStatus::BRANCH_CLOSED->value],
                'timezone' => $salon->timezone ?? 'Africa/Kampala',
                'operating_hours' => null,
            ];
        }

        $existingBookings = $this->getExistingBookings($salonId, $staffId, $date);

        $slots = $this->generateTimeSlots($openingHours, $duration, $existingBookings);

        if (count($slots) > 0) {
            $primary = AvailabilityDomainStatus::AVAILABLE;
        } else {
            $primary = AvailabilityDomainStatus::NO_WINDOWS;
            $allStatuses[] = AvailabilityDomainStatus::NO_WINDOWS;
        }

        return [
            'salon_id' => $salonId,
            'date' => $date,
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'duration' => $duration,
            'timezone' => $salon->timezone ?? 'Africa/Kampala',
            'operating_hours' => [
                'open' => $openingHours['open'],
                'close' => $openingHours['close'],
            ],
            'is_closed' => false,
            'slots' => $slots,
            'first_available_slot' => count($slots) > 0 ? $slots[0] : null,
            'total_available_slots' => count($slots),
            'domain_status' => $primary->value,
            'domain_statuses' => array_values(array_unique(array_map(
                static fn (AvailabilityDomainStatus $s) => $s->value,
                array_merge([$primary], $allStatuses),
            ))),
        ];
    }

    public function getAvailableDates(string $salonId, string $year, string $month): array
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            throw new \Exception('Salon not found');
        }

        $availableDates = [];
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        while ($startDate->lte($endDate)) {
            $dayOfWeek = $startDate->dayOfWeek;
            $openingHours = $this->getOpeningHoursForDay($salon, $dayOfWeek);

            if ($openingHours) {
                $availableDates[] = [
                    'date' => $startDate->toDateString(),
                    'day_of_week' => $dayOfWeek,
                    'is_available' => true,
                    'opening_hours' => $openingHours,
                ];
            }

            $startDate->addDay();
        }

        return [
            'year' => $year,
            'month' => $month,
            'available_dates' => $availableDates,
        ];
    }

    private function getOpeningHoursForDay(Salon $salon, int $dayOfWeek): ?array
    {
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $dayName = $dayNames[$dayOfWeek];

        // Check if salon has INHERIT mode - use provider schedule
        if ($salon->schedule_mode === 'INHERIT' && $salon->provider_id) {
            $providerSchedule = \App\Models\ProviderSchedule::where('provider_id', $salon->provider_id)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            if ($providerSchedule && !$providerSchedule->is_closed) {
                return [
                    'open' => substr($providerSchedule->open_time, 0, 5),
                    'close' => substr($providerSchedule->close_time, 0, 5),
                    'break_start' => null,
                    'break_end' => null,
                ];
            }

            return null;
        }

        // Otherwise use salon's opening_hours
        $openingHours = $salon->opening_hours;

        if (!$openingHours || !isset($openingHours[$dayName])) {
            return null;
        }

        $dayHours = $openingHours[$dayName];

        if (!($dayHours['is_open'] ?? false)) {
            return null;
        }

        return [
            'open' => $dayHours['open'] ?? '09:00',
            'close' => $dayHours['close'] ?? '18:00',
            'break_start' => $dayHours['break_start'] ?? null,
            'break_end' => $dayHours['break_end'] ?? null,
        ];
    }

    private function getExistingBookings(string $salonId, ?string $staffId, string $date): array
    {
        $salon = \App\Models\Salon::find($salonId);
        $providerId = $salon ? $salon->provider_id : null;

        if (!$providerId) {
            return [];
        }

        $query = Booking::where('salon_id', $salonId)
            ->where('date', $date)
            ->whereIn('status', ['confirmed', 'pending']);

        if ($staffId) {
            // staff_id is the salon-local identity for bookings.
            // We no longer fall back to specialist_id — those are distinct identities.
            $query->where('staff_id', $staffId);
        }

        $bookings = $query->get();

        return $bookings->map(function ($booking) {
            $start = $booking->start_time ?? $booking->time;
            $end = $booking->end_time;
            if (!$end && $booking->service) {
                $end = Carbon::parse($start)->addMinutes($booking->service->duration ?? 60)->toTimeString();
            }
            return [
                'start' => $start,
                'end' => $end,
                // staff_id is the canonical local identity for slot occupancy
                'staff_id' => $booking->staff_id,
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

            if ($isAvailable) {
                $slots[] = [
                    'start' => substr($slotStart, 0, 5),
                    'end' => substr($slotEnd, 0, 5),
                    'duration' => $duration,
                    'available' => true,
                    'time' => substr($slotStart, 0, 5),
                ];
            }

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

        $allStaff = Staff::withoutGlobalScope('salon')
            ->where('salon_id', $salonId)
            ->where('active', true)
            ->get();

        $existingBookings = Booking::where('salon_id', $salonId)
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
                    'specializations' => $staff->specializations,
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
        return true;
    }

    public function getSpecialistsForService(string $salonId, string $serviceId): array
    {
        $service = Service::withoutGlobalScope('salon')->find($serviceId);
        if (!$service) {
            throw new \Exception('Service not found');
        }

        $allStaff = Staff::withoutGlobalScope('salon')
            ->where('salon_id', $salonId)
            ->where('active', true)
            ->get();

        \Log::info('getSpecialistsForService', [
            'salon_id' => $salonId,
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
                'specializations' => $staff->specializations,
                'avatar' => $staff->avatar,
                'available' => true,
            ];
        }

        return [
            'service_id' => $serviceId,
            'salon_id' => $salonId,
            'available_staff' => $specialists,
        ];
    }
}

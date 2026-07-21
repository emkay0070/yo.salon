<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Booking;
use Carbon\Carbon;

class CustomerAvailabilityService
{
    /**
     * Get available time slots for a customer booking
     */
    public function getAvailableSlots(string $salonId, ?string $serviceId, ?string $staffId, string $date): array
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            throw new \Exception('Salon not found');
        }

        $service = $serviceId ? Service::find($serviceId) : null;
        $staff = $staffId ? Staff::find($staffId) : null;

        // Get service duration
        $duration = $service ? $service->duration : 60; // Default 60 minutes

        // Get salon opening hours for the day
        $dayOfWeek = Carbon::parse($date)->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
        $openingHours = $this->getOpeningHoursForDay($salon, $dayOfWeek);

        if (!$openingHours) {
            return ['slots' => [], 'message' => 'Salon is closed on this day'];
        }

        // Get existing bookings for the day
        $existingBookings = $this->getExistingBookings($salonId, $staffId, $date);

        // Generate time slots
        $slots = $this->generateTimeSlots($openingHours, $duration, $existingBookings);

        return [
            'date' => $date,
            'salon_id' => $salonId,
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'duration' => $duration,
            'opening_hours' => $openingHours,
            'slots' => $slots,
        ];
    }

    /**
     * Get available dates for a month
     */
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

    /**
     * Get opening hours for a specific day
     */
    private function getOpeningHoursForDay(Salon $salon, int $dayOfWeek): ?array
    {
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $dayName = $dayNames[$dayOfWeek];

        $openingHours = $salon->opening_hours;

        if (!$openingHours || !isset($openingHours[$dayName])) {
            return null;
        }

        $dayHours = $openingHours[$dayName];

        if (!$dayHours['is_open'] ?? false) {
            return null;
        }

        return [
            'open' => $dayHours['open'] ?? '09:00',
            'close' => $dayHours['close'] ?? '18:00',
            'break_start' => $dayHours['break_start'] ?? null,
            'break_end' => $dayHours['break_end'] ?? null,
        ];
    }

    /**
     * Get existing bookings for a specific date
     */
    private function getExistingBookings(string $salonId, ?string $staffId, string $date): array
    {
        $query = Booking::where('salon_id', $salonId)
            ->where('date', $date)
            ->where('status', '!=', 'cancelled');

        if ($staffId) {
            $query->where('staff_id', $staffId);
        }

        $bookings = $query->get();

        return $bookings->map(function ($booking) {
            return [
                'time' => $booking->time,
                'duration' => $booking->service ? $booking->service->duration : 60,
                'staff_id' => $booking->staff_id,
            ];
        })->toArray();
    }

    /**
     * Generate available time slots
     */
    private function generateTimeSlots(array $openingHours, int $duration, array $existingBookings): array
    {
        $slots = [];
        $currentTime = Carbon::parse($openingHours['open']);
        $closeTime = Carbon::parse($openingHours['close']);

        // Handle break time
        $breakStart = isset($openingHours['break_start']) ? Carbon::parse($openingHours['break_start']) : null;
        $breakEnd = isset($openingHours['break_end']) ? Carbon::parse($openingHours['break_end']) : null;

        while ($currentTime->copy()->addMinutes($duration)->lte($closeTime)) {
            // Skip if during break
            if ($breakStart && $breakEnd) {
                if ($currentTime->between($breakStart, $breakEnd)) {
                    $currentTime = $breakEnd->copy();
                    continue;
                }
            }

            // Check if slot conflicts with existing bookings
            $slotStart = $currentTime->toTimeString();
            $slotEnd = $currentTime->copy()->addMinutes($duration)->toTimeString();

            $isAvailable = !$this->hasConflict($slotStart, $slotEnd, $existingBookings);

            if ($isAvailable) {
                $slots[] = [
                    'time' => $slotStart,
                    'end_time' => $slotEnd,
                    'available' => true,
                ];
            }

            $currentTime->addMinutes(30); // 30-minute intervals
        }

        return $slots;
    }

    /**
     * Check if time slot conflicts with existing bookings
     */
    private function hasConflict(string $slotStart, string $slotEnd, array $existingBookings): bool
    {
        foreach ($existingBookings as $booking) {
            $bookingStart = Carbon::parse($booking['time']);
            $bookingEnd = $bookingStart->copy()->addMinutes($booking['duration']);

            $slotStartCarbon = Carbon::parse($slotStart);
            $slotEndCarbon = Carbon::parse($slotEnd);

            // Check for overlap
            if ($slotStartCarbon < $bookingEnd && $slotEndCarbon > $bookingStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available staff for a service and time slot
     */
    public function getAvailableStaff(string $salonId, string $serviceId, string $date, string $time): array
    {
        $service = Service::find($serviceId);
        if (!$service) {
            throw new \Exception('Service not found');
        }

        $duration = $service->duration;
        $slotStart = Carbon::parse($time);
        $slotEnd = $slotStart->copy()->addMinutes($duration);

        // Get all active staff for the salon
        $allStaff = Staff::where('salon_id', $salonId)
            ->where('active', true)
            ->get();

        // Get existing bookings for the time slot
        $existingBookings = Booking::where('salon_id', $salonId)
            ->where('date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        $availableStaff = [];

        foreach ($allStaff as $staff) {
            // Check if staff offers this service
            if (!$this->staffOffersService($staff, $service)) {
                continue;
            }

            // Check if staff is available during the time slot
            $isAvailable = true;
            foreach ($existingBookings as $booking) {
                if ($booking->staff_id !== $staff->id) {
                    continue;
                }

                $bookingStart = Carbon::parse($booking->time);
                $bookingEnd = $bookingStart->copy()->addMinutes($booking->service->duration ?? 60);

                if ($slotStart < $bookingEnd && $slotEnd > $bookingStart) {
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

    /**
     * Check if staff offers a specific service
     */
    private function staffOffersService(Staff $staff, Service $service): bool
    {
        // This is a simplified check - in a real implementation,
        // you might have a staff_services pivot table
        return true; // For now, assume all staff offer all services
    }
}

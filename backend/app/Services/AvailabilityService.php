<?php

namespace App\Services;

use App\Models\Specialist;
use App\Models\SpecialistAvailability;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    /**
     * Generate availability slots for a specialist on a given date
     */
    public function generateSlots(string $specialistId, string $salonId, string $date): array
    {
        $specialist = Specialist::find($specialistId);
        if (!$specialist) {
            throw new \Exception('Specialist not found');
        }

        // Get specialist working hours (default 9AM - 6PM)
        $startTime = '09:00';
        $endTime = '18:00';
        $slotDuration = 30; // minutes

        $slots = [];
        $current = Carbon::parse($date . ' ' . $startTime);
        $end = Carbon::parse($date . ' ' . $endTime);

        while ($current < $end) {
            $slotEndTime = $current->copy()->addMinutes($slotDuration);
            
            // Check if slot already exists
            $existingSlot = SpecialistAvailability::where('specialist_id', $specialistId)
                ->where('salon_id', $salonId)
                ->where('date', $date)
                ->where('start_time', $current->format('H:i'))
                ->first();

            if (!$existingSlot) {
                $slot = SpecialistAvailability::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'specialist_id' => $specialistId,
                    'salon_id' => $salonId,
                    'date' => $date,
                    'start_time' => $current->format('H:i'),
                    'end_time' => $slotEndTime->format('H:i'),
                    'duration_minutes' => $slotDuration,
                    'is_available' => true,
                    'status' => 'available',
                ]);
                $slots[] = $slot;
            } else {
                $slots[] = $existingSlot;
            }

            $current = $slotEndTime;
        }

        return $slots;
    }

    /**
     * Get available slots for a specialist on a date
     */
    public function getAvailableSlots(string $specialistId, string $salonId, string $date): array
    {
        return SpecialistAvailability::where('specialist_id', $specialistId)
            ->where('salon_id', $salonId)
            ->where('date', $date)
            ->available()
            ->orderBy('start_time')
            ->with('specialist')
            ->get()
            ->toArray();
    }

    /**
     * Get available slots for all specialists in a salon on a date
     */
    public function getSalonAvailability(string $salonId, string $date): array
    {
        $specialists = Specialist::where('active', true)
            ->whereHas('salons', function ($query) use ($salonId) {
                $query->where('salon_id', $salonId)->where('salon_specialist.active', true);
            })
            ->get();

        $availability = [];
        foreach ($specialists as $specialist) {
            $slots = $this->getAvailableSlots($specialist->id, $salonId, $date);
            $availability[] = [
                'specialist' => $specialist->toArray(),
                'slots' => $slots,
            ];
        }

        return $availability;
    }

    /**
     * Book a slot
     */
    public function bookSlot(string $slotId, string $bookingId): bool
    {
        $slot = SpecialistAvailability::find($slotId);
        if (!$slot || !$slot->is_available) {
            throw new \Exception('Slot not available');
        }

        $slot->update([
            'is_available' => false,
            'status' => 'booked',
            'booking_id' => $bookingId,
        ]);

        return true;
    }

    /**
     * Release a slot (cancel booking)
     */
    public function releaseSlot(string $slotId): bool
    {
        $slot = SpecialistAvailability::find($slotId);
        if (!$slot) {
            throw new \Exception('Slot not found');
        }

        $slot->update([
            'is_available' => true,
            'status' => 'available',
            'booking_id' => null,
        ]);

        return true;
    }

    /**
     * Block a slot (for breaks, etc.)
     */
    public function blockSlot(string $slotId): bool
    {
        $slot = SpecialistAvailability::find($slotId);
        if (!$slot) {
            throw new \Exception('Slot not found');
        }

        $slot->update([
            'is_available' => false,
            'status' => 'blocked',
        ]);

        return true;
    }

    /**
     * Generate availability for a date range
     */
    public function generateAvailabilityForRange(string $specialistId, string $salonId, string $startDate, string $endDate): int
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = 0;

        while ($start <= $end) {
            // Skip weekends if needed
            if ($start->isWeekday()) {
                $this->generateSlots($specialistId, $salonId, $start->format('Y-m-d'));
                $days++;
            }
            $start->addDay();
        }

        return $days;
    }
}

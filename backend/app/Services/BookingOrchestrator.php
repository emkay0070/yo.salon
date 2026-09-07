<?php

namespace App\Services;

use App\Domain\Availability\Engines\AvailabilityEngine;
use App\Domain\Catalog\Engines\BookabilityEngine;
use App\Domain\Catalog\Queries\BranchCatalogQuery;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\SpecialistAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Events\BookingCreated;
use Illuminate\Support\Facades\Event;

/**
 * BookingOrchestrator
 * 
 * Manages the booking pipeline:
 * Validates availability & bookability -> Reservation -> Payment -> Confirmation
 */
class BookingOrchestrator
{
    private AvailabilityEngine $availabilityEngine;
    private BookingService $bookingService;

    public function __construct(
        AvailabilityEngine $availabilityEngine,
        BookingService $bookingService
    ) {
        $this->availabilityEngine = $availabilityEngine;
        $this->bookingService = $bookingService;
    }

    /**
     * Executes the booking pipeline.
     */
    public function reserveAndBook(array $validatedData): array
    {
        // 0. Idempotency Check
        if (!empty($validatedData['idempotency_key'])) {
            $existingBooking = Booking::where('idempotency_key', $validatedData['idempotency_key'])->first();
            if ($existingBooking) {
                // Determine if we need to return full payload (customer/portal) or just booking.
                // For simplicity, we just return the existing booking.
                return ['booking' => $existingBooking];
            }
        }

        $salonId = $validatedData['salon_id'];
        $serviceId = is_array($validatedData['service_id']) ? $validatedData['service_id'][0] : $validatedData['service_id'];
        $specialistId = $validatedData['specialist_id'] ?? null;
        $date = $validatedData['date'];
        $time = $validatedData['time'];

        $salon = Salon::findOrFail($salonId);
        
        // 1. Resolve Specialist Assignment
        $assignment = null;
        if ($specialistId) {
            $assignment = SpecialistAssignment::where('specialist_id', $specialistId)
                ->where('salon_id', $salonId)
                ->first();
            
            if (!$assignment) {
                throw new \Exception('Specialist is not assigned to this branch.');
            }
        }

        // 2. Validate Bookability & Availability
        $catalogQuery = new BranchCatalogQuery();
        $resolvedService = $catalogQuery->queryAllServices($salon)->where('services.id', $serviceId)->first();

        if (!$resolvedService) {
            throw new \Exception('Service not found in branch catalog.');
        }

        $bookabilityEngine = new BookabilityEngine($salon, $resolvedService);

        if (!$bookabilityEngine->isAvailableAtBranch()) {
            throw new \Exception('This service is not available at the selected branch.');
        }

        if (!$bookabilityEngine->acceptsOnlineBooking() && empty($validatedData['internal_booking'])) {
            throw new \Exception('Online booking is not available for this service at this branch.');
        }

        if ($assignment) {
            $bookabilityResult = $bookabilityEngine->isSlotBookable(
                $this->availabilityEngine,
                $assignment->id,
                $date,
                $time
            );

            if (!$bookabilityResult['bookable']) {
                throw new \Exception($bookabilityResult['reason']);
            }
        }

        // 3. Concurrency Locking using Cache (instead of DB row locking the whole assignment)
        $lockKey = "booking_slot_{$assignment->id}_{$date}_{$time}";
        $lock = Cache::lock($lockKey, 10); // lock for 10 seconds

        if (!$lock->get()) {
            throw new \Exception('This slot is currently being booked by someone else. Please try again or select another time.');
        }

        try {
            return DB::transaction(function () use ($validatedData) {
                // 4. Create Reservation (Locking the slot)
                // Set slot_locked_until to 10 minutes from now
                $validatedData['slot_locked_until'] = CarbonImmutable::now()->addMinutes(10);
                $validatedData['status'] = 'reserved';

                // 5. Delegate to BookingService for customer resolution and creation
                if (!empty($validatedData['create_account']) || !empty($validatedData['customer_email'])) {
                    return $this->bookingService->createBookingWithAccount($validatedData);
                } else {
                    $booking = $this->bookingService->createGuestBooking($validatedData);
                    return ['booking' => $booking];
                }
            });
        } finally {
            $lock->release();
        }
    }
}

<?php

namespace App\Services;

use App\Domain\Availability\Engines\AvailabilityEngine;
use App\Domain\Catalog\Engines\BookabilityEngine;
use App\Domain\Catalog\Queries\BranchCatalogQuery;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Service;
use App\Models\SpecialistAssignment;
use App\Services\ResourceAllocator;
use App\Services\BookingConflictService;
use Carbon\Carbon;
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
    private ResourceAllocator $resourceAllocator;
    private BookingConflictService $conflictService;

    public function __construct(
        AvailabilityEngine $availabilityEngine,
        BookingService $bookingService,
        ResourceAllocator $resourceAllocator,
        BookingConflictService $conflictService
    ) {
        $this->availabilityEngine = $availabilityEngine;
        $this->bookingService = $bookingService;
        $this->resourceAllocator = $resourceAllocator;
        $this->conflictService = $conflictService;
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

        // Extract provider_id and location_id
        $providerId = $validatedData['provider_id'];
        $locationId = $validatedData['location_id'] ?? null;

        $serviceId = is_array($validatedData['service_id']) ? $validatedData['service_id'][0] : $validatedData['service_id'];
        $specialistId = $validatedData['specialist_id'] ?? null;
        $date = $validatedData['date'];
        $time = $validatedData['time'];

        // For independent_specialist providers without specialist_id, resolve primary assignment
        if (!$specialistId) {
            $provider = \App\Models\Provider::find($providerId);
            if ($provider && $provider->type === \App\Models\Provider::TYPE_SPECIALIST) {
                $primaryAssignment = SpecialistAssignment::where('provider_id', $providerId)
                    ->where('is_primary', true)
                    ->whereIn('status', ['ACTIVE', 'active'])
                    ->first();

                if ($primaryAssignment) {
                    $specialistId = $primaryAssignment->specialist_id;
                    $validatedData['specialist_id'] = $specialistId;
                } else {
                    throw new \Exception('Independent specialist has no primary assignment configured.');
                }
            }
        }
        
        // 1. Resolve Specialist Assignment
        $assignment = null;
        if ($specialistId) {
            // First try a location-scoped assignment (specialist works at this specific branch)
            $assignmentQuery = SpecialistAssignment::where('specialist_id', $specialistId)
                ->where('provider_id', $providerId);
            
            if ($locationId) {
                $assignment = (clone $assignmentQuery)->where('location_id', $locationId)->first();
            }

            // Fall back to provider-level assignment (specialist has no fixed location on their record,
            // e.g. they work across branches or the assignment was created without a location_id)
            if (!$assignment) {
                $assignment = $assignmentQuery->whereIn('status', ['ACTIVE', 'active'])->first();
            }
            
            if (!$assignment) {
                throw new \Exception('Specialist is not assigned to this provider.');
            }
        }

        // 2. Validate Bookability & Availability
        $catalogQuery = new BranchCatalogQuery();
        $resolvedService = $catalogQuery->queryAllServices($providerId, $locationId)->where('services.id', $serviceId)->first();

        if (!$resolvedService) {
            throw new \Exception('Service not found in branch catalog.');
        }

        // Check double booking policy (provider-wide enforcement)
        $configService = app(\App\Services\BookingConfigurationService::class);
        $bookingPolicy = $configService->getPolicy($serviceId, $providerId, $locationId, 'booking_policy');
        $allowDoubleBooking = $bookingPolicy['allow_double_booking'] ?? false;

        // For independent specialists without location, skip location-specific validation
        if ($locationId) {
            $location = Location::find($locationId);
            if (!$location) {
                throw new \Exception('Location not found.');
            }

            $bookabilityEngine = new BookabilityEngine($location, $resolvedService);

            if (!$bookabilityEngine->isAvailableAtBranch()) {
                throw new \Exception('This service is not available at the selected branch.');
            }

            // internal_booking bypass: staff walk-ins don't need to advertise online booking
            // capability. This ONLY bypasses the customer-facing online-booking gate.
            // It does NOT bypass: provider ownership, location ownership, specialist assignment,
            // booking conflict checks, or payment/business rules.
            if (!$bookabilityEngine->acceptsOnlineBooking() && empty($validatedData['internal_booking'])) {
                throw new \Exception('Online booking is not available for this service at this branch.');
            }

            // Location-specific slot bookability check (skip for internal/walk-in bookings)
            // internal_booking bypass: staff walk-ins operate at an arbitrary time (customer
            // is physically present). Only the exact-slot-match constraint is bypassed.
            // Booking conflict detection still runs via the concurrency lock above.
            if ($assignment && empty($validatedData['internal_booking'])) {
                $bookabilityResult = $bookabilityEngine->isSlotBookable(
                    $this->availabilityEngine,
                    $assignment->id,
                    $date,
                    $time,
                    $serviceId
                );

                if (!$bookabilityResult['bookable']) {
                    throw new \Exception($bookabilityResult['reason']);
                }
            }
        }

        // 3. Acquire PostgreSQL advisory lock and perform conflict check inside transaction
        return DB::transaction(function () use (
            $validatedData,
            $providerId,
            $locationId,
            $serviceId,
            $date,
            $time,
            $specialistId,
            $assignment,
            $resolvedService
        ) {
            // Check double booking policy (provider-wide enforcement)
            $configService = app(\App\Services\BookingConfigurationService::class);
            $bookingPolicy = $configService->getPolicy($serviceId, $providerId, $locationId, 'booking_policy');
            $allowDoubleBooking = $bookingPolicy['allow_double_booking'] ?? false;

            // 3a. Acquire advisory lock for primary scheduling resource
            if ($specialistId) {
                $this->conflictService->acquireSpecialistLock($specialistId, $providerId);
            } else {
                $this->conflictService->acquireProviderLock($providerId);
            }

            // 3b. Calculate occupied interval for conflict detection
            $serviceStart = Carbon::parse("{$date} {$time}");
            $serviceEnd = $serviceStart->copy()->addMinutes($resolvedService->duration);
            $occupiedInterval = $this->conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $resolvedService
            );

            // 3c. Check for interval conflicts (unless double booking is allowed)
            if (!$allowDoubleBooking) {
                if ($specialistId && $locationId) {
                    $hasConflict = $this->conflictService->hasIntervalConflict(
                        $specialistId,
                        $locationId,
                        $occupiedInterval['start'],
                        $occupiedInterval['end']
                    );
                } elseif (!$specialistId) {
                    $hasConflict = $this->conflictService->hasProviderLevelConflict(
                        $providerId,
                        $locationId,
                        $occupiedInterval['start'],
                        $occupiedInterval['end']
                    );
                } else {
                    $hasConflict = false;
                }

                if ($hasConflict) {
                    throw new \Exception('That appointment time is no longer available. Please choose another time.');
                }
            }

            // 4. Allocate resources if service requires them
            $resourceAllocations = collect();
            try {
                $resourceAllocations = $this->resourceAllocator->allocateResources(
                    serviceId: $serviceId,
                    locationId: $locationId,
                    date: CarbonImmutable::parse($date),
                    startTime: $time,
                    endTime: $this->calculateEndTime($time, $serviceId),
                );

                // 4a. Acquire resource locks if resources were allocated
                if ($resourceAllocations->isNotEmpty()) {
                    $resourceIds = $resourceAllocations->pluck('resource_id')->toArray();
                    $this->conflictService->acquireResourceLocks($resourceIds);
                }
            } catch (\Exception $e) {
                // If resource allocation fails, throw exception
                throw new \Exception('Required resources are not available: ' . $e->getMessage());
            }

            // 5. Create Reservation (Locking the slot)
            // For external bookings, set to 'reserved' and lock for 10 mins for payment.
            // For internal walk-ins, bypass 'reserved' and just confirm them.
            if (empty($validatedData['internal_booking'])) {
                $validatedData['slot_locked_until'] = CarbonImmutable::now()->addMinutes(10);
                $validatedData['status'] = 'reserved';
            } else {
                $validatedData['status'] = 'confirmed';
            }

            $validatedData['resource_allocations'] = $resourceAllocations;

            // 6. Delegate to BookingService for customer resolution and creation
            // Use createBookingWithAccount for both account and non-account bookings to ensure consistent return structure
            if (!empty($validatedData['create_account']) || !empty($validatedData['customer_email'])) {
                return $this->bookingService->createBookingWithAccount($validatedData);
            } else {
                // For guest bookings (no email, no account), still use createBookingWithAccount
                // to ensure consistent return structure with customer data
                return $this->bookingService->createBookingWithAccount($validatedData);
            }
        });
    }

    private function calculateEndTime(string $startTime, string $serviceId): string
    {
        $service = Service::findOrFail($serviceId);
        $start = CarbonImmutable::parse($startTime);
        $end = $start->addMinutes($service->duration);
        return $end->format('H:i:s');
    }
}

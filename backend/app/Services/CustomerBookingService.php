<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Services\BookingConfigurationService;
use App\Services\BookingConflictService;
use App\Domain\Availability\DTOs\ProviderLocationContext;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerBookingService
{
    private CustomerSpecialistService $specialistService;
    private BookingConfigurationService $configService;
    private BookingConflictService $conflictService;
    private \App\Services\AvailabilityEngine $availabilityEngine;

    public function __construct(
        CustomerSpecialistService $specialistService,
        BookingConfigurationService $configService,
        BookingConflictService $conflictService,
        \App\Services\AvailabilityEngine $availabilityEngine
    ) {
        $this->specialistService = $specialistService;
        $this->configService = $configService;
        $this->conflictService = $conflictService;
        $this->availabilityEngine = $availabilityEngine;
    }

    /**
     * Invalidate availability cache after successful booking creation
     *
     * @param string $providerId
     * @param string|null $locationId
     * @param string $date
     * @param string|null $serviceId
     * @param string|null $specialistId
     * @return void
     */
    private function invalidateAvailabilityCache(
        string $providerId,
        ?string $locationId,
        string $date,
        ?string $serviceId,
        ?string $specialistId
    ): void {
        $ctx = new ProviderLocationContext($providerId, $locationId);
        $this->availabilityEngine->clearAvailabilityCacheForSession(
            $locationId,
            $date,
            $serviceId,
            $specialistId
        );
    }

    /**
     * Create a new booking for a customer
     */
    public function createBooking(array $data): Booking
    {
        $locationId = $data['location_id'] ?? $data['salon_id'] ?? null;
        $location = \App\Models\Location::query()->whereKey($locationId)->firstOrFail();

        $booking = DB::transaction(function () use ($data, $location) {
            $customer = Customer::find($data['customer_id']);
            $service = Service::withoutGlobalScope('salon')->find($data['service_id']);
            $staff = isset($data['staff_id']) ? Staff::withoutGlobalScope('salon')->find($data['staff_id']) : null;

            if (!$customer || !$service) {
                throw new \Exception('Invalid booking data');
            }

            // Acquire advisory lock for conflict protection
            $specialistId = $data['specialist_id'] ?? null;
            if ($specialistId) {
                $this->conflictService->acquireSpecialistLock($specialistId, $location->provider_id);
            } else {
                $this->conflictService->acquireProviderLock($location->provider_id);
            }

            // Calculate occupied interval for conflict detection
            $serviceStart = Carbon::parse("{$data['date']} {$data['time']}");
            $serviceEnd = $serviceStart->copy()->addMinutes($service->duration);
            $occupiedInterval = $this->conflictService->calculateOccupiedInterval(
                $serviceStart,
                $serviceEnd,
                $service
            );

            // Check for conflicts (handles both new and legacy bookings)
            if ($specialistId && $locationId) {
                $hasConflict = $this->conflictService->hasIntervalConflict(
                    $specialistId,
                    $locationId,
                    $occupiedInterval['start'],
                    $occupiedInterval['end']
                );
            } elseif (!$specialistId) {
                $hasConflict = $this->conflictService->hasProviderLevelConflict(
                    $location->provider_id,
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

            // Calculate price
            $price = $this->calculatePrice($service, $data);

            // Resolve staff_id from SpecialistAssignment when specialist_id is provided
            $staffId = $data['staff_id'] ?? null;
            if (!$staffId && isset($data['specialist_id'])) {
                $assignment = \App\Models\SpecialistAssignment::where('specialist_id', $data['specialist_id'])
                    ->where('provider_id', $location->provider_id)
                    ->where('location_id', $location->id)
                    ->whereNotNull('staff_id')
                    ->first();
                $staffId = $assignment?->staff_id;
            }

            // Validate staff_id if provided
            if ($staffId) {
                $staffExists = \App\Models\Staff::where('id', $staffId)
                    ->where('provider_id', $location->provider_id)
                    ->where('location_id', $location->id)
                    ->exists();
                if (!$staffExists) {
                    throw new \Exception('Invalid staff_id for this provider/location');
                }
            }

            // Determine initial status based on approval policy
            $initialStatus = $this->determineInitialBookingStatus(
                $location->provider_id,
                $location->id,
                $data['service_id']
            );

            // Create booking (legacy path - does NOT create BookingService records)
            $booking = Booking::create([
                'customer_id' => $data['customer_id'],
                'location_id' => $location->id,
                'provider_id' => $location->provider_id,
                'service_id' => $data['service_id'],
                'staff_id' => $staffId,
                'specialist_id' => $data['specialist_id'] ?? null,
                'date' => $data['date'],
                'time' => $data['time'],
                'status' => $initialStatus,
                'notes' => $data['notes'] ?? null,
            ]);

            // Ensure customer has provider relationship (domain integrity)
            $providerId = $location->provider_id;
            if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
                $customerService = app(\App\Services\CustomerService::class);
                $customerService->addProviderRelationship($customer, $providerId);
            }

            \Log::info('Booking created', [
                'booking_id' => $booking->id,
                'customer_id' => $data['customer_id'],
                'location_id' => $location->id,
                'service_id' => $data['service_id'],
                'staff_id' => $staffId,
                'specialist_id' => $data['specialist_id'] ?? null,
            ]);

            // Update customer visit count
            $this->incrementCustomerVisits($customer, $location);

            // Track customer-specialist relationship if specialist is assigned
            if ($booking->specialist_id) {
                $this->specialistService->trackRelationshipFromBooking($booking);
            }

            // Schedule reminders if booking is confirmed (Phase 1E integration)
            // This ensures customer portal bookings participate in the canonical reminder lifecycle
            if ($booking->status === 'confirmed') {
                app(\App\Services\ReminderService::class)->scheduleRemindersForBooking($booking);
            }

            return $booking->load(['service', 'staff', 'customer', 'specialist']);
        });

        // Invalidate availability cache after successful transaction commit
        $this->invalidateAvailabilityCache(
            $location->provider_id,
            $location->id,
            $data['date'],
            $data['service_id'],
            $data['specialist_id'] ?? null
        );

        return $booking;
    }

    /**
     * Validate booking data
     */
    public function validateBooking(array $data): array
    {
        $errors = [];

        // Check if date is in the past (allow today)
        if (Carbon::parse($data['date'])->startOfDay()->lt(Carbon::today())) {
            $errors[] = 'Booking date must be today or in the future';
        }

        // Check if time is valid (accept both HH:MM and HH:MM:SS formats)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $data['time'])) {
            $errors[] = 'Invalid time format';
        }

        // Check availability
        $availabilityService = new CustomerAvailabilityService();
        // Compatibility: accept both salon_id (legacy) and location_id (canonical)
        $locationId = $data['location_id'] ?? $data['salon_id'] ?? null;
        $availability = $availabilityService->getAvailableSlots(
            $locationId,
            $data['service_id'],
            $data['staff_id'] ?? null,
            $data['date']
        );

        $timeAvailable = collect($availability['slots'])->firstWhere('time', $data['time']);
        if (!$timeAvailable || !$timeAvailable['available']) {
            $errors[] = 'Selected time slot is not available';
        }

        return $errors;
    }

    /**
     * Calculate booking price
     */
    public function calculatePrice(Service $service, array $data): float
    {
        $price = $service->price;

        // Apply any discounts from offers
        if (isset($data['offer_id'])) {
            // TODO: Implement offer discount logic
        }

        return $price;
    }

    /**
     * Validate that a specialist_id exists in the specialists table.
     * NOTE: Do NOT pass a staff_id here. specialist_id → specialists.id exclusively.
     * Staff ID resolution happens via SpecialistAssignment.
     */
    private function getValidSpecialistId(?string $specialistId): ?string
    {
        if (!$specialistId) {
            return null;
        }

        // Only accepts a valid Specialist UUID (not a Staff UUID)
        return \App\Models\Specialist::where('id', $specialistId)->exists()
            ? $specialistId
            : null;
    }

    /**
     * Rebook from a previous booking
     */
    public function rebookFromPrevious(string $previousBookingId, array $newData): Booking
    {
        return DB::transaction(function () use ($previousBookingId, $newData) {
            $previousBooking = Booking::withoutGlobalScope('salon')->with(['service', 'staff'])->findOrFail($previousBookingId);

            // Copy service and staff from previous booking
            $bookingData = array_merge($newData, [
                'service_id' => $previousBooking->service_id,
                'staff_id' => $previousBooking->staff_id,
            ]);

            return $this->createBooking($bookingData);
        });
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking(string $bookingId): Booking
    {
        $booking = Booking::findOrFail($bookingId);

        if ($booking->status === 'cancelled') {
            throw new \Exception('Booking is already cancelled');
        }

        // Check cancellation policy
        $bookingDateTime = Carbon::parse($booking->date . ' ' . $booking->time);
        $now = Carbon::now();
        $hoursUntilBooking = $now->diffInHours($bookingDateTime, false);

        if ($hoursUntilBooking < 24) {
            throw new \Exception('Cannot cancel booking less than 24 hours before appointment');
        }

        // Use transitionStatus for single-authority status change
        return app(\App\Services\BookingService::class)->transitionStatus(
            $booking,
            'cancelled',
            'customer',
            $booking->customer_id,
            'Customer cancelled booking'
        );
    }

    /**
     * Reschedule a booking
     * Secure the NEW interval before cancelling the old one
     */
    public function rescheduleBooking(string $bookingId, array $newData): Booking
    {
        return DB::transaction(function () use ($bookingId, $newData) {
            $booking = Booking::findOrFail($bookingId);

            $newDate = $newData['date'];
            $newTime = $newData['time'];
            $newSpecialistId = $newData['specialist_id'] ?? $booking->specialist_id;
            $newLocationId = $newData['location_id'] ?? $booking->location_id;
            $providerId = $booking->provider_id;

            // Acquire lock for the NEW interval first (secure before releasing old)
            if ($newSpecialistId) {
                $this->conflictService->acquireSpecialistLock($newSpecialistId, $providerId);
            } else {
                $this->conflictService->acquireProviderLock($providerId);
            }

            // Calculate occupied interval for conflict detection
            $service = \App\Models\Service::find($booking->service_id);
            $newServiceStart = Carbon::parse("{$newDate} {$newTime}");
            $newServiceEnd = $newServiceStart->copy()->addMinutes($service->duration);
            $newOccupiedInterval = $this->conflictService->calculateOccupiedInterval(
                $newServiceStart,
                $newServiceEnd,
                $service
            );

            // Check for conflicts (handles both new and legacy bookings)
            if ($newSpecialistId && $newLocationId) {
                $hasConflict = $this->conflictService->hasIntervalConflict(
                    $newSpecialistId,
                    $newLocationId,
                    $newOccupiedInterval['start'],
                    $newOccupiedInterval['end'],
                    $booking->id // Exclude the original booking from conflict check
                );
            } elseif (!$newSpecialistId) {
                $hasConflict = $this->conflictService->hasProviderLevelConflict(
                    $providerId,
                    $newLocationId,
                    $newOccupiedInterval['start'],
                    $newOccupiedInterval['end'],
                    $booking->id
                );
            } else {
                $hasConflict = false;
            }

            if ($hasConflict) {
                throw new \Exception('The requested reschedule time is not available.');
            }

            // NEW interval is secure - now cancel old booking
            $this->cancelBooking($bookingId);

            // Create new booking with same details but new date/time
            $newBookingData = [
                'customer_id' => $booking->customer_id,
                'location_id' => $newLocationId,
                'service_id' => $booking->service_id,
                'staff_id' => $booking->staff_id,
                'specialist_id' => $newSpecialistId,
                'date' => $newDate,
                'time' => $newTime,
                'notes' => $newData['notes'] ?? $booking->notes,
            ];

            $newBooking = $this->createBooking($newBookingData);

            // Schedule reminders for new booking (Phase 1E integration)
            // createBooking() already handles this if status is confirmed,
            // but we call handleBookingRescheduled for explicit lifecycle clarity
            app(\App\Services\ReminderService::class)->handleBookingRescheduled($newBooking->id);

            return $newBooking;
        });
    }

    /**
     * Increment customer visit count.
     *
     * Uses a raw upsert so that:
     *   - First visit  → inserts a pivot row with visits = 1
     *   - Repeat visit → increments the existing visits column
     *
     * syncWithoutDetaching(DB::raw('visits + 1')) fails on INSERT because
     * PostgreSQL cannot reference the column value on a bare INSERT statement.
     */
    private function incrementCustomerVisits(Customer $customer, \App\Models\Location $location): void
    {
        $now = now();
        $id  = (string) \Illuminate\Support\Str::orderedUuid();
        $providerId = $location->provider_id;

        DB::statement(
            'INSERT INTO customer_providers (id, customer_id, provider_id, visits, created_at, updated_at, joined_at)
             VALUES (?, ?, ?, 1, ?, ?, ?)
             ON CONFLICT (customer_id, provider_id)
             DO UPDATE SET visits = customer_providers.visits + 1, updated_at = EXCLUDED.updated_at',
            [$id, $customer->id, $providerId, $now, $now, $now]
        );
    }

    /**
     * Get customer's upcoming bookings
     */
    public function getUpcomingBookings(string $customerId, ?string $salonId): array
    {
        $query = Booking::where('customer_id', $customerId)
            ->where('date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->with(['service', 'staff', 'specialist', 'location']);

        if ($salonId) {
            // Filter by location since Booking operates at a physical context
            $query->where('location_id', $salonId);
        }

        $bookings = $query->orderBy('date')
            ->orderBy('time')
            ->get();

        return $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'date' => $booking->date,
                'time' => $booking->time,
                'status' => $booking->status,
                'service' => [
                    'id' => $booking->service->id,
                    'name' => $booking->service->name,
                    'price' => $booking->service->price,
                    'duration' => $booking->service->duration,
                ],
                'staff' => $booking->staff ? [
                    'id' => $booking->staff->id,
                    'name' => $booking->staff->name,
                ] : null,
                'specialist' => $booking->specialist ? [
                    'id' => $booking->specialist->id,
                    'name' => $booking->specialist->name,
                ] : null,
                'salon' => $booking->location ? [
                    'id' => $booking->location->id,
                    'name' => $booking->location->name,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Get customer's booking history
     */
    public function getBookingHistory(string $customerId, ?string $salonId, int $limit = 20): array
    {
        $query = Booking::where('customer_id', $customerId)
            ->where('status', 'completed')
            ->with(['service', 'staff', 'specialist', 'location']);

        if ($salonId) {
            // Filter by location since Booking operates at a physical context
            $query->where('location_id', $salonId);
        }

        $query->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->limit($limit);

        $bookings = $query->get();

        return $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'date' => $booking->date,
                'time' => $booking->time,
                'status' => $booking->status,
                'service' => [
                    'id' => $booking->service->id,
                    'name' => $booking->service->name,
                    'price' => $booking->service->price,
                    'duration' => $booking->service->duration,
                ],
                'staff' => $booking->staff ? [
                    'id' => $booking->staff->id,
                    'name' => $booking->staff->name,
                ] : null,
                'specialist' => $booking->specialist ? [
                    'id' => $booking->specialist->id,
                    'name' => $booking->specialist->name,
                ] : null,
                'salon' => $booking->location ? [
                    'id' => $booking->location->id,
                    'name' => $booking->location->name,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Determine initial booking status based on approval policy
     * 
     * @param string $providerId
     * @param string $locationId
     * @param string $serviceId
     * @return string Initial status ('pending' or 'confirmed')
     */
    private function determineInitialBookingStatus(
        string $providerId,
        string $locationId,
        string $serviceId
    ): string {
        try {
            $approvalPolicy = $this->configService->getPolicy(
                $serviceId,
                $providerId,
                $locationId,
                'approval_policy'
            );

            $requireApproval = $approvalPolicy['require_approval'] ?? false;
            $approvalType = $approvalPolicy['approval_type'] ?? 'instant';

            // If approval is not required, or type is instant, confirm immediately
            if (!$requireApproval || $approvalType === 'instant') {
                return 'confirmed';
            }

            // Manual approval: start as pending
            if ($approvalType === 'manual') {
                return 'pending';
            }

            // Consultation: not implemented, treat as pending for now
            // TODO: Implement consultation-specific flow when required
            if ($approvalType === 'consultation') {
                return 'pending';
            }

            // Default to confirmed for unknown types
            return 'confirmed';
        } catch (\Exception $e) {
            // If config lookup fails, default to confirmed to avoid blocking bookings
            \Log::warning('Failed to determine approval policy, defaulting to confirmed', [
                'error' => $e->getMessage(),
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'location_id' => $locationId,
            ]);
            return 'confirmed';
        }
    }
}

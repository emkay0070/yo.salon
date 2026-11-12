<?php

namespace App\Services;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PortalAccount;
use App\Models\BookingService as BookingServiceModel;
use App\Models\ResourceAllocation;
use App\Services\Payments\BookingPaymentService;
use App\Services\BookingConflictService;
use App\Domain\Availability\DTOs\ProviderLocationContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * BookingService
 * 
 * Responsible for creating bookings and orchestrating the customer resolution flow.
 * This is where Journey 4 (Website booking + "Create an account") belongs.
 * 
 * Principle: Booking is a business event that creates or resolves customers.
 * Authentication (portal account creation) is optional and secondary to the booking.
 */
class BookingService
{
    private CustomerService $customerService;
    private PortalAuthService $portalAuthService;
    private BookingPaymentService $bookingPaymentService;
    private CustomerSpecialistService $customerSpecialistService;
    private NotificationService $notificationService;
    private ReminderService $reminderService;
    private BookingConfigurationService $configService;
    private BookingConflictService $conflictService;
    private \App\Services\AvailabilityEngine $availabilityEngine;

    public function __construct(
        CustomerService $customerService,
        PortalAuthService $portalAuthService,
        BookingPaymentService $bookingPaymentService,
        CustomerSpecialistService $customerSpecialistService,
        NotificationService $notificationService,
        ReminderService $reminderService,
        BookingConfigurationService $configService,
        BookingConflictService $conflictService,
        \App\Services\AvailabilityEngine $availabilityEngine
    ) {
        $this->customerService = $customerService;
        $this->portalAuthService = $portalAuthService;
        $this->bookingPaymentService = $bookingPaymentService;
        $this->customerSpecialistService = $customerSpecialistService;
        $this->notificationService = $notificationService;
        $this->reminderService = $reminderService;
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
     * Validate booking time against effective schedule
     * 
     * @param string $providerId
     * @param string|null $locationId
     * @param string|null $specialistId
     * @param string $date
     * @param string $time
     * @param string|null $serviceId
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateBookingTime(
        string $providerId,
        ?string $locationId,
        ?string $specialistId,
        string $date,
        string $time,
        ?string $serviceId = null
    ): void {
        $availabilityEngine = app(AvailabilityEngine::class);
        
        // For independent_specialist providers without specialist_id, auto-resolve primary assignment
        $provider = \App\Models\Provider::find($providerId);
        if ($provider && $provider->type === \App\Models\Provider::TYPE_SPECIALIST && !$specialistId) {
            $primaryAssignment = \App\Models\SpecialistAssignment::where('provider_id', $providerId)
                ->where('is_primary', true)
                ->whereIn('status', ['ACTIVE', 'active'])
                ->first();
            
            if ($primaryAssignment) {
                $specialistId = $primaryAssignment->specialist_id;
            }
        }
        
        // Get availability for the requested date
        $availability = $availabilityEngine->getAvailableSlots(
            $providerId,
            $locationId,
            $date,
            $serviceId,
            $specialistId
        );
        
        // Check if the provider/location is closed
        if ($availability->isClosed) {
            throw new \InvalidArgumentException('The business is closed on this date.');
        }
        
        // Check if the requested time is within the operating hours
        $schedule = $availability->schedule;
        if ($schedule && $schedule->openTime && $schedule->closeTime) {
            $requestedTime = Carbon::parse($time);
            $openTime = Carbon::parse($schedule->openTime);
            $closeTime = Carbon::parse($schedule->closeTime);
            
            if ($requestedTime->lt($openTime) || $requestedTime->gte($closeTime)) {
                throw new \InvalidArgumentException(
                    "Requested time {$time} is outside operating hours ({$schedule->openTime} - {$schedule->closeTime})."
                );
            }
        }
        
        // Check if the requested time slot is actually available
        // Normalize time comparison to handle different formats (HH:MM vs HH:MM:SS)
        $normalizedTime = substr($time, 0, 5); // Extract HH:MM portion
        
        $requestedSlot = $availability->slots->first(function ($slot) use ($normalizedTime) {
            // Handle different slot formats: 'start' or 'time' key
            $slotTime = $slot['start'] ?? $slot['time'] ?? null;
            $normalizedSlotTime = substr($slotTime, 0, 5); // Extract HH:MM portion
            return $normalizedSlotTime === $normalizedTime;
        });
        
        if (!$requestedSlot) {
            throw new \InvalidArgumentException("The requested time slot {$time} is not available.");
        }
        
        // Final safety check: verify no existing booking at this specialist/date/time
        // This handles race conditions and ensures data integrity
        $existingBooking = Booking::where('provider_id', $providerId)
            ->when($specialistId, fn($q) => $q->where('specialist_id', $specialistId))
            ->where('date', $date)
            ->where('time', $time)
            ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
            ->where(function ($q) {
                // Lifecycle-aware check for reserved status
                $q->where('status', '!=', 'reserved')
                  ->orWhereNull('slot_locked_until')
                  ->orWhere('slot_locked_until', '>', now());
            })
            ->first();

        if ($existingBooking) {
            throw new \InvalidArgumentException("This time slot is already booked.");
        }
    }

    /**
     * Create a guest booking (Journey 3)
     * 
     * Flow: Booking → Customer (created if needed) → No portal account
     * 
     * @param array $data Booking data including customer details
     * @return Booking
     */
    public function createGuestBooking(array $data): Booking
    {
        $providerId = $data['provider_id'];
        $locationId = $data['location_id'] ?? null;
        $specialistId = $data['specialist_id'] ?? null;
        $date = $data['date'];
        $time = $data['time'];
        $serviceId = is_array($data['service_id']) ? $data['service_id'][0] : $data['service_id'];

        $booking = DB::transaction(function () use ($data, $providerId, $locationId, $specialistId, $date, $time, $serviceId) {

            // Acquire advisory lock if not already held (when called directly, not from BookingOrchestrator)
            // BookingOrchestrator already acquires the lock before calling this method
            if (!isset($data['_lock_acquired'])) {
                if ($specialistId) {
                    $this->conflictService->acquireSpecialistLock($specialistId, $providerId);
                } else {
                    $this->conflictService->acquireProviderLock($providerId);
                }
            }

            // Validate booking time against effective schedule
            // Skip for internal (walk-in) bookings — staff bypasses slot availability rules
            if (empty($data['internal_booking'])) {
                $this->validateBookingTime($providerId, $locationId, $specialistId, $date, $time, $serviceId);
            }

            // Resolve or create customer (business event)
            if (!empty($data['customer_id'])) {
                // Existing CRM customer — look up directly
                $customer = Customer::findOrFail($data['customer_id']);
            } elseif (!empty($data['customer_phone'])) {
                // Phone provided — find-or-create a real CRM customer
                $customer = Customer::firstOrCreate(
                    ['phone' => $data['customer_phone']],
                    [
                        'name'  => $data['customer_name'] ?? 'Customer',
                        'email' => $data['customer_email'] ?? null,
                    ]
                );
            } else {
                // No identity provided — create an isolated, provider-scoped guest.
                // This is the correct path for anonymous walk-ins.
                // Guest records do NOT contaminate the CRM phone index.
                $customer = \App\Models\Customer::createGuest(
                    $providerId,
                    $data['customer_name'] ?? 'Walk-in Guest'
                );
            }

            // Ensure customer has provider relationship (domain integrity)
            if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
                $this->customerService->addProviderRelationship($customer, $providerId);
            }

            // Determine initial status based on approval policy
            $initialStatus = $this->determineInitialBookingStatus($providerId, $locationId, $serviceId, false);

            // Create booking
            $booking = Booking::create([
                'location_id' => $locationId,
                'provider_id' => $providerId,
                'customer_id' => $customer->id,
                'specialist_id' => $specialistId,
                'service_id' => $serviceId, // scalar UUID, resolved from array on L158
                'date' => $date,
                'time' => $time,
                'status' => $data['status'] ?? $initialStatus,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'notes' => $data['notes'] ?? null,
                'slot_locked_until' => $data['slot_locked_until'] ?? null,
            ]);

            // Create BookingService records with timing
            $serviceIds = is_array($data['service_id']) ? $data['service_id'] : [$data['service_id']];
            
            // Get timezone from location or provider
            $location = $locationId ? \App\Models\Location::find($locationId) : null;
            $provider = \App\Models\Provider::find($providerId);
            $timezone = $location?->timezone ?? $provider?->timezone ?? 'Africa/Kampala';
            
            // Normalize time to HH:MM format (strip seconds if present)
            $normalizedTime = substr($time, 0, 5);
            $startTime = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$normalizedTime}", $timezone);
            foreach ($serviceIds as $index => $serviceId) {
                $service = \App\Models\Service::find($serviceId);
                $endTime = $startTime->copy()->addMinutes($service->duration);

                $bookingService = BookingServiceModel::create([
                    'booking_id' => $booking->id,
                    'service_id' => $serviceId,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);

                // Create resource allocations if provided
                if (isset($data['resource_allocations'])) {
                    foreach ($data['resource_allocations'] as $allocation) {
                        ResourceAllocation::create([
                            'booking_id' => $booking->id,
                            'booking_service_id' => $bookingService->id,
                            'resource_id' => $allocation['resource_id'],
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                        ]);
                    }
                }

                // Advance start time for next service if multiple
                $startTime = $endTime;
            }

            // Track customer-specialist relationship if specialist is assigned
            if ($booking->specialist_id) {
                $this->customerSpecialistService->trackRelationshipFromBooking($booking);
            }

            return $booking->load(['location', 'customer', 'specialist', 'service']);
        });

        // Invalidate availability cache after successful transaction commit
        $this->invalidateAvailabilityCache($providerId, $locationId, $date, $serviceId, $specialistId);

        return $booking;
    }

    /**
     * Create booking with portal account (Journey 4)
     * 
     * Flow: Booking → Customer → Portal Account → Payment (if required) → Confirmed
     * 
     * @param array $data Booking data including customer and account details
     * @return array ['booking' => Booking, 'customer' => Customer, 'portal_account' => PortalAccount|null, 'payment' => array|null]
     */
    public function createBookingWithAccount(array $data): array
    {
        $providerId = $data['provider_id'];
        $locationId = $data['location_id'] ?? null;
        $specialistId = $data['specialist_id'] ?? null;
        $date = $data['date'];
        $time = $data['time'];
        $serviceId = is_array($data['service_id']) ? $data['service_id'][0] : $data['service_id'];

        $result = DB::transaction(function () use ($data, $providerId, $locationId, $specialistId, $date, $time, $serviceId) {

            // Acquire advisory lock if not already held (when called directly, not from BookingOrchestrator)
            // BookingOrchestrator already acquires the lock before calling this method
            if (!isset($data['_lock_acquired'])) {
                if ($specialistId) {
                    $this->conflictService->acquireSpecialistLock($specialistId, $providerId);
                } else {
                    $this->conflictService->acquireProviderLock($providerId);
                }
            }

            // Validate booking time against effective schedule
            // Skip for internal (walk-in) bookings — staff bypasses slot availability rules
            if (empty($data['internal_booking'])) {
                $this->validateBookingTime($providerId, $locationId, $specialistId, $date, $time, $serviceId);
            }

            // Resolve or create customer (business event)
            if (!empty($data['customer_id'])) {
                $customer = Customer::findOrFail($data['customer_id']);
            } else {
                $customer = Customer::firstOrCreate(
                    ['phone' => $data['customer_phone']],
                    [
                        'name' => $data['customer_name'],
                        'email' => $data['customer_email'] ?? null,
                    ]
                );
            }

            // Ensure customer has provider relationship (domain integrity)
            if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
                $this->customerService->addProviderRelationship($customer, $providerId);
            }

            // Get provider and service price securely
            $provider = \App\Models\Provider::find($providerId);
            $serviceId = is_array($data['service_id']) ? $data['service_id'][0] : $data['service_id'];
            
            $catalogQuery = new \App\Domain\Catalog\Queries\BranchCatalogQuery();
            $resolvedService = $catalogQuery->queryAllServices($providerId, $locationId)->where('services.id', $serviceId)->first();
            $servicePrice = $resolvedService ? $resolvedService->price : 0;
            
            // Generate PaymentInstruction snapshot
            $paymentRulesEngine = app(\App\Services\PaymentRulesEngine::class);
            $paymentInstruction = $paymentRulesEngine->generatePaymentInstructions($serviceId, $providerId, $locationId, $servicePrice);

            // Determine deposit requirement and payment status
            $requiresDeposit = $paymentInstruction->customerAction === 'pay_now';
            $paymentStatus = $requiresDeposit ? 'pending' : 'not_required';
            
            // Determine initial status based on approval policy (payment takes precedence)
            if ($requiresDeposit) {
                $bookingStatus = 'pending_payment';
            } else {
                $bookingStatus = $this->determineInitialBookingStatus($providerId, $locationId, $serviceId, false);
            }

            $paymentSnapshotArray = (array) $paymentInstruction;
            if ($requiresDeposit && !empty($data['payment_method_id'])) {
                $paymentSnapshotArray['selected_method_id'] = $data['payment_method_id'];
            }

            // Create booking
            $booking = Booking::create([
                'location_id' => $locationId,
                'provider_id' => $providerId,
                'customer_id' => $customer->id,
                'specialist_id' => $data['specialist_id'] ?? null,
                'service_id' => $serviceId, // Keep single service_id for backward compatibility
                'date' => $date,
                'time' => $time,
                'status' => $bookingStatus,
                
                // Payment Architecture v1 Snapshot
                'payment_strategy' => $paymentInstruction->strategy,
                'deposit_required' => $requiresDeposit,
                'amount_due' => $paymentInstruction->amountDueNow,
                'amount_paid' => 0,
                'amount_remaining' => $paymentInstruction->amountRemaining,
                'payment_status' => $paymentStatus,
                'payment_expires_at' => $data['slot_locked_until'] ?? null,
                'payment_snapshot' => $paymentSnapshotArray,
                
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'notes' => $data['notes'] ?? null,
                'slot_locked_until' => $data['slot_locked_until'] ?? null,
            ]);

            // Create BookingService records with timing
            $serviceIds = is_array($data['service_id']) ? $data['service_id'] : [$data['service_id']];
            
            // Get timezone from location or provider
            $location = $locationId ? \App\Models\Location::find($locationId) : null;
            $timezone = $location?->timezone ?? $provider?->timezone ?? 'Africa/Kampala';
            
            // Normalize time to HH:MM format (strip seconds if present)
            $normalizedTime = substr($time, 0, 5);
            $startTime = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$normalizedTime}", $timezone);
            foreach ($serviceIds as $index => $serviceId) {
                $service = \App\Models\Service::find($serviceId);
                $endTime = $startTime->copy()->addMinutes($service->duration);

                $bookingService = BookingServiceModel::create([
                    'booking_id' => $booking->id,
                    'service_id' => $serviceId,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);

                // Create resource allocations if provided
                if (isset($data['resource_allocations'])) {
                    foreach ($data['resource_allocations'] as $allocation) {
                        ResourceAllocation::create([
                            'booking_id' => $booking->id,
                            'booking_service_id' => $bookingService->id,
                            'resource_id' => $allocation['resource_id'],
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                        ]);
                    }
                }

                // Advance start time for next service if multiple
                $startTime = $endTime;
            }

            // Track customer-specialist relationship if specialist is assigned
            if ($booking->specialist_id) {
                $this->customerSpecialistService->trackRelationshipFromBooking($booking);
            }

            // Dispatch BookingCreated event
            $serviceNames = $booking->services()->pluck('name')->join(', ');
            Event::dispatch(new BookingCreated(
                $booking->id,
                $customer->id,
                $providerId,
                $customer->name,
                $serviceNames,
                $booking->date,
                $booking->time
            ));

            // Create portal account if requested and customer doesn't have one
            $portalAccount = null;
            if (!empty($data['create_account']) && !$customer->hasPortalAccount()) {
                $portalAccount = PortalAccount::create([
                    'customer_id' => $customer->id,
                    'email' => $data['account_email'] ?? $data['customer_email'],
                    'password' => Hash::make($data['account_password']),
                ]);
            }

            // Initialize payment only if deposit is required and payment method is provided
            $payment = null;
            if ($requiresDeposit && !empty($data['payment_method_id'])) {
                try {
                    $payment = $this->bookingPaymentService->initializeBookingPayment(
                        $booking->id,
                        $data['payment_method_id'],
                        $data['customer_email'] ?? $customer->email,
                        $data['customer_name'],
                        $data['customer_phone']
                    );
                } catch (\Exception $e) {
                    // If payment initialization fails, still return the booking
                    // but mark it as failed payment
                    $booking->update(['payment_status' => 'failed']);
                }
            }

            return [
                'booking' => $booking->load(['location', 'customer', 'specialist', 'service']),
                'customer' => $customer,
                'portal_account' => $portalAccount,
                'is_new_customer' => $customer->wasRecentlyCreated,
                'payment' => $payment,
                'requires_deposit' => $requiresDeposit,
            ];
        });

        // Invalidate availability cache after successful transaction commit
        $this->invalidateAvailabilityCache($providerId, $locationId, $date, $serviceId, $specialistId);

        return $result;
    }

    /**
     * Create booking for existing portal account
     * 
     * Flow: Portal Account (authenticated) → Customer → Booking
     * 
     * @param array $data Booking data
     * @param PortalAccount $portalAccount Authenticated portal account
     * @return Booking
     */
    public function createBookingForPortalUser(array $data, PortalAccount $portalAccount): Booking
    {
        $providerId = $data['provider_id'];
        $locationId = $data['location_id'] ?? null;
        $specialistId = $data['specialist_id'] ?? null;
        $date = $data['date'];
        $time = $data['time'];
        $serviceId = is_array($data['service_id']) ? $data['service_id'][0] : $data['service_id'];

        $booking = DB::transaction(function () use ($data, $portalAccount, $providerId, $locationId, $specialistId, $date, $time, $serviceId) {
            $customer = $portalAccount->customer;
            
            // Validate booking time against effective schedule
            $this->validateBookingTime($providerId, $locationId, $specialistId, $date, $time, $serviceId);

            // Ensure customer has relationship with this business
            if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
                $this->customerService->addProviderRelationship($customer, $providerId);
            }

            // Determine initial status based on approval policy
            $initialStatus = $this->determineInitialBookingStatus($providerId, $locationId, $serviceId, false);

            // Create booking
            $booking = Booking::create([
                'location_id' => $locationId,
                'provider_id' => $providerId,
                'customer_id' => $customer->id,
                'specialist_id' => $specialistId,
                'service_id' => $serviceId, // scalar UUID, resolved from array on L446
                'date' => $date,
                'time' => $time,
                'status' => $data['status'] ?? $initialStatus,
                'notes' => $data['notes'] ?? null,
            ]);

            // Track customer-specialist relationship if specialist is assigned
            if ($booking->specialist_id) {
                $this->customerSpecialistService->trackRelationshipFromBooking($booking);
            }

            return $booking->load(['location', 'customer', 'specialist', 'service']);
        });

        // Invalidate availability cache after successful transaction commit
        $this->invalidateAvailabilityCache($providerId, $locationId, $date, $serviceId, $specialistId);

        return $booking;
    }

    /**
     * Update booking
     * 
     * @param Booking $booking
     * @param array $data
     * @return Booking
     */
    public function updateBooking(Booking $booking, array $data): Booking
    {
        $booking->update($data);
        return $booking->load(['location', 'customer', 'specialist', 'service']);
    }

    /**
     * Transition booking status (State Machine)
     * 
     * This is the single authoritative method for all booking status changes.
     * It validates transitions, records timestamps, creates activity logs,
     * and handles special-case logic (like visit count increment).
     * 
     * @param Booking $booking
     * @param string $newStatus
     * @param string|null $actorType Type of actor performing the transition (customer, staff, system)
     * @param string|null $actorId ID of the actor performing the transition
     * @param string|null $reason Optional reason for the transition
     * @return Booking
     * @throws ValidationException
     */
    public function transitionStatus(
        Booking $booking, 
        string $newStatus,
        ?string $actorType = 'system',
        ?string $actorId = null,
        ?string $reason = null
    ): Booking {
        // ── Canonical state machine for the full booking lifecycle ────────────────
        //
        // Reservation layer  (managed by booking system):
        //   pending         → confirmed | cancelled
        //   pending_payment → confirmed | cancelled
        //   reserved        → pending | pending_payment | confirmed | cancelled
        //
        // Operational floor layer (managed by receptionist / specialist):
        //   confirmed  → arrived | cancelled | no_show
        //   arrived    → waiting | in_progress | cancelled | no_show
        //   waiting    → in_progress | cancelled | no_show
        //   in_progress → completed
        //
        // Terminal states:
        //   completed | cancelled | no_show → (no further transitions)
        //
        $allowedTransitions = [
            'pending'         => ['confirmed', 'cancelled'],
            'pending_payment' => ['confirmed', 'cancelled'],
            'reserved'        => ['pending', 'pending_payment', 'confirmed', 'cancelled'],
            'confirmed'       => ['arrived', 'in_progress', 'cancelled', 'no_show', 'rescheduled', 'completed'],
            'arrived'         => ['waiting', 'in_progress', 'cancelled', 'no_show'],
            'waiting'         => ['in_progress', 'cancelled', 'no_show'],
            'in_progress'     => ['completed'],
            'completed'       => [],
            'cancelled'       => [],
            'no_show'         => [],
            'rescheduled'     => [],
        ];

        $currentStatus = $booking->status;

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            throw ValidationException::withMessages([
                'status' => "Invalid state transition from {$currentStatus} to {$newStatus}",
            ]);
        }

        // For operational floor states, record the timestamp atomically with the status change
        $updatePayload = ['status' => $newStatus];

        if ($newStatus === 'arrived') {
            $updatePayload['arrived_at'] = now();
        } elseif ($newStatus === 'waiting') {
            $updatePayload['waiting_since'] = now();
        }

        // Special case: completion - increment customer visit count
        if ($newStatus === 'completed' && $booking->customer) {
            $this->customerService->incrementVisit($booking->customer, $booking->provider_id);
        }

        $previousStatus = $booking->status;
        $booking->update($updatePayload);

        // Schedule reminders when booking is confirmed
        if ($newStatus === 'confirmed') {
            $this->reminderService->scheduleRemindersForBooking($booking);
        }

        // Cancel reminders when booking is cancelled
        if ($newStatus === 'cancelled') {
            $this->reminderService->cancelRemindersForBooking($booking->id);
        }

        // Create activity record for audit trail
        \App\Models\BookingActivity::create([
            'booking_id' => $booking->id,
            'type' => 'status_changed',
            'title' => "Status changed from {$previousStatus} to {$newStatus}",
            'description' => $reason ?? "Booking status transitioned from {$previousStatus} to {$newStatus}",
            'data' => [
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ],
            'actor_type' => $actorType,
            'actor_id' => $actorId,
        ]);

        return $booking->fresh(['location', 'customer', 'specialist', 'service']);
    }

    /**
     * Resolve a booking with a formal resolution record.
     *
     * This method creates a BookingResolution record, transitions the booking status,
     * sets operational_state to resolved, and creates appropriate activity logs.
     * All operations are performed atomically within a transaction.
     *
     * @param Booking $booking
     * @param string $outcome Resolution outcome (NO_SHOW, CANCELLED, RESCHEDULED, COMPLETED_LATE, CUSTOMER_ARRIVED)
     * @param string $reasonCode Reason code (CUSTOMER_DID_NOT_ARRIVE, CUSTOMER_CANCELLED, etc.)
     * @param string|null $actorType Type of actor performing the resolution
     * @param string|null $actorId ID of the actor performing the resolution
     * @param array $options Additional options:
     *   - notes: Optional notes for the resolution
     *   - follow_up_required: Whether follow-up is required
     *   - follow_up_at: When follow-up should occur
     *   - follow_up_notes: Notes for follow-up
     *   - new_booking_data: For RESCHEDULED outcome, data for the new booking
     * @return Booking
     * @throws ValidationException
     */
    public function resolveBooking(
        Booking $booking,
        string $outcome,
        string $reasonCode,
        ?string $actorType = 'staff',
        ?string $actorId = null,
        array $options = []
    ): Booking {
        // Validate outcome
        $validOutcomes = ['NO_SHOW', 'CANCELLED', 'RESCHEDULED', 'COMPLETED_LATE', 'CUSTOMER_ARRIVED'];
        if (!in_array($outcome, $validOutcomes)) {
            throw ValidationException::withMessages([
                'outcome' => "Invalid outcome. Must be one of: " . implode(', ', $validOutcomes),
            ]);
        }

        // Validate reason code
        $validReasonCodes = [
            'CUSTOMER_DID_NOT_ARRIVE',
            'CUSTOMER_CANCELLED',
            'CUSTOMER_REQUESTED_RESCHEDULE',
            'CUSTOMER_COULD_NOT_BE_REACHED',
            'CUSTOMER_ARRIVED_LATE',
            'PROVIDER_UNAVAILABLE',
            'SPECIALIST_UNAVAILABLE',
            'LOCATION_CLOSED',
            'TECHNICAL_ISSUE',
            'OTHER',
        ];
        if (!in_array($reasonCode, $validReasonCodes)) {
            throw ValidationException::withMessages([
                'reason_code' => "Invalid reason code. Must be one of: " . implode(', ', $validReasonCodes),
            ]);
        }

        // Check for duplicate resolution (idempotency) - check this BEFORE terminal state
        if ($booking->resolution()->exists()) {
            throw ValidationException::withMessages([
                'booking' => 'This booking already has a resolution.',
            ]);
        }

        // Validate resolution legality based on current booking state
        $this->validateResolutionLegality($booking, $outcome);

        // Map outcome to booking status
        $targetStatus = $this->mapOutcomeToStatus($outcome);

        // Handle rescheduling specially - requires creating a new booking
        if ($outcome === 'RESCHEDULED') {
            return $this->resolveAsRescheduled($booking, $reasonCode, $actorType, $actorId, $options);
        }

        // Handle CUSTOMER_ARRIVED specially - preserve existing arrival semantics
        if ($outcome === 'CUSTOMER_ARRIVED') {
            return $this->resolveAsLateArrival($booking, $reasonCode, $actorType, $actorId, $options);
        }

        // Standard resolution flow
        return DB::transaction(function () use ($booking, $outcome, $reasonCode, $actorType, $actorId, $targetStatus, $options) {
            // Transition booking status using the authoritative method
            $this->transitionStatus(
                $booking,
                $targetStatus,
                $actorType,
                $actorId,
                $options['notes'] ?? "Booking resolved as {$outcome}"
            );

            // Set operational state to resolved
            $booking->update(['operational_state' => 'resolved']);

            // Create resolution record
            $resolution = \App\Models\BookingResolution::create([
                'booking_id' => $booking->id,
                'outcome' => $outcome,
                'reason_code' => $reasonCode,
                'notes' => $options['notes'] ?? null,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'resolved_at' => now(),
                'follow_up_required' => $options['follow_up_required'] ?? false,
                'follow_up_at' => $options['follow_up_at'] ?? null,
                'follow_up_status' => ($options['follow_up_required'] ?? false) ? 'PENDING' : null,
                'follow_up_notes' => $options['follow_up_notes'] ?? null,
            ]);

            // Create resolution activity
            $activityType = $this->getActivityTypeForResolution($outcome);
            $activityTitle = $this->getActivityTitleForResolution($outcome, $reasonCode);

            \App\Models\BookingActivity::create([
                'booking_id' => $booking->id,
                'type' => $activityType,
                'title' => $activityTitle,
                'description' => $options['notes'] ?? "Booking resolved as {$outcome} ({$reasonCode})",
                'data' => [
                    'outcome' => $outcome,
                    'reason_code' => $reasonCode,
                    'resolution_id' => $resolution->id,
                ],
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);

            return $booking->fresh(['location', 'customer', 'specialist', 'service', 'resolution']);
        });
    }

    /**
     * Validate that a resolution is legal for the current booking state.
     */
    private function validateResolutionLegality(Booking $booking, string $outcome): void
    {
        // Terminal business states cannot be resolved
        if (in_array($booking->status, ['completed', 'cancelled', 'no_show'])) {
            throw ValidationException::withMessages([
                'booking' => "Booking is already in a terminal state ({$booking->status}) and cannot be resolved.",
            ]);
        }

        // needs_resolution state allows all outcomes
        if ($booking->operational_state === 'needs_resolution') {
            return;
        }

        // For other operational states, only certain outcomes are allowed
        $allowedByOperationalState = [
            'upcoming' => ['CANCELLED', 'RESCHEDULED'],
            'due_soon' => ['CANCELLED', 'RESCHEDULED'],
            'grace_period' => ['CANCELLED', 'RESCHEDULED', 'CUSTOMER_ARRIVED'],
        ];

        $currentState = $booking->operational_state;
        if (isset($allowedByOperationalState[$currentState]) && !in_array($outcome, $allowedByOperationalState[$currentState])) {
            throw ValidationException::withMessages([
                'booking' => "Outcome {$outcome} is not allowed for operational state {$currentState}.",
            ]);
        }

        // Operational floor states (arrived, waiting, in_progress) cannot be resolved
        if (in_array($booking->status, ['arrived', 'waiting', 'in_progress'])) {
            throw ValidationException::withMessages([
                'booking' => "Booking is in operational floor state ({$booking->status}) and cannot be resolved.",
            ]);
        }
    }

    /**
     * Map resolution outcome to booking status.
     */
    private function mapOutcomeToStatus(string $outcome): string
    {
        return match ($outcome) {
            'NO_SHOW' => 'no_show',
            'CANCELLED' => 'cancelled',
            'RESCHEDULED' => 'rescheduled',
            'COMPLETED_LATE' => 'completed',
            'CUSTOMER_ARRIVED' => 'arrived',
        };
    }

    /**
     * Handle RESCHEDULED outcome - creates a new booking and links it.
     */
    private function resolveAsRescheduled(
        Booking $booking,
        string $reasonCode,
        ?string $actorType,
        ?string $actorId,
        array $options
    ): Booking {
        if (!isset($options['new_booking_data'])) {
            throw ValidationException::withMessages([
                'new_booking_data' => 'new_booking_data is required for RESCHEDULED outcome.',
            ]);
        }

        $newBookingData = $options['new_booking_data'];
        $newDate = $newBookingData['date'];
        $newTime = $newBookingData['time'];
        $newSpecialistId = $newBookingData['specialist_id'] ?? $booking->specialist_id;
        $newLocationId = $newBookingData['location_id'] ?? $booking->location_id;

        return DB::transaction(function () use (
            $booking,
            $reasonCode,
            $actorType,
            $actorId,
            $options,
            $newBookingData,
            $newDate,
            $newTime,
            $newSpecialistId,
            $newLocationId
        ) {
            // Acquire lock for the NEW interval first (secure before releasing old)
            if ($newSpecialistId) {
                $this->conflictService->acquireSpecialistLock($newSpecialistId, $booking->provider_id);
            } else {
                $this->conflictService->acquireProviderLock($booking->provider_id);
            }

            // Check for conflicts at the NEW interval
            $service = \App\Models\Service::find($booking->service_id);
            $newServiceStart = Carbon::parse("{$newDate} {$newTime}");
            $newServiceEnd = $newServiceStart->copy()->addMinutes($service->duration);
            $newOccupiedInterval = $this->conflictService->calculateOccupiedInterval(
                $newServiceStart,
                $newServiceEnd,
                $service
            );

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
                    $booking->provider_id,
                    $newLocationId,
                    $newOccupiedInterval['start'],
                    $newOccupiedInterval['end'],
                    $booking->id
                );
            } else {
                $hasConflict = false;
            }

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'new_booking_data' => 'The requested reschedule time is not available.',
                ]);
            }

            // NEW interval is secure - now transition original booking
            $this->transitionStatus(
                $booking,
                'rescheduled',
                $actorType,
                $actorId,
                'Booking rescheduled'
            );

            // Set operational state to resolved
            $booking->update(['operational_state' => 'resolved']);

            // Create resolution record for original booking
            $resolution = \App\Models\BookingResolution::create([
                'booking_id' => $booking->id,
                'outcome' => 'RESCHEDULED',
                'reason_code' => $reasonCode,
                'notes' => $options['notes'] ?? null,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'resolved_at' => now(),
                'follow_up_required' => false,
            ]);

            // Create new booking using existing booking creation flow
            // Use internal_booking bypass since conflict check already performed above
            $newBooking = $this->createGuestBooking(array_merge($newBookingData, [
                'provider_id' => $booking->provider_id,
                'location_id' => $newLocationId,
                'customer_id' => $booking->customer_id,
                'specialist_id' => $newSpecialistId,
                'service_id' => $booking->service_id,
                '_lock_acquired' => true, // Lock already acquired above
                'internal_booking' => true, // Bypass availability validation - conflict check already done
            ]));

            // Link new booking to original
            $newBooking->update(['rescheduled_from_booking_id' => $booking->id]);

            // Create activity for original booking
            \App\Models\BookingActivity::create([
                'booking_id' => $booking->id,
                'type' => 'BOOKING_RESCHEDULED',
                'title' => 'Booking rescheduled',
                'description' => "Booking rescheduled to {$newBooking->date} at {$newBooking->time}",
                'data' => [
                    'outcome' => 'RESCHEDULED',
                    'reason_code' => $reasonCode,
                    'resolution_id' => $resolution->id,
                    'new_booking_id' => $newBooking->id,
                    'new_booking_date' => $newBooking->date,
                    'new_booking_time' => $newBooking->time,
                ],
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);

            return $booking->fresh(['location', 'customer', 'specialist', 'service', 'resolution']);
        });
    }

    /**
     * Handle CUSTOMER_ARRIVED outcome - preserves existing arrival semantics.
     */
    private function resolveAsLateArrival(
        Booking $booking,
        string $reasonCode,
        ?string $actorType,
        ?string $actorId,
        array $options
    ): Booking {
        return DB::transaction(function () use ($booking, $reasonCode, $actorType, $actorId, $options) {
            // Use transitionStatus to arrived (preserves arrived_at timestamp)
            $this->transitionStatus(
                $booking,
                'arrived',
                $actorType,
                $actorId,
                'Customer arrived late'
            );

            // Set operational state to resolved
            $booking->update(['operational_state' => 'resolved']);

            // Create resolution record
            $resolution = \App\Models\BookingResolution::create([
                'booking_id' => $booking->id,
                'outcome' => 'CUSTOMER_ARRIVED',
                'reason_code' => $reasonCode,
                'notes' => $options['notes'] ?? null,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'resolved_at' => now(),
                'follow_up_required' => false,
            ]);

            // Create resolution activity
            \App\Models\BookingActivity::create([
                'booking_id' => $booking->id,
                'type' => 'BOOKING_RESOLVED',
                'title' => 'Customer arrived late',
                'description' => 'Customer arrived after grace period expired',
                'data' => [
                    'outcome' => 'CUSTOMER_ARRIVED',
                    'reason_code' => $reasonCode,
                    'resolution_id' => $resolution->id,
                ],
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);

            return $booking->fresh(['location', 'customer', 'specialist', 'service', 'resolution']);
        });
    }

    /**
     * Get activity type for resolution outcome.
     */
    private function getActivityTypeForResolution(string $outcome): string
    {
        return match ($outcome) {
            'NO_SHOW' => 'BOOKING_MARKED_MISSED',
            'CANCELLED' => 'BOOKING_CANCELLED',
            'RESCHEDULED' => 'BOOKING_RESCHEDULED',
            'COMPLETED_LATE' => 'BOOKING_RESOLVED',
            'CUSTOMER_ARRIVED' => 'BOOKING_RESOLVED',
        };
    }

    /**
     * Get activity title for resolution outcome.
     */
    private function getActivityTitleForResolution(string $outcome, string $reasonCode): string
    {
        return match ($outcome) {
            'NO_SHOW' => 'Booking marked as no-show',
            'CANCELLED' => 'Booking cancelled',
            'RESCHEDULED' => 'Booking rescheduled',
            'COMPLETED_LATE' => 'Booking completed late',
            'CUSTOMER_ARRIVED' => 'Customer arrived late',
        };
    }

    /**
     * Initiate follow-up for an existing resolution.
     *
     * This method updates an existing resolution to require follow-up,
     * sets the follow-up status and timestamp, and creates an activity log.
     *
     * @param \App\Models\BookingResolution $resolution
     * @param string $actorType Type of actor initiating follow-up
     * @param string|null $actorId ID of the actor initiating follow-up
     * @param array $options Additional options:
     *   - follow_up_at: When follow-up should occur
     *   - follow_up_notes: Notes for follow-up
     * @return \App\Models\BookingResolution
     * @throws ValidationException
     */
    public function initiateFollowUp(
        \App\Models\BookingResolution $resolution,
        ?string $actorType = 'staff',
        ?string $actorId = null,
        array $options = []
    ): \App\Models\BookingResolution {
        return DB::transaction(function () use ($resolution, $actorType, $actorId, $options) {
            // Update resolution with follow-up information
            $resolution->update([
                'follow_up_required' => true,
                'follow_up_at' => $options['follow_up_at'] ?? now()->addHours(24),
                'follow_up_status' => 'PENDING',
                'follow_up_notes' => $options['follow_up_notes'] ?? null,
            ]);

            // Create follow-up activity
            \App\Models\BookingActivity::create([
                'booking_id' => $resolution->booking_id,
                'type' => 'FOLLOW_UP_INITIATED',
                'title' => 'Follow-up initiated',
                'description' => $options['follow_up_notes'] ?? 'Follow-up required for this booking resolution',
                'data' => [
                    'resolution_id' => $resolution->id,
                    'follow_up_at' => $resolution->follow_up_at?->toIso8601String(),
                ],
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);

            return $resolution->fresh();
        });
    }

    /**
     * Determine initial booking status based on approval policy
     * 
     * @param string $providerId
     * @param string|null $locationId
     * @param string $serviceId
     * @param bool $isInternalBooking Whether this is an internal/admin booking
     * @return string Initial status ('pending' or 'confirmed')
     */
    private function determineInitialBookingStatus(
        string $providerId,
        ?string $locationId,
        string $serviceId,
        bool $isInternalBooking = false
    ): string {
        // Internal bookings bypass approval (admin/staff created)
        if ($isInternalBooking) {
            return 'confirmed';
        }

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

    /**
     * Process auto-approval for pending bookings
     * 
     * This method is called during polling to automatically approve bookings
     * that have been pending for longer than the configured auto_approve_after_hours threshold.
     * 
     * @param string|null $providerId Optional provider ID to scope the operation
     * @return array ['approved' => int, 'skipped' => int]
     */
    public function processAutoApprovals(?string $providerId = null): array
    {
        $approvedCount = 0;
        $skippedCount = 0;

        // Get all pending bookings
        $query = Booking::where('status', 'pending');
        
        if ($providerId) {
            $query->where('provider_id', $providerId);
        }

        $pendingBookings = $query->get();

        foreach ($pendingBookings as $booking) {
            try {
                // Get approval policy for this booking's service
                $approvalPolicy = $this->configService->getPolicy(
                    $booking->service_id,
                    $booking->provider_id,
                    $booking->location_id,
                    'approval_policy'
                );

                $autoApproveHours = $approvalPolicy['auto_approve_after_hours'] ?? null;

                // Skip if auto-approval is not configured
                if ($autoApproveHours === null) {
                    $skippedCount++;
                    continue;
                }

                // Calculate time since booking creation
                $bookingCreatedAt = Carbon::parse($booking->created_at);
                $hoursSinceCreation = Carbon::now()->diffInHours($bookingCreatedAt);

                // Check if booking is eligible for auto-approval
                if ($hoursSinceCreation >= $autoApproveHours) {
                    // Transition to confirmed (idempotent - safe to call multiple times)
                    $this->transitionStatus(
                        $booking,
                        'confirmed',
                        'system',
                        null,
                        'Auto-approved after ' . $hoursSinceCreation . ' hours pending'
                    );
                    
                    \Log::info('Booking auto-approved', [
                        'booking_id' => $booking->id,
                        'provider_id' => $booking->provider_id,
                        'hours_pending' => $hoursSinceCreation,
                        'threshold_hours' => $autoApproveHours,
                    ]);
                    
                    $approvedCount++;
                } else {
                    $skippedCount++;
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to process auto-approval for booking', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
                $skippedCount++;
            }
        }

        return [
            'approved' => $approvedCount,
            'skipped' => $skippedCount,
        ];
    }
}

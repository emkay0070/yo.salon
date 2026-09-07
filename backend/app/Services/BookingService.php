<?php

namespace App\Services;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PortalAccount;
use App\Services\Payments\SalonPaymentService;
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
    private SalonPaymentService $salonPaymentService;

    public function __construct(
        CustomerService $customerService,
        PortalAuthService $portalAuthService,
        SalonPaymentService $salonPaymentService
    ) {
        $this->customerService = $customerService;
        $this->portalAuthService = $portalAuthService;
        $this->salonPaymentService = $salonPaymentService;
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
        return DB::transaction(function () use ($data) {
            // Resolve or create customer (business event)
            $customerResult = $this->customerService->resolveOrCreateForBusiness(
                [
                    'name' => $data['customer_name'],
                    'phone' => $data['customer_phone'],
                    'email' => $data['customer_email'] ?? null,
                ],
                $data['salon_id'],
                true // Create customer if not found
            );

            $customer = $customerResult['customer'];

            // Create booking
            $booking = Booking::create([
                'salon_id' => $data['salon_id'],
                'customer_id' => $customer->id,
                'specialist_id' => $data['specialist_id'] ?? null,
                'service_id' => $data['service_id'],
                'date' => $data['date'],
                'time' => $data['time'],
                'status' => $data['status'] ?? 'pending',
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'notes' => $data['notes'] ?? null,
                'slot_locked_until' => $data['slot_locked_until'] ?? null,
            ]);

            return $booking->load(['salon', 'customer', 'specialist', 'service']);
        });
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
        return DB::transaction(function () use ($data) {
            // Resolve or create customer (business event)
            $customerResult = $this->customerService->resolveOrCreateForBusiness(
                [
                    'name' => $data['customer_name'],
                    'phone' => $data['customer_phone'],
                    'email' => $data['customer_email'] ?? null,
                ],
                $data['salon_id'],
                true // Create customer if not found
            );

            $customer = $customerResult['customer'];

            // Get salon and service price securely
            $salon = \App\Models\Salon::find($data['salon_id']);
            $serviceId = is_array($data['service_id']) ? $data['service_id'][0] : $data['service_id'];
            
            $catalogQuery = new \App\Domain\Catalog\Queries\BranchCatalogQuery();
            $resolvedService = $catalogQuery->queryAllServices($salon)->where('services.id', $serviceId)->first();
            $servicePrice = $resolvedService ? $resolvedService->price : 0;
            
            // Generate PaymentInstruction snapshot
            $paymentRulesEngine = app(\App\Services\PaymentRulesEngine::class);
            $paymentInstruction = $paymentRulesEngine->generatePaymentInstructions($serviceId, $salon->id, $servicePrice);

            // Determine deposit requirement and payment status
            $requiresDeposit = $paymentInstruction->customerAction === 'pay_now';
            $bookingStatus = $requiresDeposit ? 'pending_payment' : 'confirmed';
            $paymentStatus = $requiresDeposit ? 'pending' : 'not_required';

            $paymentSnapshotArray = (array) $paymentInstruction;
            if ($requiresDeposit && !empty($data['payment_method_id'])) {
                $paymentSnapshotArray['selected_method_id'] = $data['payment_method_id'];
            }

            // Create booking
            $booking = Booking::create([
                'salon_id' => $data['salon_id'],
                'customer_id' => $customer->id,
                'specialist_id' => $data['specialist_id'] ?? null,
                'service_id' => $serviceId, // Keep single service_id for backward compatibility
                'date' => $data['date'],
                'time' => $data['time'],
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

            // Attach services (support both single service_id and array of service_ids)
            $serviceIds = is_array($data['service_id']) ? $data['service_id'] : [$data['service_id']];
            $booking->services()->attach($serviceIds);

            // Dispatch BookingCreated event
            $serviceNames = $booking->services()->pluck('name')->join(', ');
            Event::dispatch(new BookingCreated(
                $booking->id,
                $customer->id,
                $booking->salon_id,
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
                    $payment = $this->salonPaymentService->initializeBookingPayment(
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
                'booking' => $booking->load(['salon', 'customer', 'specialist', 'service']),
                'customer' => $customer,
                'portal_account' => $portalAccount,
                'is_new_customer' => $customerResult['is_new'],
                'payment' => $payment,
                'requires_deposit' => $requiresDeposit,
            ];
        });
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
        return DB::transaction(function () use ($data, $portalAccount) {
            $customer = $portalAccount->customer;

            // Ensure customer has relationship with this salon
            if (!$customer->salons()->where('salon_id', $data['salon_id'])->exists()) {
                $this->customerService->addSalonRelationship($customer, $data['salon_id']);
            }

            // Create booking
            $booking = Booking::create([
                'salon_id' => $data['salon_id'],
                'customer_id' => $customer->id,
                'specialist_id' => $data['specialist_id'] ?? null,
                'service_id' => $data['service_id'],
                'date' => $data['date'],
                'time' => $data['time'],
                'status' => $data['status'] ?? 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            return $booking->load(['salon', 'customer', 'specialist', 'service']);
        });
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
        return $booking->load(['salon', 'customer', 'specialist', 'service']);
    }

    /**
     * Cancel booking
     * 
     * @param Booking $booking
     * @return Booking
     */
    public function cancelBooking(Booking $booking): Booking
    {
        $booking->update(['status' => 'cancelled']);
        return $booking->fresh();
    }

    /**
     * Complete booking
     * 
     * @param Booking $booking
     * @return Booking
     */
    public function completeBooking(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'completed']);
            
            // Increment customer visit count
            $this->customerService->incrementVisit($booking->customer, $booking->salon_id);
            
            return $booking->fresh();
        });
    }
    /**
     * Transition booking status (State Machine)
     * 
     * @param Booking $booking
     * @param string $newStatus
     * @return Booking
     * @throws ValidationException
     */
    public function transitionStatus(Booking $booking, string $newStatus): Booking
    {
        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'pending_payment' => ['confirmed', 'cancelled'],
            'reserved' => ['pending', 'pending_payment', 'confirmed', 'cancelled'],
            'confirmed' => ['in_progress', 'cancelled', 'no_show'],
            'in_progress' => ['completed'],
            'completed' => [],
            'cancelled' => [],
            'no_show' => [],
        ];

        $currentStatus = $booking->status;

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            throw ValidationException::withMessages([
                'status' => "Invalid state transition from {$currentStatus} to {$newStatus}"
            ]);
        }

        // Delegate to specific methods if they exist for extra logic
        if ($newStatus === 'completed') {
            return $this->completeBooking($booking);
        }
        if ($newStatus === 'cancelled') {
            return $this->cancelBooking($booking);
        }

        $booking->update(['status' => $newStatus]);
        return $booking->fresh(['salon', 'customer', 'specialist', 'service']);
    }
}

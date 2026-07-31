<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\PortalAccount;
use App\Services\Payments\SalonPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
                'staff_id' => $data['staff_id'] ?? null,
                'service_id' => $data['service_id'],
                'date' => $data['date'],
                'time' => $data['time'],
                'status' => $data['status'] ?? 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            return $booking->load(['salon', 'customer', 'staff', 'service']);
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

            // Get salon to check booking policy
            $salon = \App\Models\Salon::find($data['salon_id']);
            
            // Determine if deposit is required
            $requiresDeposit = $salon->booking_deposit_enabled ?? false;
            $depositRequiredFor = $salon->deposit_required_for ?? 'all';
            
            // Check if deposit is required based on salon policy
            if ($requiresDeposit && $depositRequiredFor === 'never') {
                $requiresDeposit = false;
            } elseif ($requiresDeposit && $depositRequiredFor === 'first_time') {
                $requiresDeposit = $customerResult['is_new'];
            } elseif ($requiresDeposit && $depositRequiredFor === 'high_value') {
                // Check if service price is above minimum threshold
                $service = \App\Models\Service::find($data['service_id']);
                $requiresDeposit = $service && $service->price >= ($salon->deposit_min_service_amount ?? 0);
            }

            // Set booking status based on deposit requirement
            $bookingStatus = $requiresDeposit ? 'pending_payment' : 'confirmed';
            $paymentStatus = $requiresDeposit ? 'pending' : 'paid';

            // Create booking
            $booking = Booking::create([
                'salon_id' => $data['salon_id'],
                'customer_id' => $customer->id,
                'staff_id' => $data['staff_id'] ?? null,
                'service_id' => is_array($data['service_id']) ? $data['service_id'][0] : $data['service_id'], // Keep single service_id for backward compatibility
                'date' => $data['date'],
                'time' => $data['time'],
                'status' => $bookingStatus,
                'payment_status' => $paymentStatus,
                'notes' => $data['notes'] ?? null,
            ]);

            // Attach services (support both single service_id and array of service_ids)
            $serviceIds = is_array($data['service_id']) ? $data['service_id'] : [$data['service_id']];
            $booking->services()->attach($serviceIds);

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
                'booking' => $booking->load(['salon', 'customer', 'staff', 'service']),
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
                'staff_id' => $data['staff_id'] ?? null,
                'service_id' => $data['service_id'],
                'date' => $data['date'],
                'time' => $data['time'],
                'status' => $data['status'] ?? 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            return $booking->load(['salon', 'customer', 'staff', 'service']);
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
        return $booking->load(['salon', 'customer', 'staff', 'service']);
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
}

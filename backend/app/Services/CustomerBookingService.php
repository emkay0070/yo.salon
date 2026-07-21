<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Salon;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerBookingService
{
    /**
     * Create a new booking for a customer
     */
    public function createBooking(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::find($data['customer_id']);
            $service = Service::find($data['service_id']);
            $staff = isset($data['staff_id']) ? Staff::find($data['staff_id']) : null;
            $salon = Salon::find($data['salon_id']);

            if (!$customer || !$service || !$salon) {
                throw new \Exception('Invalid booking data');
            }

            // Calculate price
            $price = $this->calculatePrice($service, $data);

            // Create booking
            $booking = Booking::create([
                'customer_id' => $data['customer_id'],
                'salon_id' => $data['salon_id'],
                'service_id' => $data['service_id'],
                'staff_id' => $data['staff_id'] ?? null,
                'date' => $data['date'],
                'time' => $data['time'],
                'status' => 'confirmed',
                'price' => $price,
                'notes' => $data['notes'] ?? null,
            ]);

            // Update customer visit count
            $this->incrementCustomerVisits($customer, $salon);

            return $booking->load(['service', 'staff', 'customer']);
        });
    }

    /**
     * Validate booking data
     */
    public function validateBooking(array $data): array
    {
        $errors = [];

        // Check if date is in the future
        if (Carbon::parse($data['date'])->isPast()) {
            $errors[] = 'Booking date must be in the future';
        }

        // Check if time is valid
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['time'])) {
            $errors[] = 'Invalid time format';
        }

        // Check availability
        $availabilityService = new CustomerAvailabilityService();
        $availability = $availabilityService->getAvailableSlots(
            $data['salon_id'],
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
     * Rebook from a previous booking
     */
    public function rebookFromPrevious(string $previousBookingId, array $newData): Booking
    {
        return DB::transaction(function () use ($previousBookingId, $newData) {
            $previousBooking = Booking::with(['service', 'staff'])->findOrFail($previousBookingId);

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

        $booking->update(['status' => 'cancelled']);

        return $booking->fresh();
    }

    /**
     * Reschedule a booking
     */
    public function rescheduleBooking(string $bookingId, array $newData): Booking
    {
        return DB::transaction(function () use ($bookingId, $newData) {
            $booking = Booking::findOrFail($bookingId);

            // Cancel old booking
            $this->cancelBooking($bookingId);

            // Create new booking with same details but new date/time
            $newBookingData = [
                'customer_id' => $booking->customer_id,
                'salon_id' => $booking->salon_id,
                'service_id' => $booking->service_id,
                'staff_id' => $booking->staff_id,
                'date' => $newData['date'],
                'time' => $newData['time'],
                'notes' => $newData['notes'] ?? $booking->notes,
            ];

            return $this->createBooking($newBookingData);
        });
    }

    /**
     * Increment customer visit count
     */
    private function incrementCustomerVisits(Customer $customer, Salon $salon): void
    {
        $customer->salons()->syncWithoutDetaching([$salon->id => [
            'visits' => DB::raw('visits + 1'),
        ]]);
    }

    /**
     * Get customer's upcoming bookings
     */
    public function getUpcomingBookings(string $customerId, string $salonId): array
    {
        $bookings = Booking::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->where('date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->with(['service', 'staff'])
            ->orderBy('date')
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
            ];
        })->toArray();
    }

    /**
     * Get customer's booking history
     */
    public function getBookingHistory(string $customerId, string $salonId, int $limit = 20): array
    {
        $bookings = Booking::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->where('status', 'completed')
            ->with(['service', 'staff'])
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->limit($limit)
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
            ];
        })->toArray();
    }
}

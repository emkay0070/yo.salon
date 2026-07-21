<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomerAvailabilityService;
use App\Services\CustomerBookingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerBookingController extends Controller
{
    protected CustomerAvailabilityService $availabilityService;
    protected CustomerBookingService $bookingService;

    public function __construct(
        CustomerAvailabilityService $availabilityService,
        CustomerBookingService $bookingService
    ) {
        $this->availabilityService = $availabilityService;
        $this->bookingService = $bookingService;
    }

    /**
     * Get both upcoming bookings and booking history
     */
    public function index(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $upcoming = $this->bookingService->getUpcomingBookings($customerId, $salonId);
            $history = $this->bookingService->getBookingHistory($customerId, $salonId, 50);

            return response()->json([
                'upcoming' => $upcoming,
                'history' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get bookings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available time slots for booking
     */
    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'nullable|uuid',
            'staff_id' => 'nullable|uuid',
            'date' => 'required|date',
        ]);

        $salonId = $request->attributes->get('salon_id');

        try {
            $availability = $this->availabilityService->getAvailableSlots(
                $salonId,
                $validated['service_id'] ?? null,
                $validated['staff_id'] ?? null,
                $validated['date']
            );

            return response()->json($availability);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get availability',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available dates for a month
     */
    public function availableDates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $salonId = $request->attributes->get('salon_id');

        try {
            $dates = $this->availabilityService->getAvailableDates(
                $salonId,
                $validated['year'],
                $validated['month']
            );

            return response()->json($dates);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get available dates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available staff for a service and time slot
     */
    public function availableStaff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid',
            'date' => 'required|date',
            'time' => 'required',
        ]);

        $salonId = $request->attributes->get('salon_id');

        try {
            $staff = $this->availabilityService->getAvailableStaff(
                $salonId,
                $validated['service_id'],
                $validated['date'],
                $validated['time']
            );

            return response()->json($staff);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get available staff',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new booking
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid',
            'staff_id' => 'nullable|uuid',
            'date' => 'required|date',
            'time' => 'required',
            'notes' => 'nullable|string',
            'offer_id' => 'nullable|uuid',
        ]);

        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            // Validate booking
            $bookingData = array_merge($validated, [
                'customer_id' => $customerId,
                'salon_id' => $salonId,
            ]);

            $errors = $this->bookingService->validateBooking($bookingData);
            if (!empty($errors)) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $errors,
                ], 422);
            }

            // Create booking
            $booking = $this->bookingService->createBooking($bookingData);

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rebook from a previous booking
     */
    public function rebook(Request $request, string $previousBookingId): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'time' => 'required',
            'notes' => 'nullable|string',
        ]);

        $salonId = $request->attributes->get('salon_id');

        try {
            $newData = array_merge($validated, [
                'salon_id' => $salonId,
            ]);

            $booking = $this->bookingService->rebookFromPrevious($previousBookingId, $newData);

            return response()->json([
                'message' => 'Booking rebooked successfully',
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to rebook',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a booking
     */
    public function cancel(string $bookingId): JsonResponse
    {
        try {
            $booking = $this->bookingService->cancelBooking($bookingId);

            return response()->json([
                'message' => 'Booking cancelled successfully',
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel booking',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reschedule a booking
     */
    public function reschedule(Request $request, string $bookingId): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'time' => 'required',
            'notes' => 'nullable|string',
        ]);

        try {
            $booking = $this->bookingService->rescheduleBooking($bookingId, $validated);

            return response()->json([
                'message' => 'Booking rescheduled successfully',
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reschedule booking',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get upcoming bookings
     */
    public function upcoming(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $bookings = $this->bookingService->getUpcomingBookings($customerId, $salonId);

            return response()->json([
                'bookings' => $bookings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get upcoming bookings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get booking history
     */
    public function history(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');
        $limit = $request->query('limit', 20);

        try {
            $history = $this->bookingService->getBookingHistory($customerId, $salonId, $limit);

            return response()->json([
                'history' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get booking history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

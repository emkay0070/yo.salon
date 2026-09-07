<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomerAvailabilityService;
use App\Services\CustomerBookingService;
use App\Domain\Availability\Statuses\AvailabilityStatusMapper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerBookingController extends Controller
{
    protected CustomerAvailabilityService $availabilityService;
    protected CustomerBookingService $bookingService;
    protected AvailabilityStatusMapper $statusMapper;

    public function __construct(
        CustomerAvailabilityService $availabilityService,
        CustomerBookingService      $bookingService,
        AvailabilityStatusMapper   $statusMapper,
    ) {
        $this->availabilityService = $availabilityService;
        $this->bookingService      = $bookingService;
        $this->statusMapper        = $statusMapper;
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
            'date' => 'nullable|date',
        ]);

        $salonId = $request->attributes->get('salon_id');

        // If no date provided, return empty slots
        if (empty($validated['date'])) {
            return response()->json([
                'slots' => [],
                'date' => null,
            ]);
        }

        try {
            $availability = $this->availabilityService->getAvailableSlots(
                $salonId,
                $validated['service_id'] ?? null,
                $validated['staff_id'] ?? null,
                $validated['date']
            );

            // Ensure customer portal responses also go through the
            // status mapper. CustomerAvailabilityService currently returns
            // legacy-shaped payloads (no `domain_status`); the mapper's
            // `inferDomainStatus` back-compat path handles that gracefully.
            $public = $this->statusMapper->toPublicResponse($availability);

            return response()->json($public);
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
     * If date/time not provided, returns all specialists who offer the service at the salon
     */
    public function availableStaff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid',
            'date' => 'nullable|date',
            'time' => 'nullable',
        ]);

        $salonId = $request->attributes->get('salon_id');

        try {
            // If date and time are provided, check availability for that specific slot
            if (!empty($validated['date']) && !empty($validated['time'])) {
                $staff = $this->availabilityService->getAvailableStaff(
                    $salonId,
                    $validated['service_id'],
                    $validated['date'],
                    $validated['time']
                );
            } else {
                // Otherwise, return all specialists who offer this service at the salon
                $staff = $this->availabilityService->getSpecialistsForService($salonId, $validated['service_id']);
            }

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
            'specialist_id' => 'nullable|uuid',
            'date' => 'required|date',
            'time' => 'required',
            'notes' => 'nullable|string',
            'offer_id' => 'nullable|uuid',
        ]);

        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            // specialist_id and staff_id are now distinct identities.
            // specialist_id = global professional (specialists.id)
            // staff_id = salon-local employee (staff.id)
            // The domain resolves staff_id via SpecialistAssignment when needed.
            $bookingData = array_merge($validated, [
                'customer_id' => $customerId,
                'salon_id' => $salonId,
                // staff_id only used if explicitly provided (e.g. salon-direct booking)
                'staff_id' => $validated['staff_id'] ?? null,
            ]);

            // Validate booking
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

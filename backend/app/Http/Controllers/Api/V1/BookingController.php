<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    private BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Booking::with(['salon', 'customer', 'staff', 'services']);

        if ($salonId = $request->query('salon_id')) {
            $query->where('salon_id', $salonId);
        }
        if ($request->has('date')) {
            $date = $request->query('date');
            if ($date === 'today') {
                $query->whereDate('date', now()->toDateString());
            } else {
                $query->whereDate('date', $date);
            }
        }

        $bookings = $query->get();
        return response()->json($bookings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'sometimes|required_without:auth|uuid|exists:salons,id',
            'customer_id' => 'nullable|uuid|exists:customers,id',
            'staff_id' => 'nullable|uuid|exists:staff,id',
            'service_id' => 'required|uuid|exists:services,id',
            'date' => 'required|date',
            'time' => 'nullable|string',
            'status' => 'sometimes|in:pending,confirmed,cancelled,completed',
            'notes' => 'nullable|string',
        ]);

        // If time is not provided, extract it from date
        if (empty($validated['time']) && !empty($validated['date'])) {
            $validated['time'] = \Illuminate\Support\Carbon::parse($validated['date'])->format('H:i');
        }

        // If user is authenticated, salon_id is auto-set by BelongsToSalon trait
        // If no auth (guest booking), use salon_id from request
        if (auth()->check()) {
            unset($validated['salon_id']); // Remove so trait can set it
        }

        $booking = Booking::create($validated);
        return response()->json($booking->load(['salon', 'customer', 'staff', 'service']), 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        return response()->json($booking->load(['salon', 'customer', 'staff', 'service']));
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'sometimes|uuid|exists:salons,id',
            'customer_id' => 'nullable|uuid|exists:customers,id',
            'staff_id' => 'nullable|uuid|exists:staff,id',
            'service_id' => 'sometimes|uuid|exists:services,id',
            'date' => 'sometimes|date',
            'time' => 'nullable|string',
            'status' => 'sometimes|in:pending,confirmed,cancelled,completed',
            'notes' => 'nullable|string',
        ]);

        // If time is not provided but date is, extract it from date
        if (empty($validated['time']) && !empty($validated['date'])) {
            $validated['time'] = \Illuminate\Support\Carbon::parse($validated['date'])->format('H:i');
        }

        $booking->update($validated);
        return response()->json($booking->load(['salon', 'customer', 'staff', 'service']));
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $booking->delete();
        return response()->json(null, 204);
    }

    public function bySalon(string $salon): JsonResponse
    {
        $bookings = Booking::where('salon_id', $salon)
            ->with(['customer', 'staff', 'service'])
            ->get();
        return response()->json($bookings);
    }

    public function byCustomer(string $customer): JsonResponse
    {
        $bookings = Booking::where('customer_id', $customer)
            ->with(['salon', 'staff', 'service'])
            ->get();
        return response()->json($bookings);
    }

    /**
     * Create booking with portal account (Journey 4)
     * This is the main entry point for website booking with account creation
     */
    public function storeWithAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'required|uuid|exists:salons,id',
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string',
            'customer_email' => 'nullable|email',
            'service_id' => 'required|array|min:1', // Accept array of service IDs
            'service_id.*' => 'uuid|exists:services,id', // Each service_id must be a valid UUID
            'staff_id' => 'nullable|uuid|exists:staff,id',
            'date' => 'required|date',
            'time' => 'nullable|string',
            'create_account' => 'boolean',
            'account_email' => 'required_if:create_account,true|nullable|email',
            'account_password' => 'required_if:create_account,true|nullable|string|min:8',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
        ]);

        try {
            $result = $this->bookingService->createBookingWithAccount($validated);

            return response()->json([
                'booking' => $result['booking'],
                'customer' => $result['customer'],
                'portal_account' => $result['portal_account'],
                'is_new_customer' => $result['is_new_customer'],
                'payment' => $result['payment'] ?? null,
                'message' => $result['portal_account'] 
                    ? 'Booking created. Please complete payment to confirm.' 
                    : 'Booking created. Please complete payment to confirm.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

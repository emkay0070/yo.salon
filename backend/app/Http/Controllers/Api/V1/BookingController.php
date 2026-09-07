<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Domain\Catalog\Queries\BranchCatalogQuery;
use App\Domain\Catalog\Engines\BookingEngine;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    private BookingService $bookingService;
    private NotificationService $notificationService;

    public function __construct(BookingService $bookingService, NotificationService $notificationService)
    {
        $this->bookingService = $bookingService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): JsonResponse
    {
        \Log::info('BookingController::index called', ['params' => $request->all()]);
        
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);
        
        $query = Booking::with([
            'provider',
            'customer',
            'specialist',
            'service',
            'staff',
        ])->where('salon_id', $salonId);

        if ($request->has('date')) {
            $date = $request->query('date');
            if ($date === 'today') {
                $query->whereDate('date', now()->toDateString());
            } else {
                $query->whereDate('date', $date);
            }
        }

        $bookings = $query->get();
        \Log::info("Found {$bookings->count()} bookings");
        
        return response()->json($bookings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'sometimes|required_without:auth|uuid|exists:salons,id',
            'customer_id' => 'nullable|uuid|exists:customers,id',
            'specialist_id' => 'nullable|uuid|exists:specialists,id',
            'service_id' => 'required|uuid|exists:services,id',
            'date' => 'required|date',
            'time' => 'nullable|string',
            'status' => 'sometimes|in:pending,confirmed,cancelled,completed',
            'notes' => 'nullable|string',
            'idempotency_key' => 'nullable|string',
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

        try {
            $result = app(\App\Services\BookingOrchestrator::class)->reserveAndBook($validated);
            $booking = $result['booking'];
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Send booking confirmation notifications
        if ($booking->customer && $booking->salon) {
            // Send SMS if customer has phone
            if ($booking->customer->phone) {
                $this->notificationService->sendBookingConfirmationSms(
                    $booking->customer->phone,
                    $booking->customer->name,
                    $booking->salon->name,
                    $booking->date,
                    $booking->time
                );
            }

            // Send email if customer has email
            if ($booking->customer->email) {
                $this->notificationService->sendBookingConfirmationEmail(
                    $booking->customer->email,
                    $booking->customer->name,
                    $booking->salon->name,
                    $booking->date,
                    $booking->time
                );
            }
        }

        return response()->json($booking, 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        return response()->json($booking->load(['salon', 'customer', 'specialist', 'service']));
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'sometimes|uuid|exists:salons,id',
            'customer_id' => 'nullable|uuid|exists:customers,id',
            'specialist_id' => 'nullable|uuid|exists:specialists,id',
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
        return response()->json($booking->load(['salon', 'customer', 'specialist', 'service', 'staff']));
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $booking->delete();
        return response()->json(null, 204);
    }

    public function bySalon(string $salon): JsonResponse
    {
        // Get provider_id from salon
        $salonModel = \App\Models\Salon::find($salon);
        $providerId = $salonModel ? $salonModel->provider_id : null;

        if (!$providerId) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $bookings = Booking::where('provider_id', $providerId)
            ->with(['customer', 'specialist', 'service'])
            ->get();
        return response()->json($bookings);
    }

    public function byCustomer(string $customer): JsonResponse
    {
        $bookings = Booking::where('customer_id', $customer)
            ->with(['salon', 'specialist', 'service', 'services'])
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
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
            'specialist_id' => 'nullable|uuid|exists:specialists,id',
            'date' => 'required|date',
            'time' => 'nullable|string',
            'create_account' => 'boolean',
            'account_email' => 'required_if:create_account,true|nullable|email',
            'account_password' => 'required_if:create_account,true|nullable|string|min:8',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'idempotency_key' => 'nullable|string',
        ]);

        try {
            $result = app(\App\Services\BookingOrchestrator::class)->reserveAndBook($validated);

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

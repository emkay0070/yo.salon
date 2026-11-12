<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingServiceResource;
use App\Models\BookingService;
use App\Models\Specialist;
use App\Services\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    private SessionService $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Start a session for a booking service.
     *
     * @param Request $request
     * @param string $bookingServiceId
     * @return JsonResponse
     */
    public function start(Request $request, string $bookingServiceId): JsonResponse
    {
        $actor = $request->user();
        
        $bookingService = BookingService::with(['booking', 'booking.location', 'service'])->findOrFail($bookingServiceId);

        try {
            $updatedBookingService = $this->sessionService->startSession($bookingService, $actor);
            return response()->json(new BookingServiceResource($updatedBookingService->load(['booking', 'booking.location', 'service'])));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Complete a session for a booking service.
     *
     * @param Request $request
     * @param string $bookingServiceId
     * @return JsonResponse
     */
    public function complete(Request $request, string $bookingServiceId): JsonResponse
    {
        $actor = $request->user();
        
        $bookingService = BookingService::with(['booking', 'booking.location', 'service'])->findOrFail($bookingServiceId);

        try {
            $updatedBookingService = $this->sessionService->completeSession($bookingService, $actor);
            return response()->json(new BookingServiceResource($updatedBookingService->load(['booking', 'booking.location', 'service'])));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a session for a booking service.
     *
     * @param Request $request
     * @param string $bookingServiceId
     * @return JsonResponse
     */
    public function cancel(Request $request, string $bookingServiceId): JsonResponse
    {
        $actor = $request->user();
        
        $bookingService = BookingService::with(['booking', 'booking.location', 'service'])->findOrFail($bookingServiceId);

        try {
            $updatedBookingService = $this->sessionService->cancelSession($bookingService, $actor);
            return response()->json(new BookingServiceResource($updatedBookingService->load(['booking', 'booking.location', 'service'])));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}

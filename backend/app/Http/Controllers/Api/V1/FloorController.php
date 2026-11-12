<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingService as BookingServiceModel;
use App\Services\BookingService;
use App\Services\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FloorController — Operational salon floor lifecycle
 *
 * This controller owns the operational layer of the booking journey:
 *   confirmed → arrived → waiting → in_progress → completed → payment
 *
 * It is intentionally separate from BookingController (reservation management)
 * and SessionController (treatment timer). Each controller has one domain.
 *
 * All endpoints here require auth:sanctum (staff/provider session).
 */
class FloorController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private SessionService $sessionService,
        private \App\Services\SpecialistBreakService $specialistBreakService
    ) {}

    /**
     * POST /bookings/{booking}/arrive
     *
     * Mark a confirmed booking as arrived.
     * Records arrived_at timestamp and transitions status → arrived.
     * If booking is in needs_resolution state, uses resolution layer for late arrival.
     */
    public function arrive(Request $request, Booking $booking): JsonResponse
    {
        try {
            $actor = $request->user();

            // If booking is in needs_resolution state, use resolveBooking for late arrival
            if ($booking->operational_state === 'needs_resolution') {
                $updated = $this->bookingService->resolveBooking(
                    $booking,
                    'CUSTOMER_ARRIVED',
                    'CUSTOMER_ARRIVED_LATE',
                    'staff',
                    $actor->id,
                    ['notes' => 'Customer arrived after grace period expired']
                );

                return response()->json([
                    'booking'    => $updated->load(['location', 'customer', 'specialist', 'service', 'bookingServices', 'resolution']),
                    'message'    => 'Customer arrived late and booking resolved.',
                    'arrived_at' => $updated->arrived_at,
                ]);
            }

            // Regular arrival for bookings not in needs_resolution
            $updated = $this->bookingService->transitionStatus(
                $booking,
                'arrived',
                'staff',
                $actor->id,
                'Client marked as arrived by staff'
            );

            return response()->json([
                'booking'    => $updated->load(['location', 'customer', 'specialist', 'service', 'bookingServices']),
                'message'    => 'Client marked as arrived.',
                'arrived_at' => $updated->arrived_at,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /bookings/{booking}/wait
     *
     * Place an arrived client in the waiting queue.
     * Records waiting_since and optionally sets queue_position.
     */
    public function wait(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'queue_position' => 'nullable|integer|min:1',
        ]);

        try {
            $actor = $request->user();
            $updated = $this->bookingService->transitionStatus(
                $booking,
                'waiting',
                'staff',
                $actor->id,
                'Client placed in waiting queue by staff'
            );

            if (!empty($validated['queue_position'])) {
                $updated->update(['queue_position' => $validated['queue_position']]);
                $updated = $updated->fresh();
            }

            return response()->json([
                'booking'       => $updated->load(['location', 'customer', 'specialist', 'service']),
                'message'       => 'Client placed in waiting queue.',
                'waiting_since' => $updated->waiting_since,
                'queue_position'=> $updated->queue_position,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /bookings/{booking}/services/{bookingServiceId}/start
     *
     * Start a service session from the salon floor.
     */
    public function startSession(Request $request, Booking $booking, string $bookingServiceId): JsonResponse
    {
        $bookingService = BookingServiceModel::with(['booking', 'booking.location', 'service'])
            ->where('booking_id', $booking->id)
            ->findOrFail($bookingServiceId);

        try {
            $actor = $request->user();
            $updatedBookingService = $this->sessionService->startSession($bookingService, $actor);
            
            return response()->json([
                'booking_service' => new \App\Http\Resources\BookingServiceResource($updatedBookingService->load(['booking', 'booking.location', 'service'])),
                'message'         => 'Service session started.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /bookings/{booking}/services/{bookingServiceId}/complete
     *
     * Complete a service session from the salon floor.
     */
    public function completeSession(Request $request, Booking $booking, string $bookingServiceId): JsonResponse
    {
        $bookingService = BookingServiceModel::with(['booking', 'booking.location', 'service'])
            ->where('booking_id', $booking->id)
            ->findOrFail($bookingServiceId);

        try {
            $actor = $request->user();
            $updatedBookingService = $this->sessionService->completeSession($bookingService, $actor);
            
            return response()->json([
                'booking_service' => new \App\Http\Resources\BookingServiceResource($updatedBookingService->load(['booking', 'booking.location', 'service'])),
                'message'         => 'Service session completed.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /specialists/{specialist}/breaks
     */
    public function startBreak(Request $request, \App\Models\Specialist $specialist): JsonResponse
    {
        $validated = $request->validate([
            'duration_minutes' => 'required|integer|min:1|max:480',
            'location_id'      => 'nullable|uuid'
        ]);

        try {
            $actor = $request->user();
            $providerId = $request->attributes->get('provider_id');
            $break = $this->specialistBreakService->startBreak(
                $specialist,
                $actor,
                $validated['duration_minutes'],
                $providerId,
                $validated['location_id'] ?? $request->attributes->get('location_id')
            );

            return response()->json([
                'break'   => $break,
                'message' => 'Specialist break started.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /specialists/{specialist}/breaks/end
     */
    public function endBreak(Request $request, \App\Models\Specialist $specialist): JsonResponse
    {
        try {
            $actor = $request->user();
            $providerId = $request->attributes->get('provider_id');
            $locationId = $request->attributes->get('location_id') ?? $request->input('location_id');
            $break = $this->specialistBreakService->endBreak($specialist, $actor, $providerId, $locationId);

            return response()->json([
                'break'   => $break,
                'message' => 'Specialist break ended.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * GET /floor/queue
     *
     * Returns the live operational queue for a location:
     * all bookings in arrived or waiting state, ordered by arrival/wait time.
     * Used by the receptionist floor view.
     */
    public function queue(Request $request): JsonResponse
    {
        $locationId = $request->attributes->get('location_id');
        $providerId = $request->attributes->get('provider_id');

        $query = Booking::with(['customer', 'specialist', 'service', 'bookingServices'])
            ->whereIn('status', ['arrived', 'waiting'])
            ->whereDate('date', now()->toDateString());

        if ($locationId) {
            $query->where('location_id', $locationId);
        } elseif ($providerId) {
            $query->where('provider_id', $providerId);
        } else {
            return response()->json(['message' => 'No location context.'], 400);
        }

        $queue = $query
            ->orderByRaw("CASE WHEN status = 'waiting' THEN 1 ELSE 2 END") // waiting first
            ->orderBy('arrived_at', 'asc')
            ->get()
            ->map(fn($b) => $this->formatQueueItem($b));

        return response()->json([
            'date'  => now()->toDateString(),
            'count' => $queue->count(),
            'queue' => $queue,
        ]);
    }

    /**
     * GET /floor/live
     *
     * Returns all in-progress sessions at this location today.
     * Used by the specialist floor view to see who is being served.
     */
    public function live(Request $request): JsonResponse
    {
        $locationId = $request->attributes->get('location_id');
        $providerId = $request->attributes->get('provider_id');

        $query = Booking::with(['customer', 'specialist', 'service', 'bookingServices'])
            ->where('status', 'in_progress')
            ->whereDate('date', now()->toDateString());

        if ($locationId) {
            $query->where('location_id', $locationId);
        } elseif ($providerId) {
            $query->where('provider_id', $providerId);
        } else {
            return response()->json(['message' => 'No location context.'], 400);
        }

        $sessions = $query->get()->map(fn($b) => $this->formatLiveSession($b));

        return response()->json([
            'date'     => now()->toDateString(),
            'count'    => $sessions->count(),
            'sessions' => $sessions,
        ]);
    }

    /**
     * GET /floor/snapshot
     *
     * Complete floor view: confirmed (upcoming today), arrived, waiting, in_progress.
     * This is the single endpoint the dashboard should call on load.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $locationId = $request->attributes->get('location_id');
        $providerId = $request->attributes->get('provider_id');

        $baseQuery = fn() => Booking::with(['customer', 'specialist', 'service', 'bookingServices'])
            ->whereDate('date', now()->toDateString())
            ->when($locationId, fn($q) => $q->where('location_id', $locationId))
            ->when(!$locationId && $providerId, fn($q) => $q->where('provider_id', $providerId));

        $confirmed  = (clone $baseQuery())->where('status', 'confirmed')->orderBy('time')->get();
        $arrived    = (clone $baseQuery())->where('status', 'arrived')->orderBy('arrived_at')->get();
        $waiting    = (clone $baseQuery())->where('status', 'waiting')->orderBy('waiting_since')->get();
        $inProgress = (clone $baseQuery())->where('status', 'in_progress')->get();

        return response()->json([
            'date' => now()->toDateString(),
            'snapshot' => [
                'confirmed'   => $confirmed->map(fn($b) => $this->formatQueueItem($b)),
                'arrived'     => $arrived->map(fn($b) => $this->formatQueueItem($b)),
                'waiting'     => $waiting->map(fn($b) => $this->formatQueueItem($b)),
                'in_progress' => $inProgress->map(fn($b) => $this->formatLiveSession($b)),
            ],
            'counts' => [
                'confirmed'   => $confirmed->count(),
                'arrived'     => $arrived->count(),
                'waiting'     => $waiting->count(),
                'in_progress' => $inProgress->count(),
            ],
        ]);
    }

    // ── Private formatters ─────────────────────────────────────────────────────

    private function formatQueueItem(Booking $booking): array
    {
        // Expose the first BookingService that can be acted on:
        //   - for pre-session bookings (confirmed/arrived/waiting): first 'scheduled' service
        //   - for in_progress bookings: first 'in_progress' service (handled by formatLiveSession)
        $scheduledService = $booking->bookingServices
            ->where('session_status', 'scheduled')
            ->first();

        return [
            'id'                  => $booking->id,
            'status'              => $booking->status,
            'time'                => $booking->time,
            'arrived_at'          => $booking->arrived_at,
            'waiting_since'       => $booking->waiting_since,
            'queue_position'      => $booking->queue_position,
            // The BookingService.id the floor operator should call start/complete on
            'booking_service_id'  => $scheduledService?->id,
            // Waiting duration in minutes (null if not yet waiting)
            'wait_minutes'        => $booking->waiting_since
                ? now()->diffInMinutes($booking->waiting_since)
                : null,
            'customer'       => [
                'id'       => $booking->customer?->id,
                'name'     => $booking->customer?->name,
                'is_guest' => $booking->customer?->is_guest ?? false,
            ],
            'service'        => [
                'id'       => $booking->service?->id,
                'name'     => $booking->service?->name,
                'duration' => $booking->service?->duration,
            ],
            'specialist'     => $booking->specialist ? [
                'id'         => $booking->specialist->id,
                'name'       => $booking->specialist->name,
                'floor_state'=> $this->getSpecialistFloorState($booking->specialist->id),
            ] : null,
        ];
    }

    private function getSpecialistFloorState(string $specialistId): array
    {
        $activeSession = \App\Models\BookingService::whereHas('booking', function ($q) use ($specialistId) {
                $q->where('specialist_id', $specialistId);
            })
            ->where('session_status', 'in_progress')
            ->first();

        if ($activeSession) {
            return [
                'status' => 'BUSY',
                'expected_end_at' => $activeSession->end_time,
            ];
        }

        $activeBreak = \App\Models\SpecialistBreak::where('specialist_id', $specialistId)
            ->whereNull('actual_end_at')
            ->where('expected_end_at', '>', now())
            ->first();

        if ($activeBreak) {
            return [
                'status' => 'ON_BREAK',
                'expected_end_at' => $activeBreak->expected_end_at,
            ];
        }

        return [
            'status' => 'AVAILABLE',
            'expected_end_at' => null,
        ];
    }

    private function formatLiveSession(Booking $booking): array
    {
        $activeSession = $booking->bookingServices
            ->where('session_status', 'in_progress')
            ->first();

        return array_merge($this->formatQueueItem($booking), [
            'session' => $activeSession ? [
                'id'           => $activeSession->id,
                'started_at'   => $activeSession->started_at,
                'elapsed_min'  => $activeSession->started_at
                    ? now()->diffInMinutes($activeSession->started_at)
                    : null,
                'expected_end' => $activeSession->end_time,
            ] : null,
        ]);
    }
}

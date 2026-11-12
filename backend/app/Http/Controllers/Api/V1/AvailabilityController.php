<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AvailabilityEngine;
use App\Domain\Availability\Statuses\AvailabilityStatusMapper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    private AvailabilityEngine $availabilityEngine;
    private AvailabilityStatusMapper $statusMapper;

    public function __construct(
        AvailabilityEngine $availabilityEngine,
        AvailabilityStatusMapper $statusMapper,
    ) {
        $this->availabilityEngine = $availabilityEngine;
        $this->statusMapper = $statusMapper;
    }

    /**
     * Get available slots for a provider
     */
    public function index(Request $request, string $providerId): JsonResponse
    {
        // Support both direct params and nested params[date] format
        $params = $request->input('params', []);
        $date = $request->input('date') ?? ($params['date'] ?? null);
        $serviceId = $request->input('service_id') ?? ($params['service_id'] ?? null);
        $specialistId = $request->input('specialist_id') ?? ($params['specialist_id'] ?? null);
        $slotGranularity = $request->input('slot_granularity') ?? ($params['slot_granularity'] ?? 15);

        $request->merge([
            'date' => $date,
            'service_id' => $serviceId,
            'specialist_id' => $specialistId,
            'slot_granularity' => $slotGranularity,
        ]);

        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'service_id' => 'nullable|uuid|exists:services,id',
            'specialist_id' => 'nullable|uuid|exists:specialists,id',
            'slot_granularity' => 'nullable|integer|in:15,30,60',
        ]);

        try {
            $availability = $this->availabilityEngine->getAvailableSlots(
                $providerId,
                null, // locationId - not used in this public endpoint
                $date,
                $serviceId,
                $specialistId,
                $slotGranularity
            );

            // Map rich internal domain states → stable public statuses.
            $public = $this->statusMapper->toPublicResponse($availability);

            return response()->json($public);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Requested resource not found',
            ], 404);
        }
    }

    /**
     * Get batch availability for multiple dates
     */
    public function batch(Request $request, string $providerId): JsonResponse
    {
        // Support both direct params and nested params[date] format
        $params = $request->input('params', []);
        $startDate = $request->input('start_date') ?? ($params['start_date'] ?? null);
        $endDate = $request->input('end_date') ?? ($params['end_date'] ?? null);
        $serviceId = $request->input('service_id') ?? ($params['service_id'] ?? null);
        $specialistId = $request->input('specialist_id') ?? ($params['specialist_id'] ?? null);

        $request->merge([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'service_id' => $serviceId,
            'specialist_id' => $specialistId,
        ]);

        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'service_id' => 'nullable|uuid',
            'specialist_id' => 'nullable|uuid',
        ]);

        try {
            $availability = $this->availabilityEngine->getBatchAvailability(
                $providerId,
                null, // locationId - not used in this public endpoint
                $startDate,
                $endDate,
                $serviceId,
                $specialistId
            );

            return response()->json($availability);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get first available slot
     */
    public function firstAvailable(Request $request, string $providerId): JsonResponse
    {
        // Support both direct params and nested params[date] format
        $params = $request->input('params', []);
        $serviceId = $request->input('service_id') ?? ($params['service_id'] ?? null);
        $specialistId = $request->input('specialist_id') ?? ($params['specialist_id'] ?? null);
        $daysAhead = $request->input('days_ahead') ?? ($params['days_ahead'] ?? 30);

        $request->merge([
            'service_id' => $serviceId,
            'specialist_id' => $specialistId,
            'days_ahead' => $daysAhead,
        ]);

        $request->validate([
            'service_id' => 'nullable|uuid',
            'specialist_id' => 'nullable|uuid',
            'days_ahead' => 'nullable|integer|min:1|max:365',
        ]);

        try {
            $slot = $this->availabilityEngine->getFirstAvailableSlot(
                $providerId,
                null, // locationId - not used in this public endpoint
                $serviceId,
                $specialistId,
                $daysAhead
            );

            if (!$slot) {
                return response()->json([
                    'error' => 'no_availability',
                    'message' => 'No available slots found in the specified period',
                ], 404);
            }

            return response()->json($slot);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Requested resource not found',
            ], 404);
        }
    }

    /**
     * Lock a time slot
     */
    public function lockSlot(Request $request, string $providerId): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'service_id' => 'required|uuid|exists:services,id',
            'specialist_id' => 'nullable|uuid|exists:specialists,id',
        ]);

        try {
            $lock = $this->availabilityEngine->lockSlot(
                $providerId,
                $request->input('date'),
                $request->input('start_time'),
                $request->input('service_id'),
                $request->input('specialist_id')
            );

            return response()->json($lock, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Requested resource not found',
            ], 404);
        }
    }

    /**
     * Release a locked slot
     */
    public function releaseSlot(Request $request, string $providerId, string $lockId): JsonResponse
    {
        try {
            $this->availabilityEngine->releaseSlot($lockId);

            return response()->json([
                'message' => 'Slot released successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'release_failed',
                'message' => 'Failed to release slot',
            ], 400);
        }
    }
}

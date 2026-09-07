<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BookingOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingOrchestratorController extends Controller
{
    private BookingOrchestrator $orchestrator;

    public function __construct(BookingOrchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * Create a reservation using the orchestrator
     */
    public function reserve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'required|exists:salons,id',
            'service_id' => 'required|exists:services,id',
            'specialist_id' => 'nullable|exists:specialists,id',
            'date' => 'required|date',
            'time' => 'required|string',
            'customer_id' => 'nullable|exists:customers,id',
            'notes' => 'nullable|string',
            'payment_method_id' => 'nullable|string'
        ]);

        try {
            $result = $this->orchestrator->reserveAndBook($validated);

            return response()->json([
                'message' => 'Reservation created successfully',
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create reservation',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BookingRulesValidator;
use App\Services\PaymentRulesEngine;
use App\Services\CancellationRulesEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingRulesController extends Controller
{
    private BookingRulesValidator $rulesValidator;
    private PaymentRulesEngine $paymentEngine;
    private CancellationRulesEngine $cancellationEngine;

    public function __construct(
        BookingRulesValidator $rulesValidator,
        PaymentRulesEngine $paymentEngine,
        CancellationRulesEngine $cancellationEngine
    ) {
        $this->rulesValidator = $rulesValidator;
        $this->paymentEngine = $paymentEngine;
        $this->cancellationEngine = $cancellationEngine;
    }

    /**
     * Validate a booking request against all rules
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid|exists:services,id',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'provider_id' => 'nullable|uuid|exists:providers,id',
            'date' => 'required|date',
            'time' => 'nullable|string',
            'customer_id' => 'nullable|uuid|exists:customers,id',
            'specialist_id' => 'nullable|uuid|exists:specialists,id',
        ]);

        try {
            $validation = $this->rulesValidator->validateBookingRequest($validated);

            return response()->json([
                'valid' => $validation['valid'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Validation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Get cancellation policy and fees
     */
    public function getCancellationPolicy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid|exists:services,id',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'provider_id' => 'nullable|uuid|exists:providers,id',
        ]);

        try {
            $summary = $this->cancellationEngine->getCancellationPolicySummary(
                $validated['service_id'],
                $validated['provider_id'] ?? null,
                $validated['location_id'] ?? null
            );

            return response()->json([
                'cancellation_policy' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get cancellation policy',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if cancellation is allowed and calculate fees
     */
    public function checkCancellation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid|exists:services,id',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'provider_id' => 'nullable|uuid|exists:providers,id',
            'booking_time' => 'required|date',
            'deposit_amount' => 'required|numeric|min:0',
        ]);

        try {
            $canCancel = $this->cancellationEngine->canCancel(
                $validated['service_id'],
                $validated['provider_id'] ?? null,
                $validated['location_id'] ?? null,
                \Carbon\Carbon::parse($validated['booking_time'])
            );

            $fee = $this->cancellationEngine->calculateCancellationFee(
                $validated['service_id'],
                $validated['provider_id'] ?? null,
                $validated['location_id'] ?? null,
                $validated['deposit_amount'],
                \Carbon\Carbon::parse($validated['booking_time'])
            );

            return response()->json([
                'can_cancel' => $canCancel['allowed'],
                'cancellation_reason' => $canCancel['reason'],
                'cancellation_fee' => $fee['fee'],
                'refundable_amount' => $fee['refundable_amount'],
                'free_cancellation' => $canCancel['free_cancellation'] ?? false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check cancellation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check customer no-show status
     */
    public function checkNoShowStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'provider_id' => 'nullable|uuid|exists:providers,id',
        ]);

        try {
            $status = $this->cancellationEngine->checkNoShowStatus(
                $validated['customer_id'],
                $validated['provider_id'] ?? null,
                $validated['location_id'] ?? null
            );

            return response()->json([
                'no_show_status' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check no-show status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if rescheduling is allowed
     */
    public function checkReschedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid|exists:services,id',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'provider_id' => 'nullable|uuid|exists:providers,id',
            'booking_time' => 'required|date',
        ]);

        try {
            $canReschedule = $this->cancellationEngine->canReschedule(
                $validated['service_id'],
                $validated['provider_id'] ?? null,
                $validated['location_id'] ?? null,
                \Carbon\Carbon::parse($validated['booking_time'])
            );

            return response()->json([
                'can_reschedule' => $canReschedule['allowed'],
                'reschedule_reason' => $canReschedule['reason'],
                'free_reschedule' => $canReschedule['free_reschedule'] ?? false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check reschedule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get booking requirements for a service
     */
    public function getRequirements(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid|exists:services,id',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'provider_id' => 'nullable|uuid|exists:providers,id',
        ]);

        try {
            $requirements = [
                'requires_approval' => $this->rulesValidator->requiresApproval(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
                'requires_consultation' => $this->rulesValidator->requiresConsultation(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
                'requires_customer_profile' => $this->rulesValidator->requiresCustomerProfile(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
                'requires_phone_number' => $this->rulesValidator->requiresPhoneNumber(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
                'allows_walk_ins' => $this->rulesValidator->allowsWalkIns(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
                'allows_repeat_bookings' => $this->rulesValidator->allowsRepeatBookings(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
                'allows_double_booking' => $this->rulesValidator->allowsDoubleBooking(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
                'buffer_minutes' => $this->rulesValidator->getBufferMinutes(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
                'specialist_assignment_strategy' => $this->rulesValidator->getSpecialistAssignmentStrategy(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
                'allow_specialist_choice' => $this->rulesValidator->allowSpecialistChoice(
                    $validated['service_id'],
                    $validated['provider_id'] ?? null,
                    $validated['location_id'] ?? null
                ),
            ];

            return response()->json([
                'requirements' => $requirements,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get requirements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

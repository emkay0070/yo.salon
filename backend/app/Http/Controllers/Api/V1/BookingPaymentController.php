<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\BookingPaymentService;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BookingPaymentController extends Controller
{
    private BookingPaymentService $bookingPaymentService;

    public function __construct(BookingPaymentService $bookingPaymentService)
    {
        $this->bookingPaymentService = $bookingPaymentService;
    }

    /**
     * Initialize payment for a booking.
     * This is used when customers pay for their appointments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function initializeBookingPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'email' => 'required|email',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        try {
            $result = $this->bookingPaymentService->initializeBookingPayment(
                $validated['booking_id'],
                $validated['payment_method_id'],
                $validated['email'],
                $validated['customer_name'],
                $validated['customer_phone'] ?? null
            );

            return response()->json([
                'success' => true,
                'payment_link' => $result['payment_link'],
                'reference' => $result['reference'],
                'provider_reference' => $result['provider_reference'],
                'access_code' => $result['access_code'],
                'payment_request_id' => $result['payment_request_id'],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Booking payment initialization failed', [
                'error' => $e->getMessage(),
                'booking_id' => $validated['booking_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify a booking payment using the reference.
     * This can be called after payment completion to confirm status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyBookingPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string',
        ]);

        try {
            $result = $this->bookingPaymentService->verifyBookingPayment($validated['reference']);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Booking payment verification failed', [
                'error' => $e->getMessage(),
                'reference' => $validated['reference'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record a manual payment (cash, card terminal, etc.).
     * This is used for walk-in customers or physical payments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recordManualPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'nullable|exists:bookings,id',
            'customer_id' => 'nullable|exists:customers,id',
            'location_id' => 'required|exists:locations,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,terminal',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->bookingPaymentService->recordManualPayment($validated);

            return response()->json([
                'success' => true,
                'transaction' => $result['transaction'],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Manual payment recording failed', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refund a booking payment.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refundBookingPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $this->bookingPaymentService->refundBookingPayment(
                $validated['transaction_id'],
                $validated['amount'] ?? null
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Booking payment refund failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $validated['transaction_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment status for a booking.
     *
     * @param string $bookingId
     * @return JsonResponse
     */
    public function getBookingPaymentStatus(string $bookingId): JsonResponse
    {
        try {
            $booking = Booking::with(['paymentRequest', 'transactions'])->findOrFail($bookingId);

            return response()->json([
                'booking_id' => $booking->id,
                'payment_status' => $booking->payment_status,
                'total_price' => $booking->total_price,
                'payment_request' => $booking->paymentRequest ? [
                    'id' => $booking->paymentRequest->id,
                    'status' => $booking->paymentRequest->status,
                    'amount' => $booking->paymentRequest->amount,
                    'provider_reference' => $booking->paymentRequest->provider_reference,
                    'requested_at' => $booking->paymentRequest->requested_at,
                    'expires_at' => $booking->paymentRequest->expires_at,
                ] : null,
                'transactions' => $booking->transactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => $transaction->type,
                        'status' => $transaction->status,
                        'amount' => $transaction->net_amount,
                        'currency' => $transaction->currency,
                        'internal_reference' => $transaction->internal_reference,
                        'provider_reference' => $transaction->provider_reference,
                        'paid_at' => $transaction->paid_at,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status: ' . $e->getMessage(),
            ], 500);
        }
    }
}

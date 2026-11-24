<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Services\Payments\BookingPaymentService;
use App\Support\TenantBootstrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CustomerPaymentController
 *
 * Public endpoint for customer-initiated Flutterwave checkout.
 * Unlike BookingPaymentController (auth:sanctum, business-owner initiated),
 * this controller allows a guest customer to initialize Flutterwave payment
 * for an existing booking without authentication.
 *
 * Business rules:
 * - Customer booking payments belong to the provider, not TYBUEL.
 * - Only Flutterwave is supported for customer self-serve checkout.
 * - The payment method must belong to the booking's provider.
 * - No secret keys are ever exposed to the frontend.
 */
class CustomerPaymentController extends Controller
{
    private BookingPaymentService $bookingPaymentService;

    public function __construct(BookingPaymentService $bookingPaymentService)
    {
        $this->bookingPaymentService = $bookingPaymentService;
    }

    /**
     * Initialize Flutterwave payment for a customer booking.
     *
     * Public endpoint — no authentication required. Validates that the booking
     * exists, is pending payment, and that the selected payment method is a
     * Flutterwave gateway belonging to the booking's provider.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function initialize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id'       => 'required',
            'payment_method_id' => 'required',
            'email'            => 'required|email',
            'customer_name'    => 'required|string|max:255',
            'customer_phone'   => 'nullable|string|max:20',
            'redirect_url'     => 'nullable|url',
        ]);

        return $this->runInBookingOrganization(
            (string) $validated['booking_id'],
            function (?string $organizationId) use ($validated) {
                if ($organizationId === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Booking not found.',
                    ], 404);
                }

                $booking = Booking::find($validated['booking_id']);

                if (!$booking) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Booking not found.',
                    ], 404);
                }

                $paymentMethod = PaymentMethod::find($validated['payment_method_id']);

                if (!$paymentMethod || $paymentMethod->provider_id !== $booking->provider_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment method not found or does not belong to this booking.',
                    ], 404);
                }

                if ($paymentMethod->provider !== 'flutterwave') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only Flutterwave is supported for customer self-serve checkout.',
                    ], 422);
                }

                if (!$paymentMethod->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This payment method is currently disabled.',
                    ], 422);
                }

                try {
                    $result = $this->bookingPaymentService->initializeBookingPayment(
                        $validated['booking_id'],
                        $validated['payment_method_id'],
                        $validated['email'],
                        $validated['customer_name'],
                        $validated['customer_phone'] ?? null,
                        $validated['redirect_url'] ?? null
                    );

                    Log::info('Customer Flutterwave payment initialized', [
                        'booking_id' => $validated['booking_id'],
                        'payment_method_id' => $validated['payment_method_id'],
                        'provider_reference' => $result['provider_reference'] ?? null,
                    ]);

                    return response()->json([
                        'success' => true,
                        'payment_link' => $result['payment_link'],
                        'reference' => $result['reference'],
                        'provider_reference' => $result['provider_reference'],
                    ], 201);

                } catch (\Exception $e) {
                    Log::error('Customer Flutterwave payment initialization failed', [
                        'booking_id' => $validated['booking_id'],
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to initialize payment: ' . $e->getMessage(),
                    ], 500);
                }
            }
        );
    }

    /**
     * Get payment status for a customer booking (public endpoint).
     *
     * Returns only the payment status and provider reference — no sensitive
     * data. Used by the Flutterwave callback page to verify payment before
     * showing success/failure/pending to the customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required',
        ]);

        return $this->runInBookingOrganization(
            (string) $validated['booking_id'],
            function (?string $organizationId) use ($validated) {
                if ($organizationId === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Booking not found.',
                    ], 404);
                }

                $booking = Booking::find($validated['booking_id']);

                if (!$booking) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Booking not found.',
                    ], 404);
                }

                $paymentRequest = PaymentRequest::where('booking_id', $booking->id)->first();

                return response()->json([
                    'success' => true,
                    'booking_id' => $booking->id,
                    'payment_status' => $booking->payment_status,
                    'payment_request' => $paymentRequest ? [
                        'id' => $paymentRequest->id,
                        'status' => $paymentRequest->status,
                        'provider_reference' => $paymentRequest->provider_reference,
                        'amount' => $paymentRequest->amount,
                        'requested_at' => $paymentRequest->requested_at,
                        'completed_at' => $paymentRequest->completed_at,
                        'expires_at' => $paymentRequest->expires_at,
                    ] : null,
                ]);
            }
        );
    }

    /**
     * Execute a callback under the RLS context of the organization that owns
     * the given booking.
     *
     * Public checkout is unauthenticated and therefore runs in the guest RLS
     * context (`app.current_organization_id` is empty), where `bookings` is
     * deliberately invisible. The booking UUID is the only tenant selector
     * available on this public route, so it is resolved on the privileged
     * bootstrap connection (BYPASSRLS) — the same pre-tenant discovery path
     * used elsewhere — and the runtime connection's context is then scoped to
     * that organization for the duration of the callback. RLS policies are not
     * weakened, and the context resets when the enclosing transaction ends.
     *
     * @param callable(?string):mixed $callback Receives the resolved organization id, or null when the booking is unknown.
     */
    private function runInBookingOrganization(string $bookingId, callable $callback): mixed
    {
        $organizationId = DB::connection(TenantBootstrap::connectionName())
            ->table('bookings')
            ->join('providers', 'providers.id', '=', 'bookings.provider_id')
            ->where('bookings.id', $bookingId)
            ->value('providers.organization_id');

        if (!$organizationId) {
            return $callback(null);
        }

        // SET LOCAL (set_config is_local = true) only applies inside a
        // transaction block, so open one to scope the organization context to
        // every query the callback performs. The context resets automatically
        // once the transaction completes.
        return DB::transaction(function () use ($organizationId, $callback) {
            DB::statement(
                "SELECT set_config('app.current_organization_id', ?, true)",
                [(string) $organizationId]
            );

            return $callback((string) $organizationId);
        });
    }
}

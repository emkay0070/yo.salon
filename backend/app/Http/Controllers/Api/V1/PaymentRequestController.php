<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\Booking;
use App\Services\FeeEngine;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentRoutingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentRequestController extends Controller
{
    protected FeeEngine $feeEngine;
    protected PaymentManager $paymentManager;
    protected PaymentRoutingService $paymentRoutingService;

    public function __construct(FeeEngine $feeEngine, PaymentManager $paymentManager, PaymentRoutingService $paymentRoutingService)
    {
        $this->feeEngine = $feeEngine;
        $this->paymentManager = $paymentManager;
        $this->paymentRoutingService = $paymentRoutingService;
    }

    private function providerId(): ?string
    {
        return auth()->user()?->currentProvider()?->id;
    }

    /**
     * Payment requests are tenant-owned. Route model binding alone would let
     * any authenticated provider read or mutate another provider's request.
     */
    private function ensureProviderOwns(PaymentRequest $paymentRequest): void
    {
        abort_unless($paymentRequest->provider_id === $this->providerId(), 404);
    }

    public function index(Request $request): JsonResponse
    {
        $providerId = auth()->user()->currentProvider()?->id;
        if (!$providerId) return response()->json(['message' => 'No provider associated with your account'], 403);

        $query = PaymentRequest::with(['booking', 'customer', 'paymentMethod'])
            ->where('provider_id', $providerId);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Create and dispatch a new payment request.
     * For mobile money, this triggers the STK push via the provider.
     */
    public function store(Request $request): JsonResponse
    {
        $providerId = auth()->user()->currentProvider()?->id;
        if (!$providerId) return response()->json(['message' => 'No provider associated with your account'], 403);

        $validated = $request->validate([
            'booking_id'        => 'nullable|exists:bookings,id',
            'customer_id'       => 'nullable|exists:customers,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment_method'    => 'nullable|string',
            'amount'            => 'required|numeric|min:1',
            'phone_number'      => 'nullable|string',
            'idempotency_key'   => 'nullable|string|max:255',
        ]);

        // IDEMPOTENCY CHECK: Check for existing payment request with same idempotency key
        if (!empty($validated['idempotency_key'])) {
            $existingRequest = PaymentRequest::where('idempotency_key', $validated['idempotency_key'])
                ->where('provider_id', $providerId)
                ->first();

            if ($existingRequest) {
                Log::info('Returning existing payment request (idempotent)', [
                    'idempotency_key' => $validated['idempotency_key'],
                    'payment_request_id' => $existingRequest->id,
                ]);
                return response()->json($existingRequest->load(['booking', 'customer', 'paymentMethod']), 200);
            }
        }

        // IDEMPOTENCY CHECK: Check for existing pending payment request for same booking
        if (!empty($validated['booking_id'])) {
            $existingRequest = PaymentRequest::where('booking_id', $validated['booking_id'])
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            if ($existingRequest) {
                Log::info('Returning existing pending payment request', [
                    'booking_id' => $validated['booking_id'],
                    'payment_request_id' => $existingRequest->id,
                ]);
                return response()->json($existingRequest->load(['booking', 'customer', 'paymentMethod']), 200);
            }
        }

        $paymentMethodId = $validated['payment_method_id'] ?? null;
        if (!$paymentMethodId && !empty($validated['payment_method'])) {
            $provider = $validated['payment_method'];
            $pm = \App\Models\PaymentMethod::firstOrCreate(
                ['provider_id' => $providerId, 'provider' => $provider],
                [
                    'type' => $provider === 'cash' ? 'cash' : ($provider === 'visa' ? 'card' : 'mobile_money'),
                    'display_name' => ucfirst($provider)
                ]
            );
            $paymentMethodId = $pm->id;
        }

        // Get payment method to determine provider. A provider may only use its
        // own payment methods (and therefore its own credentials).
        $paymentMethod = $paymentMethodId
            ? \App\Models\PaymentMethod::where('id', $paymentMethodId)
                ->where('provider_id', $providerId)
                ->first()
            : null;

        if ($paymentMethodId && !$paymentMethod) {
            return response()->json(['message' => 'Payment method not found'], 404);
        }

        $providerName = $paymentMethod?->provider ?? 'mtn_momo'; // Default to MTN
        if ($providerName === 'mtn') {
            $providerName = 'mtn_momo';
        }

        // A provider may only attach its own customers to a payment request.
        if (!empty($validated['customer_id'])) {
            $ownsCustomer = \App\Models\Provider::whereKey($providerId)
                ->whereHas('customers', fn ($q) => $q->whereKey($validated['customer_id']))
                ->exists();

            if (!$ownsCustomer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }
        }

        // VALIDATE AMOUNT: Backend should calculate amount, not trust frontend
        $calculatedAmount = $validated['amount'];
        if (!empty($validated['booking_id'])) {
            $booking = Booking::with(['services', 'location'])->find($validated['booking_id']);
            if ($booking) {
                // A provider may only create payment requests for its own bookings.
                if ($booking->provider_id !== $providerId) {
                    return response()->json(['message' => 'Booking not found'], 404);
                }

                // Derive provider from location since Booking no longer has salon relationship
                $providerModel = $booking->location ? \App\Models\Provider::where('id', $booking->provider_id)->first() : null;
                $serviceTotal = 0;
                $depositAmount = 0;

                foreach ($booking->services as $service) {
                    if ($providerModel && $booking->location) {
                        $resolver = new \App\Services\BookingPolicyResolver($providerModel, $booking->location, $service);
                        $serviceTotal += $resolver->effectivePrice();
                        $depositAmount += $resolver->depositAmount();
                    }
                }

                // If deposit is required, use deposit amount; otherwise use full amount
                $calculatedAmount = $depositAmount > 0 ? $depositAmount : $serviceTotal;

                // Validate that frontend amount matches backend calculation (with small tolerance for rounding)
                $tolerance = 100; // Allow 100 UGX difference for rounding
                if (abs($validated['amount'] - $calculatedAmount) > $tolerance) {
                    Log::warning('Payment amount mismatch', [
                        'booking_id' => $validated['booking_id'],
                        'frontend_amount' => $validated['amount'],
                        'backend_amount' => $calculatedAmount,
                        'service_total' => $serviceTotal,
                        'deposit_amount' => $depositAmount,
                    ]);
                    return response()->json([
                        'message' => 'Amount mismatch. Please refresh and try again.',
                        'expected_amount' => $calculatedAmount,
                    ], 400);
                }
            }
        }

        // Generate unique reference
        $reference = 'PAY-' . strtoupper(Str::random(12));

        $paymentRequest = PaymentRequest::create([
            'provider_id'        => $providerId,
            'booking_id'         => $validated['booking_id'] ?? null,
            'customer_id'        => $validated['customer_id'] ?? null,
            'payment_method_id'  => $paymentMethodId,
            'provider'           => $providerName,
            'amount'             => $calculatedAmount,
            'phone_number'       => $validated['phone_number'] ?? null,
            'status'             => 'pending',
            'provider_reference' => $reference,
            'requested_at'       => now(),
            'expires_at'         => now()->addMinutes(15),
            'idempotency_key'    => $validated['idempotency_key'] ?? null,
        ]);

        // Dispatch to provider if it's a mobile money provider
        if (in_array($providerName, ['mtn_momo', 'airtel_money']) && $paymentRequest->phone_number) {
            // Get credentials from payment method record (per-provider credentials)
            $credentials = null;
            if ($paymentMethod) {
                $credentials = [
                    'api_subscription_key' => $paymentMethod->api_subscription_key,
                    'api_key' => $paymentMethod->api_key,
                    'merchant_id' => $paymentMethod->merchant_id,
                    'environment' => $paymentMethod->environment ?? 'sandbox',
                ];
            }

            $paymentData = [
                'amount' => $calculatedAmount,
                'currency' => 'UGX',
                'phone_number' => $paymentRequest->phone_number,
                'reference' => $reference,
                'description' => 'Payment for booking #' . ($paymentRequest->booking_id ?? 'N/A'),
            ];

            $response = $this->paymentManager->requestPayment($providerName, $paymentData, $credentials);

            if ($response['success']) {
                $paymentRequest->update([
                    'status' => 'processing',
                    'provider_reference' => $response['provider_reference'] ?? $reference,
                ]);
            } else {
                $paymentRequest->update([
                    'status' => 'failed',
                ]);
            }
        } else {
            // For cash or card, mark as pending (no STK push)
            $paymentRequest->update(['status' => 'pending']);
        }

        // Update booking payment_status to 'pending'
        if ($paymentRequest->booking_id) {
            Booking::where('id', $paymentRequest->booking_id)
                ->update(['payment_status' => 'pending']);
        }

        return response()->json($paymentRequest->load(['booking', 'customer', 'paymentMethod']), 201);
    }

    public function show(PaymentRequest $paymentRequest): JsonResponse
    {
        $this->ensureProviderOwns($paymentRequest);

        return response()->json($paymentRequest->load(['booking', 'customer', 'paymentMethod']));
    }

    /**
     * Check payment status from database (webhook is source of truth)
     * Polling should only read DB, never call provider directly
     */
    public function checkStatus(PaymentRequest $paymentRequest): JsonResponse
    {
        $this->ensureProviderOwns($paymentRequest);

        // Only return current status from database
        // Webhook is the source of truth for payment status
        return response()->json([
            'payment_request' => $paymentRequest->load(['booking', 'customer', 'paymentMethod']),
        ]);
    }

    /**
     * Update non-settlement lifecycle state only.
     *
     * A payment becomes "paid" exclusively through provider verification or the
     * manual-payment path. Allowing an authenticated tenant to set `paid` here
     * would mint a completed transaction without any money being collected.
     */
    public function updateStatus(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->ensureProviderOwns($paymentRequest);

        $validated = $request->validate([
            'status'             => 'required|in:failed,cancelled,expired',
            'provider_reference' => 'nullable|string',
        ]);

        if (in_array($paymentRequest->status, ['paid', 'successful'], true)) {
            return response()->json(['message' => 'Settled payment requests cannot be updated.'], 422);
        }

        $paymentRequest->update([
            'status'             => $validated['status'],
            'provider_reference' => $validated['provider_reference'] ?? $paymentRequest->provider_reference,
            'completed_at'       => now(),
        ]);

        if ($paymentRequest->booking_id && $validated['status'] === 'cancelled') {
            Booking::where('id', $paymentRequest->booking_id)
                ->update(['payment_status' => 'unpaid']);
        }

        return response()->json($paymentRequest->fresh());
    }

    /**
     * Cancel an active payment request.
     */
    public function cancel(PaymentRequest $paymentRequest): JsonResponse
    {
        $this->ensureProviderOwns($paymentRequest);

        if (!in_array($paymentRequest->status, ['pending', 'sent'])) {
            return response()->json(['message' => 'Only pending or sent requests can be cancelled.'], 422);
        }

        $paymentRequest->update([
            'status'       => 'cancelled',
            'completed_at' => now(),
        ]);

        // Revert booking payment_status to unpaid
        if ($paymentRequest->booking_id) {
            Booking::where('id', $paymentRequest->booking_id)
                ->update(['payment_status' => 'unpaid']);
        }

        return response()->json($paymentRequest->fresh());
    }
}

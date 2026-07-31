<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\Booking;
use App\Services\FeeEngine;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentRequestController extends Controller
{
    protected FeeEngine $feeEngine;
    protected PaymentManager $paymentManager;

    public function __construct(FeeEngine $feeEngine, PaymentManager $paymentManager)
    {
        $this->feeEngine = $feeEngine;
        $this->paymentManager = $paymentManager;
    }
    public function index(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        $query = PaymentRequest::with(['booking', 'customer', 'paymentMethod'])
            ->where('salon_id', $salonId);

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
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        $validated = $request->validate([
            'booking_id'        => 'nullable|exists:bookings,id',
            'customer_id'       => 'nullable|exists:customers,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment_method'    => 'nullable|string',
            'amount'            => 'required|numeric|min:1',
            'phone_number'      => 'nullable|string',
        ]);

        $paymentMethodId = $validated['payment_method_id'] ?? null;
        if (!$paymentMethodId && !empty($validated['payment_method'])) {
            $provider = $validated['payment_method'];
            $pm = \App\Models\PaymentMethod::firstOrCreate(
                ['salon_id' => $salonId, 'provider' => $provider],
                [
                    'type' => $provider === 'cash' ? 'cash' : ($provider === 'visa' ? 'card' : 'mobile_money'),
                    'display_name' => ucfirst($provider)
                ]
            );
            $paymentMethodId = $pm->id;
        }

        // Get payment method to determine provider
        $paymentMethod = $paymentMethodId ? \App\Models\PaymentMethod::find($paymentMethodId) : null;
        $providerName = $paymentMethod?->provider ?? 'mtn_momo'; // Default to MTN

        // VALIDATE AMOUNT: Backend should calculate amount, not trust frontend
        $calculatedAmount = $validated['amount'];
        if ($validated['booking_id']) {
            $booking = Booking::with('services')->find($validated['booking_id']);
            if ($booking) {
                // Calculate actual amount from booking services
                $serviceTotal = $booking->services->sum('price');
                // Check if salon has deposit policy
                $salonPolicy = \App\Models\FeatureFlag::where('salon_id', $salonId)
                    ->where('key', 'booking_deposit_enabled')
                    ->first();
                
                if ($salonPolicy && $salonPolicy->value === true) {
                    $calculatedAmount = $serviceTotal * 0.3; // 30% deposit
                } else {
                    $calculatedAmount = $serviceTotal;
                }

                // Validate that frontend amount matches backend calculation (with small tolerance for rounding)
                $tolerance = 100; // Allow 100 UGX difference for rounding
                if (abs($validated['amount'] - $calculatedAmount) > $tolerance) {
                    Log::warning('Payment amount mismatch', [
                        'booking_id' => $validated['booking_id'],
                        'frontend_amount' => $validated['amount'],
                        'backend_amount' => $calculatedAmount,
                    ]);
                    return response()->json([
                        'message' => 'Amount mismatch. Please refresh and try again.',
                        'expected_amount' => $calculatedAmount,
                    ], 400);
                }
            }
        }

        // IDEMPOTENCY CHECK: Check for existing pending payment request
        if ($validated['booking_id']) {
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

        // Generate unique reference
        $reference = 'PAY-' . strtoupper(Str::random(12));

        $paymentRequest = PaymentRequest::create([
            'salon_id'           => $salonId,
            'booking_id'         => $validated['booking_id'] ?? null,
            'customer_id'        => $validated['customer_id'] ?? null,
            'payment_method_id'  => $paymentMethodId,
            'provider'           => $providerName,
            'amount'             => $calculatedAmount, // Use backend-calculated amount
            'phone_number'       => $validated['phone_number'] ?? null,
            'status'             => 'pending',
            'provider_reference' => $reference,
            'requested_at'       => now(),
            'expires_at'         => now()->addMinutes(15),
        ]);

        // Dispatch to provider if it's a mobile money provider
        if (in_array($providerName, ['mtn_momo', 'airtel_money']) && $paymentRequest->phone_number) {
            // Get credentials from payment method record (per-salon credentials)
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
                'amount' => $calculatedAmount, // Use backend-calculated amount
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
        return response()->json($paymentRequest->load(['booking', 'customer', 'paymentMethod']));
    }

    /**
     * Check payment status from database (webhook is source of truth)
     * Polling should only read DB, never call provider directly
     */
    public function checkStatus(PaymentRequest $paymentRequest): JsonResponse
    {
        // Only return current status from database
        // Webhook is the source of truth for payment status
        return response()->json([
            'payment_request' => $paymentRequest->load(['booking', 'customer', 'paymentMethod']),
        ]);
    }

    /**
     * Handle provider webhook callbacks — customer approved or declined.
     * In production, this would be called by the payment provider's webhook.
     */
    public function updateStatus(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $validated = $request->validate([
            'status'             => 'required|in:approved,expired,cancelled,paid',
            'provider_reference' => 'nullable|string',
        ]);

        $paymentRequest->update([
            'status'             => $validated['status'],
            'provider_reference' => $validated['provider_reference'] ?? $paymentRequest->provider_reference,
            'completed_at'       => in_array($validated['status'], ['paid', 'cancelled', 'expired']) ? now() : null,
        ]);

        // If paid, create a completed transaction and mark booking as paid
        if ($validated['status'] === 'paid') {
            $paymentMethod = $paymentRequest->payment_method_id
                ? \App\Models\PaymentMethod::find($paymentRequest->payment_method_id)
                : null;

            $fees = $this->feeEngine->calculateFees((float) $paymentRequest->amount, $paymentMethod);

            $transaction = \App\Models\Transaction::create([
                'salon_id'           => $paymentRequest->salon_id,
                'booking_id'         => $paymentRequest->booking_id,
                'customer_id'        => $paymentRequest->customer_id,
                'payment_method_id'  => $paymentRequest->payment_method_id,
                'type'               => 'payment',
                'status'             => 'completed',
                'gross_amount'       => $fees['gross_amount'],
                'gateway_fee'        => $fees['gateway_fee'],
                'platform_fee'       => $fees['platform_fee'],
                'tax_amount'         => $fees['tax_amount'],
                'net_amount'         => $fees['net_amount'],
                'currency'           => 'UGX',
                'internal_reference' => 'TXN-' . strtoupper(Str::random(10)),
                'provider_reference' => $validated['provider_reference'] ?? null,
                'paid_at'            => now(),
            ]);

            if ($paymentRequest->booking_id) {
                Booking::where('id', $paymentRequest->booking_id)
                    ->update(['payment_status' => 'paid']);
            }

            return response()->json([
                'payment_request' => $paymentRequest->fresh(),
                'transaction'     => $transaction,
            ]);
        }

        return response()->json($paymentRequest->fresh());
    }

    /**
     * Cancel an active payment request.
     */
    public function cancel(PaymentRequest $paymentRequest): JsonResponse
    {
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

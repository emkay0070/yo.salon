<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\Booking;
use App\Services\FeeEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentRequestController extends Controller
{
    protected FeeEngine $feeEngine;

    public function __construct(FeeEngine $feeEngine)
    {
        $this->feeEngine = $feeEngine;
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
     * For mobile money, this would trigger the STK push via the provider.
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

        $paymentRequest = PaymentRequest::create([
            'salon_id'           => $salonId,
            'booking_id'         => $validated['booking_id'] ?? null,
            'customer_id'        => $validated['customer_id'] ?? null,
            'payment_method_id'  => $paymentMethodId,
            'amount'             => $validated['amount'],
            'status'       => 'pending',
            'requested_at' => now(),
            'expires_at'   => now()->addMinutes(15),
        ]);

        // TODO: Dispatch to provider (e.g., Flutterwave / MTN MoMo)
        // ProviderService::sendPushRequest($paymentRequest);

        // Transition to 'sent' after dispatching
        $paymentRequest->update(['status' => 'sent']);

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

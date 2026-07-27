<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Services\FeeEngine;

class TransactionController extends Controller
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

        $query = Transaction::with(['booking', 'customer', 'paymentMethod'])
            ->where('salon_id', $salonId);

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('payment_method_id')) {
            $query->where('payment_method_id', $request->query('payment_method_id'));
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Manually record a transaction (e.g., Cash payment by receptionist).
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
            'type'              => 'required|in:payment,refund,adjustment,payout',
            'amount'            => 'required|numeric|min:0', // Still accept 'amount' from client but map to gross
            'currency'          => 'sometimes|string|max:10',
            'notes'             => 'nullable|string',
            'provider_reference'=> 'nullable|string',
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
            $paymentMethodId = $pm->id;
        }

        $paymentMethod = $paymentMethodId ? \App\Models\PaymentMethod::find($paymentMethodId) : null;
        $fees = $this->feeEngine->calculateFees((float) $validated['amount'], $paymentMethod);

        $transaction = Transaction::create([
            'salon_id'           => $salonId,
            'booking_id'         => $validated['booking_id'] ?? null,
            'customer_id'        => $validated['customer_id'] ?? null,
            'payment_method_id'  => $paymentMethodId,
            'type'               => $validated['type'],
            'gross_amount'       => $fees['gross_amount'],
            'gateway_fee'        => $fees['gateway_fee'],
            'platform_fee'       => $fees['platform_fee'],
            'tax_amount'         => $fees['tax_amount'],
            'net_amount'         => $fees['net_amount'],
            'currency'           => $validated['currency'] ?? 'UGX',
            'notes'              => $validated['notes'] ?? null,
            'provider_reference' => $validated['provider_reference'] ?? null,
            'status'             => 'completed',
            'internal_reference' => 'TXN-' . strtoupper(Str::random(10)),
            'paid_at'            => now(),
        ]);

        // Mark booking as paid if this is a payment transaction
        if ($transaction->booking_id && $transaction->type === 'payment') {
            \App\Models\Booking::where('id', $transaction->booking_id)
                ->update(['payment_status' => 'paid']);
        }

        if ($transaction->booking_id && $transaction->type === 'refund') {
            \App\Models\Booking::where('id', $transaction->booking_id)
                ->update(['payment_status' => 'refunded']);
        }

        return response()->json($transaction->load(['booking', 'customer', 'paymentMethod']), 201);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json($transaction->load(['booking', 'customer', 'paymentMethod', 'salon']));
    }

    /**
     * Today's Summary — metrics for the Wallet dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        $date = $request->query('date', now()->toDateString());

        $todayTransactions = Transaction::where('salon_id', $salonId)
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->get();

        $yesterdayTransactions = Transaction::where('salon_id', $salonId)
            ->whereDate('created_at', now()->subDay()->toDateString())
            ->where('status', 'completed')
            ->get();

        $totalReceived     = $todayTransactions->where('type', 'payment')->sum('gross_amount');
        $totalNetReceived  = $todayTransactions->where('type', 'payment')->sum('net_amount');
        $totalRefunds      = $todayTransactions->where('type', 'refund')->sum('gross_amount');
        $transactionCount  = $todayTransactions->where('type', 'payment')->count();
        $averageSale       = $transactionCount > 0 ? $totalReceived / $transactionCount : 0;

        $prevReceived      = $yesterdayTransactions->where('type', 'payment')->sum('gross_amount');
        $prevRefunds       = $yesterdayTransactions->where('type', 'refund')->sum('gross_amount');
        $prevCount         = $yesterdayTransactions->where('type', 'payment')->count();

        return response()->json([
            'date'              => $date,
            'total_received'    => $totalReceived,
            'total_net_received'=> $totalNetReceived,
            'transaction_count' => $transactionCount,
            'average_sale'      => round($averageSale, 0),
            'total_refunds'     => $totalRefunds,
            'vs_yesterday'      => [
                'total_received'    => $prevReceived > 0 ? round((($totalReceived - $prevReceived) / $prevReceived) * 100, 1) : null,
                'transaction_count' => $prevCount > 0 ? round((($transactionCount - $prevCount) / $prevCount) * 100, 1) : null,
                'average_sale'      => $prevCount > 0 ? round(($averageSale - ($prevCount > 0 ? $prevReceived / $prevCount : 0)) / ($prevCount > 0 ? $prevReceived / $prevCount : 1) * 100, 1) : null,
                'total_refunds'     => $prevRefunds > 0 ? round((($totalRefunds - $prevRefunds) / $prevRefunds) * 100, 1) : null,
            ],
        ]);
    }
}

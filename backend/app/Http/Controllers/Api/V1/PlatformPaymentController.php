<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PlatformPaymentService;
use App\Services\Payments\FlutterwaveProvider;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PlatformPaymentController extends Controller
{
    private PlatformPaymentService $platformPaymentService;

    public function __construct(PlatformPaymentService $platformPaymentService)
    {
        $this->platformPaymentService = $platformPaymentService;
    }

    /**
     * Initialize payment for a subscription invoice.
     * This is used when salon owners pay for their Yo.Salon subscription.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function initializeSubscriptionPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'email' => 'required|email',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        try {
            $result = $this->platformPaymentService->initializeSubscriptionPayment(
                $validated['invoice_id'],
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
            ], 201);

        } catch (\Exception $e) {
            Log::error('Subscription payment initialization failed', [
                'error' => $e->getMessage(),
                'invoice_id' => $validated['invoice_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify a subscription payment using the reference.
     * This can be called after payment completion to confirm status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifySubscriptionPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string',
        ]);

        try {
            $result = $this->platformPaymentService->verifySubscriptionPayment($validated['reference']);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Subscription payment verification failed', [
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
     * Refund a subscription payment.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refundSubscriptionPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $this->platformPaymentService->refundSubscriptionPayment(
                $validated['invoice_id'],
                $validated['amount'] ?? null
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Subscription payment refund failed', [
                'error' => $e->getMessage(),
                'invoice_id' => $validated['invoice_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment status for an invoice.
     *
     * @param string $invoiceId
     * @return JsonResponse
     */
    public function getInvoicePaymentStatus(string $invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::with('payments')->findOrFail($invoiceId);

            return response()->json([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'amount' => $invoice->total,
                'currency' => $invoice->currency,
                'payments' => $invoice->payments->map(function ($payment) {
                    return [
                        'status' => $payment->status,
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'payment_gateway' => $payment->payment_gateway,
                        'transaction_id' => $payment->transaction_id,
                        'processed_at' => $payment->processed_at,
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

    /**
     * Initialize cash payment for an invoice (creates pending invoice).
     * This is used when a provider pays cash in person.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function initializeCashPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        try {
            $invoice = Invoice::findOrFail($validated['invoice_id']);

            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice is already paid',
                ], 400);
            }

            // Mark invoice as pending for cash payment
            $updatedInvoice = app(\App\Services\InvoiceService::class)->markAsPending($invoice->id, 'cash');

            return response()->json([
                'success' => true,
                'message' => 'Cash payment initialized. Awaiting confirmation.',
                'invoice' => $updatedInvoice,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Cash payment initialization failed', [
                'error' => $e->getMessage(),
                'invoice_id' => $validated['invoice_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize cash payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm cash payment for an invoice (admin only).
     * This is used when an admin confirms receipt of cash payment.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmCashPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        try {
            $confirmationData = [
                'confirmed_by' => auth()->id(),
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ];

            $invoice = app(\App\Services\InvoiceService::class)->confirmCashPayment(
                $validated['invoice_id'],
                $confirmationData
            );

            return response()->json([
                'success' => true,
                'message' => 'Cash payment confirmed and subscription activated.',
                'invoice' => $invoice,
            ]);

        } catch (\Exception $e) {
            Log::error('Cash payment confirmation failed', [
                'error' => $e->getMessage(),
                'invoice_id' => $validated['invoice_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm cash payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}

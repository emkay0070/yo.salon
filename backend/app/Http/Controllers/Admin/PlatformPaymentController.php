<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Models\Invoice;
use App\Models\PlatformPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PlatformPaymentController extends Controller
{
    private InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Get pending invoices awaiting manual verification
     */
    public function getPendingInvoices(): JsonResponse
    {
        try {
            $invoices = Invoice::where('status', 'pending')
                ->with('subscription.plan', 'subscription.provider')
                ->with('payments')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'invoices' => $invoices,
                'count' => $invoices->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get pending invoices', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get pending invoices',
            ], 500);
        }
    }

    /**
     * Verify manual payment (admin action)
     */
    public function verifyPayment(Request $request, string $invoiceId): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        try {
            $verificationData = [
                'verified_by' => auth()->id(),
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ];

            $invoice = $this->invoiceService->verifyManualPayment($invoiceId, $verificationData);

            return response()->json([
                'message' => 'Payment verified and subscription activated',
                'invoice' => $invoice,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to verify payment', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId,
            ]);

            return response()->json([
                'message' => 'Failed to verify payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active platform payment methods
     */
    public function getPaymentMethods(): JsonResponse
    {
        try {
            $paymentMethods = $this->invoiceService->getActivePaymentMethods();

            return response()->json([
                'payment_methods' => $paymentMethods,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get payment methods', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get payment methods',
            ], 500);
        }
    }

    /**
     * Get payment method details (decrypted for admin use)
     */
    public function getPaymentMethodDetails(string $id): JsonResponse
    {
        try {
            $paymentMethod = PlatformPaymentMethod::findOrFail($id);

            return response()->json([
                'payment_method' => $paymentMethod,
                'details' => $paymentMethod->details, // Decrypted automatically by Laravel
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get payment method details', [
                'error' => $e->getMessage(),
                'payment_method_id' => $id,
            ]);

            return response()->json([
                'message' => 'Failed to get payment method details',
            ], 500);
        }
    }

    /**
     * Update platform payment method details (admin only)
     */
    public function updatePaymentMethod(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'details' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        try {
            $paymentMethod = PlatformPaymentMethod::findOrFail($id);

            $paymentMethod->update([
                'details' => $validated['details'] ?? $paymentMethod->details,
                'is_active' => $validated['is_active'] ?? $paymentMethod->is_active,
            ]);

            return response()->json([
                'message' => 'Payment method updated',
                'payment_method' => $paymentMethod->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update payment method', [
                'error' => $e->getMessage(),
                'payment_method_id' => $id,
            ]);

            return response()->json([
                'message' => 'Failed to update payment method: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get invoice details
     */
    public function getInvoice(string $invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::with('subscription.plan', 'subscription.provider', 'payments')
                ->findOrFail($invoiceId);

            return response()->json([
                'invoice' => $invoice,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get invoice', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId,
            ]);

            return response()->json([
                'message' => 'Failed to get invoice',
            ], 500);
        }
    }

    /**
     * Reject manual payment
     */
    public function rejectPayment(Request $request, string $invoiceId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        try {
            $invoice = $this->invoiceService->markAsFailed($invoiceId, $validated['reason']);

            return response()->json([
                'message' => 'Payment rejected',
                'invoice' => $invoice,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject payment', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId,
            ]);

            return response()->json([
                'message' => 'Failed to reject payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}

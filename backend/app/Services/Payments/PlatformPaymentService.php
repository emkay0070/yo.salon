<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PlatformPaymentService - Orchestrate invoice/payment/subscription flow
 * 
 * This service orchestrates the payment flow for platform subscriptions:
 * 1. Create Invoice
 * 2. Initialize Payment
 * 3. Verify Payment
 * 4. Activate Subscription
 * 
 * It delegates to InvoiceService and SubscriptionService for business logic.
 */
class PlatformPaymentService
{
    private PaymentProviderInterface $provider;
    private InvoiceService $invoiceService;
    private SubscriptionService $subscriptionService;

    public function __construct(
        PaymentProviderInterface $provider,
        InvoiceService $invoiceService,
        SubscriptionService $subscriptionService
    ) {
        $this->provider = $provider;
        $this->invoiceService = $invoiceService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Initialize payment for a subscription invoice.
     *
     * @param string $invoiceId
     * @param string $customerEmail
     * @param string $customerName
     * @param string|null $customerPhone
     * @return array
     */
    public function initializeSubscriptionPayment(
        string $invoiceId,
        string $customerEmail,
        string $customerName,
        ?string $customerPhone = null
    ): array {
        $invoice = Invoice::findOrFail($invoiceId);

        if ($invoice->status === 'paid') {
            throw new \Exception('Invoice is already paid');
        }

        $reference = 'SUB-' . strtoupper(Str::random(12));

        $paymentData = [
            'reference' => $reference,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'email' => $customerEmail,
            'customer_name' => $customerName,
            'phone' => $customerPhone,
            'title' => 'Yo.Salon Subscription Payment',
            'description' => "Payment for invoice {$invoice->invoice_number}",
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'subscription_id' => $invoice->subscription_id,
                'type' => 'subscription_payment',
            ],
            'redirect_url' => config('app.url') . '/billing/payment/callback',
        ];

        $result = $this->provider->initializePayment($paymentData);

        // Store the payment reference on the invoice
        $invoice->update([
            'metadata' => array_merge($invoice->metadata ?? [], [
                'payment_reference' => $result['reference'],
                'provider_reference' => $result['provider_reference'],
                'payment_provider' => $this->provider->getProviderName(),
            ]),
        ]);

        return $result;
    }

    /**
     * Verify a subscription payment and update the invoice/subscription.
     *
     * @param string $reference
     * @return array
     */
    public function verifySubscriptionPayment(string $reference): array
    {
        $verificationResult = $this->provider->verifyPayment($reference);

        if (!$verificationResult['success']) {
            return [
                'success' => false,
                'status' => $verificationResult['status'],
                'message' => 'Payment verification failed',
            ];
        }

        return DB::transaction(function () use ($verificationResult, $reference) {
            // Find invoice by payment reference in metadata
            $invoice = Invoice::where('metadata->payment_reference', $reference)->first();

            if (!$invoice) {
                throw new \Exception('Invoice not found for this payment reference');
            }

            if ($invoice->status === 'paid') {
                return [
                    'success' => true,
                    'status' => 'already_paid',
                    'message' => 'Invoice already paid',
                    'invoice' => $invoice,
                ];
            }

            // Mark invoice as paid using InvoiceService
            $paymentData = [
                'payment_method' => $verificationResult['payment_method'],
                'payment_gateway' => $this->provider->getProviderName(),
                'transaction_id' => $verificationResult['provider_reference'],
                'gateway_response' => $verificationResult['data'],
            ];

            $updatedInvoice = $this->invoiceService->markAsPaid($invoice->id, $verificationResult['payment_method'], $paymentData);

            // Get subscription from invoice
            $subscription = $invoice->subscription;

            return [
                'success' => true,
                'status' => 'completed',
                'invoice' => $updatedInvoice,
                'subscription' => $subscription,
                'verification_data' => $verificationResult,
            ];
        });
    }

    /**
     * Handle webhook callback for subscription payments.
     *
     * @param array $payload
     * @param string $signature
     * @return array
     */
    public function handleSubscriptionWebhook(array $payload, string $signature): array
    {
        $webhookData = $this->provider->handleWebhook($payload, $signature);

        $event = $webhookData['event'];
        $reference = $webhookData['reference'];

        // Only process successful payment events
        if (!in_array($event, ['charge.completed', 'transaction.successful'])) {
            return [
                'success' => true,
                'status' => 'ignored',
                'message' => 'Event not processed: ' . $event,
            ];
        }

        if ($webhookData['status'] !== 'successful' && $webhookData['status'] !== 'completed') {
            return [
                'success' => true,
                'status' => 'ignored',
                'message' => 'Payment not successful: ' . $webhookData['status'],
            ];
        }

        return $this->verifySubscriptionPayment($reference);
    }

    /**
     * Refund a subscription payment.
     *
     * @param string $invoiceId
     * @param float|null $amount
     * @return array
     */
    public function refundSubscriptionPayment(string $invoiceId, ?float $amount = null): array
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if ($invoice->status !== 'paid') {
            throw new \Exception('Invoice is not paid');
        }

        $paymentReference = $invoice->metadata['payment_reference'] ?? null;

        if (!$paymentReference) {
            throw new \Exception('No payment reference found on invoice');
        }

        $refundResult = $this->provider->refundPayment($paymentReference, $amount);

        if ($refundResult['success']) {
            // Update invoice status to refunded
            $invoice->update([
                'status' => 'refunded',
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'refund_reference' => $refundResult['reference'],
                    'refunded_at' => now()->toIso8601String(),
                    'refund_amount' => $refundResult['amount_refunded'],
                ]),
            ]);
        }

        return $refundResult;
    }
}

<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Services\FeeEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SalonPaymentService
{
    private PaymentProviderInterface $provider;
    private FeeEngine $feeEngine;

    public function __construct(
        PaymentProviderInterface $provider,
        FeeEngine $feeEngine
    ) {
        $this->provider = $provider;
        $this->feeEngine = $feeEngine;
    }

    /**
     * Initialize payment for a salon booking.
     *
     * @param string $bookingId
     * @param string $paymentMethodId
     * @param string $customerEmail
     * @param string $customerName
     * @param string|null $customerPhone
     * @return array
     */
    public function initializeBookingPayment(
        string $bookingId,
        string $paymentMethodId,
        string $customerEmail,
        string $customerName,
        ?string $customerPhone = null
    ): array {
        $booking = Booking::with(['salon', 'customer'])->find($bookingId);

        if (!$booking) {
            throw new \Exception('Booking not found');
        }

        $paymentMethod = PaymentMethod::find($paymentMethodId);

        if (!$paymentMethod) {
            throw new \Exception('Payment method not found');
        }

        if ($paymentMethod->salon_id !== $booking->salon_id) {
            throw new \Exception('Payment method does not belong to this salon');
        }

        // Calculate total from all services
        $totalPrice = $booking->services()->sum('price');
        
        // Calculate fees
        $fees = $this->feeEngine->calculateFees($totalPrice, $paymentMethod);

        $reference = 'SALON-' . strtoupper(Str::random(12));

        $paymentData = [
            'reference' => $reference,
            'amount' => $fees['gross_amount'],
            'currency' => 'UGX',
            'email' => $customerEmail,
            'customer_name' => $customerName,
            'phone' => $customerPhone,
            'title' => $booking->salon->name,
            'description' => "Payment for booking #{$booking->id}",
            'metadata' => [
                'booking_id' => $booking->id,
                'salon_id' => $booking->salon_id,
                'customer_id' => $booking->customer_id,
                'payment_method_id' => $paymentMethodId,
                'type' => 'booking_payment',
                'gross_amount' => $fees['gross_amount'],
                'gateway_fee' => $fees['gateway_fee'],
                'platform_fee' => $fees['platform_fee'],
                'tax_amount' => $fees['tax_amount'],
                'net_amount' => $fees['net_amount'],
            ],
            'redirect_url' => config('app.url') . "/salon/{$booking->salon_id}/payment/callback",
        ];

        // Customize payment options based on payment method type
        if ($paymentMethod->type === 'manual') {
            // For manual payment, return instructions instead of calling provider
            $paymentRequest = PaymentRequest::create([
                'salon_id' => $booking->salon_id,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $fees['gross_amount'],
                'status' => 'pending',
                'provider_reference' => $reference,
                'requested_at' => now(),
                'expires_at' => now()->addMinutes(60), // Manual payments have longer expiry
            ]);

            return [
                'success' => true,
                'type' => 'manual',
                'payment_request_id' => $paymentRequest->id,
                'reference' => $reference,
                'instructions' => [
                    'method' => $paymentMethod->display_name,
                    'phone' => $paymentMethod->account_identifier,
                    'amount' => $fees['gross_amount'],
                    'message' => "Please send {$fees['gross_amount']} UGX to {$paymentMethod->account_identifier} and upload your payment proof.",
                ],
            ];
        }

        if ($paymentMethod->type === 'offline') {
            // For offline payment (pay at salon), no payment initialization needed
            return [
                'success' => true,
                'type' => 'offline',
                'message' => 'Payment will be collected at the salon.',
            ];
        }

        // Only call provider for 'api' or 'gateway' type payment methods
        if ($paymentMethod->provider === 'flutterwave') {
            $paymentData['payment_options'] = 'card, mobilemoneyuganda, ussd, account';
        } elseif ($paymentMethod->provider === 'mtn') {
            $paymentData['payment_options'] = 'mobilemoneyuganda';
        } elseif ($paymentMethod->provider === 'airtel') {
            $paymentData['payment_options'] = 'mobilemoneyuganda';
        }

        $result = $this->provider->initializePayment($paymentData);

        // Create payment request
        $paymentRequest = PaymentRequest::create([
            'salon_id' => $booking->salon_id,
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'payment_method_id' => $paymentMethodId,
            'amount' => $fees['gross_amount'],
            'status' => 'sent',
            'provider_reference' => $result['provider_reference'],
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        // Update booking payment status
        $booking->update(['payment_status' => 'pending']);

        return array_merge($result, [
            'payment_request_id' => $paymentRequest->id,
        ]);
    }

    /**
     * Verify a salon payment and create transaction.
     *
     * @param string $reference
     * @return array
     */
    public function verifySalonPayment(string $reference): array
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
            // Find payment request by provider reference
            $paymentRequest = PaymentRequest::where('provider_reference', $reference)
                ->orWhere('metadata->payment_reference', $reference)
                ->first();

            if (!$paymentRequest) {
                throw new \Exception('Payment request not found for this reference');
            }

            if ($paymentRequest->status === 'paid') {
                return [
                    'success' => true,
                    'status' => 'already_paid',
                    'message' => 'Payment already processed',
                    'transaction' => $paymentRequest->transaction,
                ];
            }

            $paymentMethod = PaymentMethod::find($paymentRequest->payment_method_id);
            $fees = $this->feeEngine->calculateFees((float) $paymentRequest->amount, $paymentMethod);

            // Create transaction
            $transaction = Transaction::create([
                'salon_id' => $paymentRequest->salon_id,
                'booking_id' => $paymentRequest->booking_id,
                'customer_id' => $paymentRequest->customer_id,
                'payment_method_id' => $paymentRequest->payment_method_id,
                'type' => 'payment',
                'status' => 'completed',
                'gross_amount' => $fees['gross_amount'],
                'gateway_fee' => $fees['gateway_fee'],
                'platform_fee' => $fees['platform_fee'],
                'tax_amount' => $fees['tax_amount'],
                'net_amount' => $fees['net_amount'],
                'currency' => 'UGX',
                'internal_reference' => 'TXN-' . strtoupper(Str::random(10)),
                'provider_reference' => $verificationResult['provider_reference'],
                'paid_at' => now(),
            ]);

            // Update payment request
            $paymentRequest->update([
                'status' => 'paid',
                'completed_at' => now(),
            ]);

            // Update booking payment status
            if ($paymentRequest->booking_id) {
                Booking::where('id', $paymentRequest->booking_id)
                    ->update(['payment_status' => 'paid']);
            }

            return [
                'success' => true,
                'status' => 'completed',
                'transaction' => $transaction,
                'payment_request' => $paymentRequest->fresh(),
                'verification_data' => $verificationResult,
            ];
        });
    }

    /**
     * Handle webhook callback for salon payments.
     *
     * @param array $payload
     * @param string $signature
     * @return array
     */
    public function handleSalonWebhook(array $payload, string $signature): array
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

        return $this->verifySalonPayment($reference);
    }

    /**
     * Record a manual payment (cash, card terminal, etc.).
     *
     * @param array $data
     * @return array
     */
    public function recordManualPayment(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $bookingId = $data['booking_id'] ?? null;
            $customerId = $data['customer_id'] ?? null;
            $salonId = $data['salon_id'];
            $amount = $data['amount'];
            $paymentMethod = $data['payment_method'] ?? 'cash';

            // Get or create payment method
            $paymentMethodRecord = PaymentMethod::firstOrCreate(
                [
                    'salon_id' => $salonId,
                    'provider' => $paymentMethod,
                ],
                [
                    'type' => $paymentMethod === 'cash' ? 'cash' : 'card',
                    'display_name' => ucfirst($paymentMethod),
                    'is_active' => true,
                ]
            );

            // Calculate fees
            $fees = $this->feeEngine->calculateFees($amount, $paymentMethodRecord);

            // Create transaction
            $transaction = Transaction::create([
                'salon_id' => $salonId,
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodRecord->id,
                'type' => 'payment',
                'status' => 'completed',
                'gross_amount' => $fees['gross_amount'],
                'gateway_fee' => $fees['gateway_fee'],
                'platform_fee' => $fees['platform_fee'],
                'tax_amount' => $fees['tax_amount'],
                'net_amount' => $fees['net_amount'],
                'currency' => 'UGX',
                'internal_reference' => 'TXN-' . strtoupper(Str::random(10)),
                'provider_reference' => 'MANUAL-' . strtoupper(Str::random(8)),
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Update booking payment status
            if ($bookingId) {
                Booking::where('id', $bookingId)
                    ->update(['payment_status' => 'paid']);
            }

            return [
                'success' => true,
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * Refund a salon payment.
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     */
    public function refundSalonPayment(string $transactionId, ?float $amount = null): array
    {
        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            throw new \Exception('Transaction not found');
        }

        if ($transaction->status !== 'completed') {
            throw new \Exception('Transaction is not completed');
        }

        // For manual payments, just create a refund transaction
        if (str_starts_with($transaction->provider_reference, 'MANUAL-')) {
            return $this->createRefundTransaction($transaction, $amount);
        }

        // For digital payments, use provider
        $refundResult = $this->provider->refundPayment($transaction->provider_reference, $amount);

        if ($refundResult['success']) {
            $this->createRefundTransaction($transaction, $amount, $refundResult['reference']);
        }

        return $refundResult;
    }

    /**
     * Create a refund transaction record.
     *
     * @param Transaction $originalTransaction
     * @param float|null $amount
     * @param string|null $providerReference
     * @return array
     */
    private function createRefundTransaction(
        Transaction $originalTransaction,
        ?float $amount = null,
        ?string $providerReference = null
    ): array {
        $refundAmount = $amount ?? $originalTransaction->net_amount;

        $refundTransaction = Transaction::create([
            'salon_id' => $originalTransaction->salon_id,
            'booking_id' => $originalTransaction->booking_id,
            'customer_id' => $originalTransaction->customer_id,
            'payment_method_id' => $originalTransaction->payment_method_id,
            'type' => 'refund',
            'status' => 'completed',
            'gross_amount' => -$refundAmount,
            'gateway_fee' => 0,
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => -$refundAmount,
            'currency' => 'UGX',
            'internal_reference' => 'REF-' . strtoupper(Str::random(10)),
            'provider_reference' => $providerReference ?? 'MANUAL-REF-' . strtoupper(Str::random(8)),
            'paid_at' => now(),
            'notes' => 'Refund for transaction ' . $originalTransaction->internal_reference,
        ]);

        // Update original transaction metadata
        $originalTransaction->update([
            'metadata' => array_merge($originalTransaction->metadata ?? [], [
                'refunded' => true,
                'refund_transaction_id' => $refundTransaction->id,
                'refund_amount' => $refundAmount,
            ]),
        ]);

        return [
            'success' => true,
            'transaction' => $refundTransaction,
        ];
    }
}

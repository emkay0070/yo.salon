<?php

namespace App\Services\Payments;

use App\Events\PaymentConfirmed;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Services\FeeEngine;
use App\Services\BookingConfigurationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class BookingPaymentService
{
    private PaymentManager $paymentManager;
    private FeeEngine $feeEngine;
    private PaymentRoutingService $paymentRoutingService;
    private BookingConfigurationService $configService;

    public function __construct(
        PaymentManager $paymentManager,
        FeeEngine $feeEngine,
        PaymentRoutingService $paymentRoutingService,
        BookingConfigurationService $configService
    ) {
        $this->paymentManager = $paymentManager;
        $this->feeEngine = $feeEngine;
        $this->paymentRoutingService = $paymentRoutingService;
        $this->configService = $configService;
    }

    /**
     * Resolve the correct payment provider for a given payment method.
     *
     * @param PaymentMethod $paymentMethod
     * @return \App\Services\Payments\Contracts\PaymentProviderInterface
     * @throws \Exception if the provider is not registered
     */
    private function getProvider(PaymentMethod $paymentMethod): \App\Services\Payments\Contracts\PaymentProviderInterface
    {
        $provider = $this->paymentManager->getProvider($paymentMethod->provider);

        if (!$provider) {
            throw new \Exception("Payment provider not registered: {$paymentMethod->provider}");
        }

        // Configure per-salon credentials if available on the payment method
        $credentials = null;
        if ($paymentMethod->provider === 'mtn_momo') {
            $credentials = [
                'api_subscription_key' => $paymentMethod->api_subscription_key,
                'api_key' => $paymentMethod->api_key,
                'merchant_id' => $paymentMethod->merchant_id,
                'environment' => $paymentMethod->environment ?? 'sandbox',
            ];
        } elseif ($paymentMethod->provider === 'airtel') {
            $credentials = [
                'api_key' => $paymentMethod->api_key,
                'api_secret' => $paymentMethod->api_secret,
                'country' => $paymentMethod->country ?? 'UG',
                'currency' => $paymentMethod->currency ?? 'UGX',
                'environment' => $paymentMethod->environment ?? 'sandbox',
            ];
        }

        if ($credentials && method_exists($provider, 'setCredentials')) {
            $provider->setCredentials($credentials);
        }

        return $provider;
    }

    /**
     * Initialize payment for a booking.
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
        $booking = Booking::with(['location', 'customer'])->find($bookingId);

        if (!$booking) {
            throw new \Exception('Booking not found');
        }

        $paymentMethod = PaymentMethod::find($paymentMethodId);

        if (!$paymentMethod) {
            throw new \Exception('Payment method not found');
        }

        // Derive location from payment method's provider for comparison
        $provider = \App\Models\Provider::find($paymentMethod->provider_id);
        if ($provider && $booking->location_id) {
            $location = \App\Models\Location::find($booking->location_id);
            if ($location && $location->provider_id !== $provider->id) {
                throw new \Exception('Payment method does not belong to this location');
            }
        }

        // Calculate total from all services
        $totalPrice = $booking->services()->sum('price');
        
        // Calculate fees
        $fees = $this->feeEngine->calculateFees($totalPrice, $paymentMethod);

        $reference = 'BOOKING-' . strtoupper(Str::random(12));

        $paymentData = [
            'reference' => $reference,
            'amount' => $fees['gross_amount'],
            'currency' => 'NGN',
            'email' => $customerEmail,
            'customer_name' => $customerName,
            'phone' => $customerPhone,
            'title' => $booking->location->name,
            'description' => "Payment for booking #{$booking->id}",
            'metadata' => [
                'booking_id' => $booking->id,
                'location_id' => $booking->location_id,
                'customer_id' => $booking->customer_id,
                'payment_method_id' => $paymentMethodId,
                'type' => 'booking_payment',
                'gross_amount' => $fees['gross_amount'],
                'gateway_fee' => $fees['gateway_fee'],
                'platform_fee' => $fees['platform_fee'],
                'tax_amount' => $fees['tax_amount'],
                'net_amount' => $fees['net_amount'],
            ],
            'redirect_url' => config('app.url') . "/location/{$booking->location_id}/payment/callback",
        ];

        // Customize payment options based on payment method type
        if ($paymentMethod->type === 'manual') {
            // For manual payment, return instructions instead of calling provider
            $paymentRequest = PaymentRequest::create([
                'provider_id' => $paymentMethod->provider_id,
                'location_id' => $booking->location_id,
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
            // For offline payment (pay at location), no payment initialization needed
            return [
                'success' => true,
                'type' => 'offline',
                'message' => 'Payment will be collected at the location.',
            ];
        }

        // Only call provider for 'api', 'gateway', or 'mobile_money' type payment methods
        if ($paymentMethod->provider === 'flutterwave') {
            $paymentData['payment_options'] = 'card, mobilemoneyuganda, ussd, account';
        } elseif ($paymentMethod->provider === 'mtn') {
            $paymentData['payment_options'] = 'mobilemoneyuganda';
        } elseif ($paymentMethod->provider === 'airtel') {
            $paymentData['payment_options'] = 'mobilemoneyuganda';
        }

        $result = $this->getProvider($paymentMethod)->initializePayment($paymentData);

        // Create payment request
        $paymentRequest = PaymentRequest::create([
            'provider_id' => $paymentMethod->provider_id,
            'location_id' => $booking->location_id,
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
     * Verify a booking payment and create transaction.
     *
     * @param string $reference The provider's transaction reference
     * @param array|null $verificationResult Optional pre-verified result (for webhook-initiated verification)
     * @return array
     */
    public function verifyBookingPayment(string $reference, ?array $verificationResult = null): array
    {
        // Find payment request first to determine the correct payment provider
        $paymentRequest = PaymentRequest::where('provider_reference', $reference)->first();

        if (!$paymentRequest) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'Payment request not found for this reference',
            ];
        }

        $paymentMethod = PaymentMethod::find($paymentRequest->payment_method_id);

        if (!$paymentMethod) {
            return [
                'success' => false,
                'status' => 'payment_method_not_found',
                'message' => 'Payment method not found',
            ];
        }

        // If no pre-verified result (e.g., client-initiated verification), call the provider
        if ($verificationResult === null) {
            $provider = $this->getProvider($paymentMethod);
            $verificationResult = $provider->verifyPayment($reference);

            if (!$verificationResult['success']) {
                return [
                    'success' => false,
                    'status' => $verificationResult['status'],
                    'message' => 'Payment verification failed',
                ];
            }
        }

        $result = DB::transaction(function () use ($verificationResult, $reference) {
            // Find payment request by provider reference
            $paymentRequest = PaymentRequest::where('provider_reference', $reference)->first();

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

            // Reject payment if payment request has expired
            if ($paymentRequest->status === 'expired') {
                return $this->handleLatePayment($paymentRequest, $verificationResult, 'expired');
            }

            // Reject payment if payment request expiry time has passed
            if ($paymentRequest->expires_at && $paymentRequest->expires_at < now()) {
                // Mark as expired if not already
                if (!in_array($paymentRequest->status, ['expired', 'paid'])) {
                    $paymentRequest->update([
                        'status' => 'expired',
                        'completed_at' => now(),
                    ]);
                }
                return $this->handleLatePayment($paymentRequest, $verificationResult, 'expired');
            }

            // Reject payment if associated booking has been cancelled
            if ($paymentRequest->booking_id) {
                $booking = Booking::find($paymentRequest->booking_id);
                if ($booking && $booking->status === 'cancelled') {
                    return $this->handleLatePayment($paymentRequest, $verificationResult, 'booking_cancelled');
                }
            }

            $paymentMethod = PaymentMethod::find($paymentRequest->payment_method_id);
            
            // Use provider's actual fees if available, otherwise use default zero fees
            if (isset($verificationResult['fees'])) {
                $fees = $this->feeEngine->calculateFeesFromProvider(
                    (float) $paymentRequest->amount,
                    $paymentMethod,
                    $verificationResult
                );
            } else {
                $fees = $this->feeEngine->calculateFees((float) $paymentRequest->amount, $paymentMethod);
            }
            
            // Determine payment account
            $paymentAccount = $this->paymentRoutingService->determinePaymentAccount($paymentMethod);

            // Create transaction
            $transaction = Transaction::create([
                'provider_id' => $paymentRequest->provider_id,
                'booking_id' => $paymentRequest->booking_id,
                'customer_id' => $paymentRequest->customer_id,
                'payment_method_id' => $paymentRequest->payment_method_id,
                'payment_account_id' => $paymentAccount?->id,
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
                $booking = Booking::find($paymentRequest->booking_id);
                if ($booking) {
                    $booking->update(['payment_status' => 'paid']);

                    // Transition booking status based on payment completion and approval policy
                    // Only transition from pending_payment to avoid disrupting other states
                    if ($booking->status === 'pending_payment') {
                        $requiresApproval = $this->configService->requiresApproval(
                            $booking->service_id,
                            $booking->provider_id,
                            $booking->location_id
                        );

                        if ($requiresApproval) {
                            // Manual approval required: move to pending state
                            $booking->update(['status' => 'pending']);
                        } else {
                            // No approval required: confirm the booking
                            $booking->update(['status' => 'confirmed']);
                        }
                    }
                }
            }

            return [
                'success' => true,
                'status' => 'completed',
                'message' => 'Payment verified successfully',
                'transaction' => $transaction,
                'payment_request_id' => $paymentRequest->id,
            ];
        });

        // Dispatch PaymentConfirmed event AFTER payment transaction commits
        // Skip in testing environment to prevent finance domain listener errors from aborting test transaction
        if ($result['success'] && $result['status'] === 'completed' && app()->environment() !== 'testing') {
            try {
                $paymentRequest = PaymentRequest::find($result['payment_request_id']);
                $transaction = $result['transaction'];
                $booking = Booking::find($paymentRequest->booking_id);
                if ($booking) {
                    Event::dispatch(new PaymentConfirmed(
                        $booking,
                        $transaction,
                        $booking->customer_id
                    ));
                }
            } catch (\Throwable $e) {
                // Log but don't throw - payment is committed, listeners are secondary
                \Log::error('PaymentConfirmed event listener error (non-blocking)', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Handle webhook callback for booking payments.
     *
     * @param array $payload
     * @param string $signature
     * @return array
     */
    public function handleBookingWebhook(array $payload, string $signature): array
    {
        $webhookData = $this->paymentManager->getProvider('flutterwave')->handleWebhook($payload, $signature);

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

        return $this->verifyBookingPayment($reference);
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
            $locationId = $data['location_id'];
            $amount = $data['amount'];
            $paymentMethod = $data['payment_method'] ?? 'cash';

            // Get provider from location for payment method
            $location = \App\Models\Location::find($locationId);
            if (!$location) {
                throw new \Exception('Location not found');
            }
            $providerId = $location->provider_id;

            // Get or create payment method
            $paymentMethodRecord = PaymentMethod::firstOrCreate(
                [
                    'provider_id' => $providerId,
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
            
            // Determine payment account
            $paymentAccount = $this->paymentRoutingService->determinePaymentAccount($paymentMethodRecord);

            // Create transaction
            $transaction = Transaction::create([
                'provider_id' => $providerId,
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodRecord->id,
                'payment_account_id' => $paymentAccount?->id,
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
     * Refund a booking payment.
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     */
    public function refundBookingPayment(string $transactionId, ?float $amount = null): array
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
        $paymentMethod = PaymentMethod::find($transaction->payment_method_id);
        $provider = $this->getProvider($paymentMethod);
        $refundResult = $provider->refundPayment($transaction->provider_reference, $amount);

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
            'provider_id' => $originalTransaction->provider_id,
            'booking_id' => $originalTransaction->booking_id,
            'customer_id' => $originalTransaction->customer_id,
            'payment_method_id' => $originalTransaction->payment_method_id,
            'payment_account_id' => $originalTransaction->payment_account_id,
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

        // Dispatch RefundConfirmed event to trigger Finance domain processing
        // Skip in testing environment to prevent finance domain listener errors from aborting test transaction
        if (app()->environment() !== 'testing') {
            $booking = $originalTransaction->booking;
            if ($booking) {
                try {
                    event(new \App\Events\RefundConfirmed(
                        $originalTransaction,
                        $refundTransaction,
                        $booking,
                        $originalTransaction->customer_id,
                        $refundAmount,
                        'UGX'
                    ));
                } catch (\Throwable $e) {
                    // Log but don't throw - payment is committed, listeners are secondary
                    \Log::error('RefundConfirmed event listener error (non-blocking)', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        return [
            'success' => true,
            'transaction' => $refundTransaction,
        ];
    }

    /**
     * Handle a late payment that arrived after expiry or booking cancellation.
     * Rejects the payment — no transaction is created, booking is left unchanged.
     * The expired payment request remains as the trace for reconciliation.
     *
     * @param PaymentRequest $paymentRequest
     * @param array $verificationResult
     * @param string $rejectionReason
     * @return array
     */
    private function handleLatePayment(PaymentRequest $paymentRequest, array $verificationResult, string $rejectionReason): array
    {
        // Idempotency: if already processed, return consistent result
        if ($paymentRequest->status === 'paid') {
            return [
                'success' => false,
                'status' => $rejectionReason,
                'message' => 'Payment already processed',
            ];
        }

        // Log the late payment attempt for reconciliation traceability
        \Log::warning('Late payment rejected', [
            'payment_request_id' => $paymentRequest->id,
            'booking_id' => $paymentRequest->booking_id,
            'rejection_reason' => $rejectionReason,
            'provider_reference' => $verificationResult['reference'] ?? null,
            'amount' => $paymentRequest->amount,
        ]);

        // Reject the late payment — no transaction created, booking unchanged
        return [
            'success' => false,
            'status' => $rejectionReason,
            'message' => "Payment received late: {$rejectionReason}",
        ];
    }
}

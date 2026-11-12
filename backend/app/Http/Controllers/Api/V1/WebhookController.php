<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PlatformPaymentService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\FlutterwaveProvider;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private PlatformPaymentService $platformPaymentService;
    private BookingPaymentService $bookingPaymentService;
    private FlutterwaveProvider $flutterwaveProvider;
    private PaymentManager $paymentManager;

    public function __construct(
        PlatformPaymentService $platformPaymentService,
        BookingPaymentService $bookingPaymentService,
        FlutterwaveProvider $flutterwaveProvider,
        PaymentManager $paymentManager
    ) {
        $this->platformPaymentService = $platformPaymentService;
        $this->bookingPaymentService = $bookingPaymentService;
        $this->flutterwaveProvider = $flutterwaveProvider;
        $this->paymentManager = $paymentManager;
    }

    /**
     * Handle Flutterwave webhook callbacks.
     * This endpoint handles both platform (subscription) and booking payments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleFlutterwave(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('verif-hash');

        // Validate webhook signature
        if (!$this->flutterwaveProvider->validateWebhookSignature($payload, $signature)) {
            Log::warning('Invalid Flutterwave webhook signature', [
                'ip' => $request->ip(),
                'payload' => $payload,
            ]);
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        Log::info('Flutterwave webhook received', [
            'event' => $event,
            'reference' => $data['tx_ref'] ?? null,
        ]);

        try {
            // Determine payment type from metadata
            $paymentType = $data['metadata']['type'] ?? null;

            if ($paymentType === 'subscription_payment') {
                // Platform payment (B2B - subscription)
                $result = $this->platformPaymentService->handleSubscriptionWebhook($payload, $signature);
            } elseif ($paymentType === 'booking_payment') {
                // Booking payment (B2C - customer booking)
                $result = $this->bookingPaymentService->handleBookingWebhook($payload, $signature);
            } else {
                // Unknown payment type, log and ignore
                Log::warning('Unknown payment type in webhook', [
                    'payment_type' => $paymentType,
                    'reference' => $data['tx_ref'] ?? null,
                ]);
                return response()->json(['message' => 'Unknown payment type'], 200);
            }

            Log::info('Flutterwave webhook processed successfully', [
                'event' => $event,
                'result' => $result,
            ]);

            return response()->json(['message' => 'Webhook processed successfully'], 200);

        } catch (\Exception $e) {
            Log::error('Flutterwave webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle platform subscription payment webhooks specifically.
     * This is a dedicated endpoint for platform payments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePlatformWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('verif-hash');

        if (!$this->flutterwaveProvider->validateWebhookSignature($payload, $signature)) {
            Log::warning('Invalid platform webhook signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        try {
            $result = $this->platformPaymentService->handleSubscriptionWebhook($payload, $signature);
            return response()->json(['message' => 'Platform webhook processed'], 200);
        } catch (\Exception $e) {
            Log::error('Platform webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle booking payment webhooks specifically.
     * This is a dedicated endpoint for booking payments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleBookingWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('verif-hash');

        if (!$this->flutterwaveProvider->validateWebhookSignature($payload, $signature)) {
            Log::warning('Invalid booking webhook signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        try {
            $result = $this->bookingPaymentService->handleBookingWebhook($payload, $signature);
            return response()->json(['message' => 'Booking webhook processed'], 200);
        } catch (\Exception $e) {
            Log::error('Booking webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle MTN MoMo webhook callbacks.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleMTN(Request $request): JsonResponse
    {
        $payload  = $request->all();
        $signature = $request->header('X-Reference-Id');

        Log::info('MTN MoMo webhook received', [
            'payload'   => $payload,
            'signature' => $signature,
        ]);

        try {
            // Parse and validate webhook via PaymentManager (signature verification included)
            $result = $this->paymentManager->handleWebhook('mtn_momo', $payload, $signature);

            // Distinguish provider-level errors (no event key) from webhook payloads (has event key)
            if (empty($result['event']) && !empty($result['status']) && $result['status'] === 'failed') {
                Log::warning('MTN webhook rejected by provider', [
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
                return response()->json(['message' => $result['message'] ?? 'Webhook rejected'], 400);
            }

            $event = $result['event'] ?? null;
            $reference = $result['reference'] ?? null;
            $status = $result['status'] ?? null;

            // Only process successful payment events
            if (!in_array($event, ['transaction.updated'])) {
                Log::warning('MTN webhook event not processed', [
                    'event' => $event,
                    'reference' => $reference,
                ]);
                return response()->json(['message' => 'Event not processed: ' . $event], 200);
            }

            if ($status !== 'successful' && $status !== 'completed') {
                // Failed/expired/cancelled payments: mark the request as failed, no transaction
                $paymentRequest = \App\Models\PaymentRequest::where('provider_reference', $reference)->first();
                if ($paymentRequest) {
                    $paymentRequest->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                    ]);
                }
                return response()->json(['message' => 'Payment not successful: ' . $status], 200);
            }

            // Verify and record the payment through BookingPaymentService.
            // Pass the webhook data as the verification result to avoid a redundant
            // external API call — the webhook IS the provider's confirmation.
            $data = $result['data'] ?? [];
            $verificationResult = [
                'success' => true,
                'status' => 'successful',
                'reference' => $reference,
                'provider_reference' => $data['financialTransactionId'] ?? $reference,
                'amount' => $data['amount'] ?? null,
                'currency' => 'UGX',
                'fees' => 0,
            ];

            $verifyResult = $this->bookingPaymentService->verifyBookingPayment($reference, $verificationResult);

            Log::info('MTN webhook processed successfully', [
                'reference' => $reference,
                'result' => $verifyResult,
            ]);

            return response()->json([
                'message' => 'MTN webhook processed successfully',
                'result' => $verifyResult,
            ], 200);

        } catch (\Exception $e) {
            Log::error('MTN webhook processing failed', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }
}

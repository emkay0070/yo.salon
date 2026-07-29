<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PlatformPaymentService;
use App\Services\Payments\SalonPaymentService;
use App\Services\Payments\FlutterwaveProvider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private PlatformPaymentService $platformPaymentService;
    private SalonPaymentService $salonPaymentService;
    private FlutterwaveProvider $flutterwaveProvider;

    public function __construct(
        PlatformPaymentService $platformPaymentService,
        SalonPaymentService $salonPaymentService,
        FlutterwaveProvider $flutterwaveProvider
    ) {
        $this->platformPaymentService = $platformPaymentService;
        $this->salonPaymentService = $salonPaymentService;
        $this->flutterwaveProvider = $flutterwaveProvider;
    }

    /**
     * Handle Flutterwave webhook callbacks.
     * This endpoint handles both platform (subscription) and salon (booking) payments.
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
                // Salon payment (B2C - customer booking)
                $result = $this->salonPaymentService->handleSalonWebhook($payload, $signature);
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
     * Handle salon booking payment webhooks specifically.
     * This is a dedicated endpoint for salon payments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleSalonWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('verif-hash');

        if (!$this->flutterwaveProvider->validateWebhookSignature($payload, $signature)) {
            Log::warning('Invalid salon webhook signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        try {
            $result = $this->salonPaymentService->handleSalonWebhook($payload, $signature);
            return response()->json(['message' => 'Salon webhook processed'], 200);
        } catch (\Exception $e) {
            Log::error('Salon webhook processing failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }
}

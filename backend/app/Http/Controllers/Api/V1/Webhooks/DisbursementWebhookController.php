<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Payout\Payout;
use App\Models\PaymentMethod;
use App\Services\Payments\Factories\DisbursementProviderFactory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DisbursementWebhookController extends Controller
{
    /**
     * Handle MTN Disbursement webhooks
     */
    public function handleMtn(Request $request): JsonResponse
    {
        return $this->processWebhook('mtn', $request);
    }

    /**
     * Handle Airtel Disbursement webhooks (for future)
     */
    public function handleAirtel(Request $request): JsonResponse
    {
        return $this->processWebhook('airtel', $request);
    }

    /**
     * Generic webhook processor
     */
    private function processWebhook(string $providerName, Request $request): JsonResponse
    {
        $payload = $request->all();
        // In production, signature would come from headers
        $signature = $request->header('X-Signature', ''); 

        Log::info("Disbursement Webhook Received [{$providerName}]", [
            'payload' => $payload
        ]);

        try {
            $provider = DisbursementProviderFactory::make($providerName);
            
            $normalized = $provider->handleWebhook($payload, $signature);
            
            $reference = $normalized['reference'] ?? null;
            $payloadStatus = $normalized['status'] ?? 'processing'; // completed, failed, processing

            if (!$reference) {
                Log::warning("Disbursement Webhook missing reference [{$providerName}]");
                return response()->json(['message' => 'Missing reference'], 400);
            }

            // Find the payout.
            // Prefer idempotency_key (UUID) since that's what we pass as externalId in automated payouts.
            // Fall back to the 'reference' column to support legacy/manual payouts that use OUT-* references.
            $payout = Payout::where('idempotency_key', $reference)->first()
                ?? Payout::where('reference', $reference)->first();

            if (!$payout) {
                Log::warning("Disbursement Webhook payout not found [{$providerName}]", ['reference' => $reference]);
                return response()->json(['message' => 'Payout not found'], 404);
            }

            // MTN disbursement callbacks are not signed. Confirm the final
            // status with the provider API before mutating the payout; a forged
            // callback must not be able to complete a transfer that never ran.
            $status = $providerName === 'mtn'
                ? $this->verifyMtnStatus($payout, $reference)
                : $payloadStatus;

            if ($status === null) {
                Log::warning("Disbursement Webhook status could not be confirmed [{$providerName}]", [
                    'reference' => $reference,
                    'payout_id' => $payout->id,
                ]);

                return response()->json([
                    'message' => 'Webhook received; provider status not confirmed',
                ], 200);
            }

            if ($status === 'completed') {
                $payout->markAsCompleted($reference);
                Log::info("Disbursement Payout Completed", ['payout_id' => $payout->id]);
            } elseif ($status === 'failed') {
                $payout->markAsFailed('Failed via webhook callback');
                Log::info("Disbursement Payout Failed", ['payout_id' => $payout->id]);
            }

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            Log::error("Disbursement Webhook Error [{$providerName}]: " . $e->getMessage());
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }

    /**
     * Resolve the authoritative MTN status for a payout reference.
     * Returns null when the status cannot be verified.
     */
    private function verifyMtnStatus(Payout $payout, string $reference): ?string
    {
        $settlement = $payout->settlement;
        $payable = $settlement?->payable;

        if (!$payable) {
            Log::warning('Disbursement webhook payout has no payable settlement', [
                'payout_id' => $payout->id,
            ]);
            return null;
        }

        $providerId = $payable->provider_id ?? $payable->id;

        $config = PaymentMethod::where('provider_id', $providerId)
            ->where('provider', 'mtn_disbursement')
            ->where('is_active', true)
            ->first();

        if (!$config) {
            Log::warning('MTN disbursement configuration not found for webhook verification', [
                'payout_id' => $payout->id,
                'provider_id' => $providerId,
            ]);
            return null;
        }

        $provider = DisbursementProviderFactory::make('mtn');

        if (method_exists($provider, 'setCredentials')) {
            $provider->setCredentials([
                'api_subscription_key' => $config->api_subscription_key,
                'api_key'              => $config->api_key,
                'merchant_id'          => $config->merchant_id,
                'environment'          => $config->environment,
            ]);
        }

        $result = $provider->checkStatus($reference);

        if (!$result->success) {
            Log::warning('MTN disbursement status verification failed', [
                'payout_id' => $payout->id,
                'reference' => $reference,
                'message' => $result->message,
            ]);
            return null;
        }

        return $result->status;
    }
}

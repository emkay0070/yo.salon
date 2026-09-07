<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Payout\Payout;
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
            $status = $normalized['status'] ?? 'processing'; // completed, failed, processing

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
}

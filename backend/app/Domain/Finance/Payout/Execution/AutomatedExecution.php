<?php

namespace App\Domain\Finance\Payout\Execution;

use App\Domain\Finance\Payout\Payout;
use App\Models\PaymentProfile;
use App\Services\Payments\Factories\DisbursementProviderFactory;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AutomatedExecution implements ExecutionStrategy
{
    public function supports(string $method): bool
    {
        // For V1, we only allow automated execution if it's not cash.
        return $method !== 'cash'; 
    }

    public function execute(Payout $payout, array $payload = []): void
    {
        // 1. Resolve Recipient Profile
        // If not explicitly provided in payload, find the default profile for this method
        $profileId = $payload['payment_profile_id'] ?? null;
        
        if ($profileId) {
            $profile = PaymentProfile::find($profileId);
        } else {
            $profile = PaymentProfile::where('owner_type', $payout->recipient_type)
                ->where('owner_id', $payout->recipient_id)
                ->where('method', $payout->method)
                ->orderByDesc('is_default') // Prefer default
                ->first();
        }

        if (!$profile) {
            throw new InvalidArgumentException("No valid payment profile found for automated {$payout->method} payout.");
        }

        // 2. Resolve Provider via Factory
        try {
            $provider = DisbursementProviderFactory::make($payout->method);

            // Resolve the salon from the settlement's payable.
            // Settlement::payable is the entity that OWES the money (the Salon).
            $settlement = $payout->settlement;
            $payable = $settlement->payable;

            // Determine the salon_id — payable is usually a Salon, but could be a Provider.
            // Prefer a direct salon_id if the payable exposes one, otherwise use its ID directly.
            $salonId = $payable->salon_id ?? $payable->id;

            // Look up the salon's disbursement configuration.
            // We reuse the PaymentMethod model, with provider = '{method}_disbursement'
            $config = \App\Models\PaymentMethod::where('salon_id', $salonId)
                ->where('provider', $payout->method . '_disbursement')
                ->where('is_active', true)
                ->first();

            if (!$config) {
                throw new RuntimeException("Disbursement configuration not found for salon [{$salonId}]. Please configure a {$payout->method} disbursement method.");
            }

            if (method_exists($provider, 'setCredentials')) {
                $provider->setCredentials([
                    'api_subscription_key' => $config->api_subscription_key,
                    'api_key'              => $config->api_key,
                    'merchant_id'          => $config->merchant_id,
                    'environment'          => $config->environment,
                ]);
            }

        } catch (\Exception $e) {
            throw new RuntimeException("Disbursement provider not available: " . $e->getMessage());
        }

        // 3. Generate internal reference
        $internalReference = 'OUT-' . strtoupper(Str::random(12));
        
        // Ensure idempotency_key is always set — it may be null if the payout was created
        // outside PayoutService (e.g. tests, legacy manual payouts).
        $idempotencyKey = $payout->idempotency_key ?? (string) \Illuminate\Support\Str::uuid();
        
        $payout->update([
            'reference' => $internalReference,
            'idempotency_key' => $idempotencyKey,
        ]);

        // 4. Dispatch API request
        // The idempotency_key (UUID) is sent to the provider to strictly enforce idempotency.
        $response = $provider->transfer(
            profile: $profile,
            amount: $payout->amount,
            currency: $payout->currency,
            reference: $idempotencyKey, // Used as X-Reference-Id/externalId
            description: "Payout for settlement {$payout->settlement_id}"
        );

        // 5. Update payout status
        if ($response->success) {
            $payout->update([
                'status'             => $response->status ?? 'processing',
                'provider'           => $payout->method,
                'provider_reference' => $response->providerReference ?? null,
            ]);
            // Completion happens asynchronously via Webhook in Automated mode.
        } else {
            $payout->markAsFailed($response->message ?: 'Provider transfer failed');
        }
    }
}

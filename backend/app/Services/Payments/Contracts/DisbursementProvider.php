<?php

namespace App\Services\Payments\Contracts;

use App\Models\PaymentProfile;
use App\Services\Payments\DTOs\PaymentResponseDTO;

/**
 * DisbursementProvider abstracts the underlying API (MTN, Airtel, Bank)
 * for automated payouts.
 */
interface DisbursementProvider
{
    /**
     * Initiate a transfer to the given payment profile.
     * 
     * @param PaymentProfile $profile The destination
     * @param float $amount The amount to send
     * @param string $currency The currency to send in
     * @param string $reference A unique internal reference for this transfer
     * @param string $description Optional description
     * @return PaymentResponseDTO
     */
    public function transfer(
        PaymentProfile $profile,
        float $amount,
        string $currency,
        string $reference,
        string $description = ''
    ): PaymentResponseDTO;

    /**
     * Check the status of a previously initiated transfer.
     * 
     * @param string $providerReference The reference returned by the provider
     * @return PaymentResponseDTO
     */
    public function checkStatus(string $providerReference): PaymentResponseDTO;

    /**
     * Validate a webhook payload and return normalized status.
     * 
     * @param array $payload The raw webhook data
     * @param string $signature The signature for verification
     * @return array [ 'event' => ..., 'reference' => ..., 'status' => ... ]
     */
    public function handleWebhook(array $payload, string $signature): array;
}

<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentProfile;
use App\Services\Payments\Contracts\DisbursementProvider;
use App\Services\Payments\DTOs\PaymentResponseDTO;

/**
 * Stub boundary for Airtel Disbursement.
 */
class AirtelDisbursementProvider implements DisbursementProvider
{
    public function transfer(
        PaymentProfile $profile,
        float $amount,
        string $currency,
        string $reference,
        string $description = ''
    ): PaymentResponseDTO {
        // TODO: Implement Airtel B2C API
        return PaymentResponseDTO::failure('Airtel disbursement not yet configured');
    }

    public function checkStatus(string $providerReference): PaymentResponseDTO
    {
        return PaymentResponseDTO::failure('Airtel disbursement not yet configured');
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        return [
            'event' => 'transfer.updated',
            'reference' => null,
            'status' => 'failed',
        ];
    }
}

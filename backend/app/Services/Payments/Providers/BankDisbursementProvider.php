<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentProfile;
use App\Services\Payments\Contracts\DisbursementProvider;
use App\Services\Payments\DTOs\PaymentResponseDTO;

/**
 * Stub boundary for Bank Disbursement (e.g. Flutterwave, Interswitch, direct bank API).
 */
class BankDisbursementProvider implements DisbursementProvider
{
    public function transfer(
        PaymentProfile $profile,
        float $amount,
        string $currency,
        string $reference,
        string $description = ''
    ): PaymentResponseDTO {
        // TODO: Implement Bank Transfer API
        return PaymentResponseDTO::failure('Bank disbursement not yet configured');
    }

    public function checkStatus(string $providerReference): PaymentResponseDTO
    {
        return PaymentResponseDTO::failure('Bank disbursement not yet configured');
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

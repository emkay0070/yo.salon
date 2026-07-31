<?php

namespace App\Services\Payments\DTOs;

class PaymentResponseDTO
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $providerReference = null,
        public readonly ?string $message = null,
        public readonly ?array $rawResponse = null,
    ) {}

    public static function success(array $data): self
    {
        return new self(
            success: true,
            status: $data['status'] ?? 'pending',
            transactionId: $data['transaction_id'] ?? null,
            providerReference: $data['provider_reference'] ?? null,
            message: $data['message'] ?? null,
            rawResponse: $data,
        );
    }

    public static function failure(string $message, array $rawResponse = null): self
    {
        return new self(
            success: false,
            status: 'failed',
            message: $message,
            rawResponse: $rawResponse,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'transaction_id' => $this->transactionId,
            'provider_reference' => $this->providerReference,
            'message' => $this->message,
            'raw_response' => $this->rawResponse,
        ];
    }
}

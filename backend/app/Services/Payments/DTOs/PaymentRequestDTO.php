<?php

namespace App\Services\Payments\DTOs;

class PaymentRequestDTO
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $phoneNumber,
        public readonly string $reference,
        public readonly ?string $customerName = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount: $data['amount'],
            currency: $data['currency'] ?? 'UGX',
            phoneNumber: $data['phone_number'],
            reference: $data['reference'],
            customerName: $data['customer_name'] ?? null,
            customerEmail: $data['customer_email'] ?? null,
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'phone_number' => $this->phoneNumber,
            'reference' => $this->reference,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'description' => $this->description,
        ];
    }
}

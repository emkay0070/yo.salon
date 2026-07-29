<?php

namespace App\Services\Payments;

interface PaymentProviderInterface
{
    /**
     * Initialize a payment transaction.
     *
     * @param array $data Payment data including amount, currency, customer info, metadata
     * @return array Response with payment link, reference, and provider data
     */
    public function initializePayment(array $data): array;

    /**
     * Verify a payment transaction using the provider reference.
     *
     * @param string $reference The provider's transaction reference
     * @return array Payment verification status and details
     */
    public function verifyPayment(string $reference): array;

    /**
     * Process a webhook callback from the payment provider.
     *
     * @param array $payload The webhook payload from the provider
     * @param string $signature The webhook signature for verification
     * @return array Processed webhook data
     */
    public function handleWebhook(array $payload, string $signature): array;

    /**
     * Refund a payment transaction.
     *
     * @param string $reference The original transaction reference
     * @param float|null $amount Amount to refund (null for full refund)
     * @return array Refund transaction details
     */
    public function refundPayment(string $reference, ?float $amount = null): array;

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Validate webhook signature.
     *
     * @param array $payload The webhook payload
     * @param string $signature The webhook signature
     * @return bool
     */
    public function validateWebhookSignature(array $payload, string $signature): bool;
}

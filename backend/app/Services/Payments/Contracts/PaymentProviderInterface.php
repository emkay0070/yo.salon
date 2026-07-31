<?php

namespace App\Services\Payments\Contracts;

interface PaymentProviderInterface
{
    /**
     * Request a payment from the customer
     *
     * @param array $data
     * @return array
     */
    public function requestPayment(array $data): array;

    /**
     * Check the status of a payment
     *
     * @param string $transactionId
     * @return array
     */
    public function checkPaymentStatus(string $transactionId): array;

    /**
     * Handle webhook callback from payment provider
     *
     * @param array $payload
     * @return bool
     */
    public function handleWebhook(array $payload): bool;

    /**
     * Verify webhook signature
     *
     * @param array $payload
     * @param string $signature
     * @return bool
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool;

    /**
     * Get provider name
     *
     * @return string
     */
    public function getProviderName(): string;
}

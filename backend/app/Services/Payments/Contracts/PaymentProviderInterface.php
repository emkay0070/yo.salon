<?php

namespace App\Services\Payments\Contracts;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Services\Payments\DTOs\PaymentResponseDTO;

interface PaymentProviderInterface
{
    /**
     * Initialize a payment request.
     * Used for one-off charges and add-on purchases.
     */
    public function initializePayment(array $data): array;

    /**
     * Verify a payment transaction using the provider reference.
     */
    public function verifyPayment(string $reference): array;

    /**
     * Attempt to collect payment for a finalized, frozen invoice.
     *
     * The provider decides HOW to collect based on the payment method:
     *   - Flutterwave: use tokenized card charge
     *   - MTN MoMo:    send MoMo push prompt
     *   - Manual/Cash: return 'unsupported' — billing engine falls back to notification
     *
     * The billing engine must NEVER attempt this on a draft invoice.
     * The invoice must be finalized (frozen) before calling this method.
     */
    public function attemptInvoiceCollection(Invoice $invoice, PaymentMethod $method): PaymentResponseDTO;

    /**
     * Return an array of capabilities this provider supports.
     * The billing engine checks capabilities before calling provider-specific methods.
     *
     * Possible capability keys:
     *   'tokenized_charging' — can charge a saved card/token automatically
     *   'momo_push'          — can send a MoMo push prompt
     *   'refunds'            — supports payment refunds
     *   'webhooks'           — sends webhook callbacks
     *   'split_payments'     — supports Flutterwave subaccount splits
     *
     * @return array<string, bool>
     */
    public function getCapabilities(): array;

    /**
     * Handle and parse a webhook callback from the payment provider.
     *
     * Validates the signature, parses the payload, and returns a structured
     * response that the service layer can act on.
     *
     * Returns an array with keys:
     *   'event'     — provider event string (e.g. 'charge.completed')
     *   'reference' — transaction reference
     *   'status'    — payment status (e.g. 'successful', 'failed')
     *   'data'      — raw provider data
     *
     * @throws \Exception if signature is invalid
     */
    public function handleWebhook(array $payload, string $signature): array;

    /**
     * Validate webhook signature.
     */
    public function validateWebhookSignature(array $payload, string $signature): bool;

    /**
     * Get the provider identifier string.
     */
    public function getProviderName(): string;
}

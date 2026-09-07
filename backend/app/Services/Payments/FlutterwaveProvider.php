<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\DTOs\PaymentResponseDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveProvider implements PaymentProviderInterface
{
    private ?string $secretKey;
    private ?string $publicKey;
    private string $baseUrl;
    private ?string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key');
        $this->publicKey = config('services.flutterwave.public_key');
        $this->baseUrl = config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3');
        $this->webhookSecret = config('services.flutterwave.webhook_secret');
    }

    public function initializePayment(array $data): array
    {
        $payload = [
            'tx_ref' => $data['reference'] ?? $this->generateReference(),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'UGX',
            'payment_options' => $data['payment_options'] ?? 'card, mobilemoneyuganda, ussd',
            'redirect_url' => $data['redirect_url'] ?? config('app.url') . '/payment/callback',
            'customer' => [
                'email' => $data['email'] ?? null,
                'name' => $data['customer_name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ],
            'customizations' => [
                'title' => $data['title'] ?? 'Payment',
                'description' => $data['description'] ?? '',
                'logo' => $data['logo'] ?? null,
            ],
            'metadata' => $data['metadata'] ?? [],
        ];

        // Filter out null values
        $payload = array_filter($payload, function ($value) {
            return !is_null($value);
        });

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/payments', $payload);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Flutterwave payment initialization failed', [
                'response' => $result,
                'payload' => $payload,
            ]);
            throw new \Exception('Payment initialization failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        return [
            'success' => true,
            'reference' => $result['data']['tx_ref'],
            'provider_reference' => $result['data']['link'],
            'payment_link' => $result['data']['link'],
            'access_code' => $result['data']['access_code'] ?? null,
            'data' => $result['data'],
        ];
    }

    public function verifyPayment(string $reference): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . '/transactions/' . $reference . '/verify');

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Flutterwave payment verification failed', [
                'response' => $result,
                'reference' => $reference,
            ]);
            throw new \Exception('Payment verification failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        $data = $result['data'];
        $status = $data['status'] ?? 'failed';
        $isSuccessful = in_array($status, ['successful', 'completed']);

        return [
            'success' => $isSuccessful,
            'status' => $status,
            'reference' => $data['tx_ref'],
            'provider_reference' => $data['flw_ref'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'payment_method' => $data['payment_type'] ?? null,
            'customer' => [
                'email' => $data['customer']['email'] ?? null,
                'name' => $data['customer']['name'] ?? null,
                'phone' => $data['customer']['phone_number'] ?? null,
            ],
            'processed_at' => $data['created_at'] ?? null,
            'fees' => $data['app_fee'] ?? 0,
            'data' => $data,
        ];
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        // Validate signature first — reject anything that doesn't match
        if (!$this->validateWebhookSignature($payload, $signature)) {
            throw new \Exception('Invalid Flutterwave webhook signature');
        }

        $event = $payload['event'] ?? null;
        $data  = $payload['data'] ?? [];

        if (!$event || !$data) {
            throw new \Exception('Invalid Flutterwave webhook payload: missing event or data');
        }

        $reference = $data['tx_ref'] ?? null;
        $status    = $data['status'] ?? 'failed';

        Log::info('Flutterwave webhook received', [
            'event'     => $event,
            'reference' => $reference,
            'status'    => $status,
        ]);

        return [
            'event'     => $event,
            'reference' => $reference,
            'status'    => $status,
            'data'      => $data,
        ];
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        $payload = [
            'ref' => $reference,
        ];

        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/transactions/' . $reference . '/refund', $payload);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Flutterwave refund failed', [
                'response' => $result,
                'reference' => $reference,
            ]);
            throw new \Exception('Refund failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        return [
            'success' => true,
            'reference' => $result['data']['id'] ?? null,
            'amount_refunded' => $result['data']['amount'] ?? null,
            'status' => $result['data']['status'] ?? 'pending',
            'data' => $result['data'],
        ];
    }

    /**
     * Attempt to collect payment for a finalized, frozen invoice.
     *
     * Flutterwave can use tokenized card charging if a token is available.
     */
    public function attemptInvoiceCollection(Invoice $invoice, PaymentMethod $method): PaymentResponseDTO
    {
        // Check if payment method has a token for recurring charges
        $token = $method->metadata['token'] ?? null;

        if (empty($token)) {
            return PaymentResponseDTO::failure(
                'No payment token available for automatic charging',
                ['reason' => 'no_token']
            );
        }

        $reference = 'INV-' . strtoupper(uniqid());

        $payload = [
            'tx_ref' => $reference,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'token' => $token,
            'email' => $method->account_identifier ?? null,
            'narration' => "Payment for invoice {$invoice->invoice_number}",
        ];

        Log::info('Flutterwave: Attempting invoice collection with token', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'reference' => $reference,
            'amount' => $invoice->total,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/charges', $payload);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Flutterwave: Invoice collection failed', [
                'response' => $result,
                'invoice_id' => $invoice->id,
            ]);

            return PaymentResponseDTO::failure(
                'Failed to charge tokenized card',
                $result
            );
        }

        Log::info('Flutterwave: Invoice collection initiated successfully', [
            'invoice_id' => $invoice->id,
            'reference' => $reference,
        ]);

        return PaymentResponseDTO::success([
            'status' => $result['data']['status'] ?? 'pending',
            'transaction_id' => $reference,
            'provider_reference' => $result['data']['id'] ?? $reference,
            'message' => 'Invoice payment charge initiated successfully',
        ]);
    }

    /**
     * Return an array of capabilities this provider supports.
     */
    public function getCapabilities(): array
    {
        return [
            'tokenized_charging' => true,
            'momo_push' => false,
            'refunds' => true,
            'webhooks' => true,
            'split_payments' => true,
        ];
    }

    public function getProviderName(): string
    {
        return 'flutterwave';
    }

    public function validateWebhookSignature(array $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('Flutterwave webhook secret not configured');
            return false;
        }

        $computedSignature = hash_hmac('sha256', json_encode($payload), $this->webhookSecret);

        return hash_equals($computedSignature, $signature);
    }

    private function generateReference(): string
    {
        return 'FLW-' . strtoupper(uniqid()) . '-' . time();
    }
}

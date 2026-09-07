<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\DTOs\PaymentResponseDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AirtelProvider implements PaymentProviderInterface
{
    private string $baseUrl;
    private ?string $clientId;
    private ?string $clientSecret;
    private string $country;
    private string $currency;
    private string $environment;
    private ?string $callbackUrl;

    public function __construct(array $credentials = [])
    {
        // If credentials provided, use them (for per-salon integration)
        // Otherwise use global config (for platform payments)
        $this->clientId = $credentials['api_key'] ?? config('services.airtel.client_id');
        $this->clientSecret = $credentials['api_secret'] ?? config('services.airtel.client_secret');
        $this->country = $credentials['country'] ?? config('services.airtel.country', 'UG');
        $this->currency = $credentials['currency'] ?? config('services.airtel.currency', 'UGX');
        $this->environment = $credentials['environment'] ?? config('services.airtel.environment', 'sandbox');
        
        $this->baseUrl = $this->environment === 'production' 
            ? 'https://www.airtel.africa' 
            : 'https://preprod.airtel.africa';
            
        $this->callbackUrl = config('services.airtel.callback_url');
    }

    public function initializePayment(array $data): array
    {
        // Get access token first
        $token = $this->getAccessToken();
        
        if (!$token) {
            throw new \Exception('Failed to obtain Airtel access token');
        }

        $reference = $data['reference'] ?? $this->generateReference();
        
        // Airtel payment payload
        $payload = [
            'reference' => $reference,
            'subscriber' => [
                'country' => $this->country,
                'currency' => $this->currency,
                'msisdn' => $this->formatPhoneNumber($data['phone'] ?? null),
            ],
            'transaction' => [
                'amount' => $data['amount'],
                'country' => $this->country,
                'currency' => $this->currency,
                'id' => $reference,
            ],
            'payee' => [
                'country' => $this->country,
                'currency' => $this->currency,
            ],
        ];

        // Remove phone if not provided
        if (empty($payload['subscriber']['msisdn'])) {
            unset($payload['subscriber']);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-Country' => $this->country,
            'X-Currency' => $this->currency,
        ])->post($this->baseUrl . '/merchant/v1/payments', $payload);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Airtel payment initialization failed', [
                'response' => $result,
                'payload' => $payload,
            ]);
            throw new \Exception('Airtel payment initialization failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        return [
            'success' => true,
            'reference' => $reference,
            'provider_reference' => $result['data']['transaction']['id'] ?? $reference,
            'status' => 'pending',
            'data' => $result,
        ];
    }

    public function verifyPayment(string $reference): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            throw new \Exception('Failed to obtain Airtel access token');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-Country' => $this->country,
            'X-Currency' => $this->currency,
        ])->get($this->baseUrl . '/merchant/v1/payments/' . $reference);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Airtel payment verification failed', [
                'response' => $result,
                'reference' => $reference,
            ]);
            throw new \Exception('Airtel payment verification failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        $transaction = $result['data']['transaction'] ?? [];
        $status = $transaction['status'] ?? 'failed';
        $isSuccessful = in_array(strtolower($status), ['successful', 'completed', 'success']);

        return [
            'success' => $isSuccessful,
            'status' => strtolower($status),
            'reference' => $reference,
            'provider_reference' => $transaction['id'] ?? $reference,
            'amount' => $transaction['amount'] ?? null,
            'currency' => $transaction['currency'] ?? $this->currency,
            'payment_method' => 'airtel_money',
            'customer' => [
                'phone' => $transaction['msisdn'] ?? null,
            ],
            'processed_at' => $transaction['creation_time'] ?? null,
            'fees' => 0, // Airtel doesn't return fees in basic response
            'data' => $result,
        ];
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        // For Airtel, we might need signature validation, but leaving it open as per implementation
        if (empty($payload['data']['transaction']['id']) || empty($payload['data']['transaction']['status'])) {
            throw new \Exception('Invalid Airtel webhook payload: missing transaction id or status');
        }

        $transaction = $payload['data']['transaction'];
        $transactionId = $transaction['id'];
        $status = strtolower($transaction['status']);

        Log::info('Airtel webhook received', [
            'transaction_id' => $transactionId,
            'status' => $status,
        ]);

        return [
            'event'     => 'transaction.updated',
            'reference' => $transactionId,
            'status'    => $status,
            'data'      => $payload,
        ];
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            throw new \Exception('Failed to obtain Airtel access token');
        }

        $refundReference = 'REF-' . $reference . '-' . time();
        
        $payload = [
            'reference' => $refundReference,
            'transaction' => [
                'amount' => $amount,
                'country' => $this->country,
                'currency' => $this->currency,
                'id' => $refundReference,
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-Country' => $this->country,
            'X-Currency' => $this->currency,
        ])->post($this->baseUrl . '/merchant/v1/disbursements', $payload);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Airtel refund failed', [
                'response' => $result,
                'reference' => $reference,
            ]);
            throw new \Exception('Airtel refund failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        return [
            'success' => true,
            'reference' => $refundReference,
            'amount_refunded' => $amount,
            'status' => 'pending',
            'data' => $result,
        ];
    }

    /**
     * Attempt to collect payment for a finalized, frozen invoice.
     *
     * Airtel can send a MoMo push prompt to the customer's phone.
     */
    public function attemptInvoiceCollection(Invoice $invoice, PaymentMethod $method): PaymentResponseDTO
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return PaymentResponseDTO::failure(
                'Failed to obtain Airtel access token',
                []
            );
        }

        $reference = 'INV-' . strtoupper(uniqid());

        $payload = [
            'reference' => $reference,
            'subscriber' => [
                'country' => $this->country,
                'currency' => $this->currency,
                'msisdn' => $this->formatPhoneNumber($method->account_identifier),
            ],
            'transaction' => [
                'amount' => $invoice->total,
                'country' => $this->country,
                'currency' => $this->currency,
                'id' => $reference,
            ],
            'payee' => [
                'country' => $this->country,
                'currency' => $this->currency,
            ],
        ];

        Log::info('Airtel: Attempting invoice collection', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'reference' => $reference,
            'amount' => $invoice->total,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-Country' => $this->country,
            'X-Currency' => $this->currency,
        ])->post($this->baseUrl . '/merchant/v1/payments', $payload);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Airtel: Invoice collection failed', [
                'response' => $result,
                'invoice_id' => $invoice->id,
            ]);

            return PaymentResponseDTO::failure(
                'Failed to initiate invoice collection',
                $result
            );
        }

        Log::info('Airtel: Invoice collection initiated successfully', [
            'invoice_id' => $invoice->id,
            'reference' => $reference,
        ]);

        return PaymentResponseDTO::success([
            'status' => 'pending',
            'transaction_id' => $reference,
            'provider_reference' => $result['data']['transaction']['id'] ?? $reference,
            'message' => 'Invoice payment request initiated successfully',
        ]);
    }

    /**
     * Return an array of capabilities this provider supports.
     */
    public function getCapabilities(): array
    {
        return [
            'tokenized_charging' => false,
            'momo_push' => true,
            'refunds' => true,
            'webhooks' => true,
            'split_payments' => false,
        ];
    }

    public function getProviderName(): string
    {
        return 'airtel';
    }

    public function validateWebhookSignature(array $payload, string $signature): bool
    {
        // Airtel may use webhook signatures - implement based on their documentation
        // For now, validate payload structure
        return !empty($payload['data']['transaction']['id']) && !empty($payload['data']['transaction']['status']);
    }

    /**
     * Get Airtel API access token
     */
    public function getAccessToken(): ?string
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/merchant/v1/oauth/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Airtel token request failed', [
                'response' => $result,
            ]);
            return null;
        }

        return $result['access_token'] ?? null;
    }

    /**
     * Format phone number for Airtel API (remove +, ensure 256 prefix for Uganda)
     */
    private function formatPhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 0, replace with 256 (Uganda country code)
        if (str_starts_with($phone, '0')) {
            $phone = '256' . substr($phone, 1);
        }

        // If doesn't start with 256, add it
        if (!str_starts_with($phone, '256')) {
            $phone = '256' . $phone;
        }

        return $phone;
    }

    private function generateReference(): string
    {
        return 'AIRTEL-' . strtoupper(uniqid()) . '-' . time();
    }
}

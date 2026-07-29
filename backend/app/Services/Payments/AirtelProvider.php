<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AirtelProvider implements PaymentProviderInterface
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
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
        // Airtel webhooks may include signature verification
        // For now, we'll validate the payload structure
        
        if (empty($payload['data']['transaction']['id']) || empty($payload['data']['transaction']['status'])) {
            throw new \Exception('Invalid Airtel webhook payload');
        }

        $transaction = $payload['data']['transaction'];
        $status = strtolower($transaction['status']);
        $isSuccessful = in_array($status, ['successful', 'completed', 'success']);

        return [
            'event' => 'payment_status_update',
            'reference' => $transaction['id'],
            'provider_reference' => $transaction['id'],
            'status' => $status,
            'amount' => $transaction['amount'] ?? null,
            'currency' => $transaction['currency'] ?? $this->currency,
            'payment_method' => 'airtel_money',
            'customer' => [
                'phone' => $transaction['msisdn'] ?? null,
            ],
            'processed_at' => $transaction['creation_time'] ?? null,
            'fees' => 0,
            'data' => $payload,
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

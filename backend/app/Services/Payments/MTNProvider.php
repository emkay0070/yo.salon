<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MTNProvider implements PaymentProviderInterface
{
    private string $baseUrl;
    private string $subscriptionKey;
    private string $apiKey;
    private string $apiUser;
    private string $environment;
    private ?string $callbackUrl;

    public function __construct(array $credentials = [])
    {
        // If credentials provided, use them (for per-salon integration)
        // Otherwise use global config (for platform payments)
        $this->subscriptionKey = $credentials['api_subscription_key'] ?? config('services.mtn.subscription_key');
        $this->apiKey = $credentials['api_key'] ?? config('services.mtn.api_key');
        $this->apiUser = $credentials['merchant_id'] ?? config('services.mtn.api_user');
        $this->environment = $credentials['environment'] ?? config('services.mtn.environment', 'sandbox');
        
        $this->baseUrl = $this->environment === 'production' 
            ? 'https://api.mtn.com' 
            : 'https://sandbox.momodeveloper.mtn.com';
            
        $this->callbackUrl = config('services.mtn.callback_url');
    }

    public function initializePayment(array $data): array
    {
        // Get access token first
        $token = $this->getAccessToken();
        
        if (!$token) {
            throw new \Exception('Failed to obtain MTN access token');
        }

        $reference = $data['reference'] ?? $this->generateReference();
        
        // MTN RequestToPay payload
        $payload = [
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'UGX',
            'externalId' => $reference,
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $this->formatPhoneNumber($data['phone'] ?? null),
            ],
            'payeeNote' => $data['description'] ?? 'Payment for service',
            'payerMessage' => $data['customer_message'] ?? 'Please confirm payment',
            'callbackUrl' => $data['callback_url'] ?? $this->callbackUrl,
        ];

        // Remove phone if not provided
        if (empty($payload['payer']['partyId'])) {
            unset($payload['payer']);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Reference-Id' => $reference,
            'X-Target-Environment' => $this->environment,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/collection/v1_0/requesttopay', $payload);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('MTN payment initialization failed', [
                'response' => $result,
                'payload' => $payload,
            ]);
            throw new \Exception('MTN payment initialization failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        return [
            'success' => true,
            'reference' => $reference,
            'provider_reference' => $reference, // MTN uses same reference
            'status' => 'pending',
            'data' => $result,
        ];
    }

    public function verifyPayment(string $reference): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            throw new \Exception('Failed to obtain MTN access token');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Target-Environment' => $this->environment,
            'Ocp-Apim-Subscription-Key' => $this->subscription_key,
        ])->get($this->baseUrl . '/collection/v1_0/requesttopay/' . $reference);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('MTN payment verification failed', [
                'response' => $result,
                'reference' => $reference,
            ]);
            throw new \Exception('MTN payment verification failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        $status = $result['status'] ?? 'failed';
        $isSuccessful = in_array($status, ['SUCCESSFUL', 'COMPLETED']);

        return [
            'success' => $isSuccessful,
            'status' => strtolower($status),
            'reference' => $reference,
            'provider_reference' => $reference,
            'amount' => $result['amount'] ?? null,
            'currency' => $result['currency'] ?? null,
            'payment_method' => 'mtn_mobile_money',
            'customer' => [
                'phone' => $result['payer']['partyId'] ?? null,
            ],
            'processed_at' => $result['creationTime'] ?? null,
            'fees' => 0, // MTN doesn't return fees in basic response
            'data' => $result,
        ];
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        // MTN webhooks don't typically use signature verification in the same way
        // They rely on the callback URL being secure
        // We'll validate the payload structure instead
        
        if (empty($payload['externalId']) || empty($payload['status'])) {
            throw new \Exception('Invalid MTN webhook payload');
        }

        $status = strtolower($payload['status']);
        $isSuccessful = in_array($status, ['successful', 'completed']);

        return [
            'event' => 'payment_status_update',
            'reference' => $payload['externalId'],
            'provider_reference' => $payload['externalId'],
            'status' => $status,
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'payment_method' => 'mtn_mobile_money',
            'customer' => [
                'phone' => $payload['payer']['partyId'] ?? null,
            ],
            'processed_at' => $payload['creationTime'] ?? null,
            'fees' => 0,
            'data' => $payload,
        ];
    }

    public function refundPayment(string $reference, ?float $amount = null): array
    {
        $token = $this->getAccessToken();
        
        if (!$token) {
            throw new \Exception('Failed to obtain MTN access token');
        }

        $refundReference = 'REF-' . $reference . '-' . time();
        
        $payload = [
            'amount' => $amount,
            'currency' => 'UGX',
            'externalId' => $refundReference,
            'payeeNote' => 'Refund for transaction ' . $reference,
            'payerMessage' => 'Refund processed',
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Reference-Id' => $refundReference,
            'X-Target-Environment' => $this->environment,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/disbursement/v1_0/transfer', $payload);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('MTN refund failed', [
                'response' => $result,
                'reference' => $reference,
            ]);
            throw new \Exception('MTN refund failed: ' . ($result['message'] ?? 'Unknown error'));
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
        return 'mtn';
    }

    public function validateWebhookSignature(array $payload, string $signature): bool
    {
        // MTN doesn't use webhook signatures in the traditional sense
        // Validation is done through secure callback URL and payload structure
        return !empty($payload['externalId']) && !empty($payload['status']);
    }

    /**
     * Get MTN API access token
     */
    public function getAccessToken(): ?string
    {
        $response = Http::withBasicAuth(
            $this->apiUser,
            $this->apiKey
        )->withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/collection/token/');

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('MTN token request failed', [
                'response' => $result,
            ]);
            return null;
        }

        return $result['access_token'] ?? null;
    }

    /**
     * Format phone number for MTN API (remove +, ensure 256 prefix for Uganda)
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
        return 'MTN-' . strtoupper(uniqid()) . '-' . time();
    }
}

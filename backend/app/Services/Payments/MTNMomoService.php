<?php

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\DTOs\PaymentRequestDTO;
use App\Services\Payments\DTOs\PaymentResponseDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MTNMomoService implements PaymentProviderInterface
{
    private ?string $baseUrl = null;
    private ?string $subscriptionKey = null;
    private ?string $apiUser = null;
    private ?string $apiKey = null;
    private ?string $environment = null;
    private ?string $accessToken = null;

    /**
     * Set credentials from payment method record
     */
    public function setCredentials(array $credentials): void
    {
        $this->subscriptionKey = $credentials['api_subscription_key'] ?? null;
        $this->apiKey = $credentials['api_key'] ?? null;
        $this->apiUser = $credentials['merchant_id'] ?? null; // MTN uses merchant_id as api_user
        $this->environment = $credentials['environment'] ?? 'sandbox';
        
        // Dynamic base URL based on environment
        $this->baseUrl = $this->environment === 'production'
            ? 'https://momodeveloper.mtn.com'
            : 'https://sandbox.momodeveloper.mtn.com';

        Log::info('MTN MoMo: Credentials configured', [
            'environment' => $this->environment,
            'base_url' => $this->baseUrl,
            'merchant_id' => $this->apiUser,
        ]);
    }

    /**
     * Ensure credentials are set before making API calls
     */
    private function ensureCredentials(): void
    {
        if (!$this->subscriptionKey || !$this->apiKey || !$this->apiUser) {
            throw new \Exception('MTN MoMo credentials not set. Call setCredentials() first.');
        }
    }

    /**
     * Get access token for MTN MoMo API
     */
    public function getAccessToken(): string
    {
        $this->ensureCredentials();

        if ($this->accessToken) {
            return $this->accessToken;
        }

        Log::info('MTN MoMo: Requesting access token', [
            'url' => "{$this->baseUrl}/collection/token/",
            'subscription_key_length' => strlen($this->subscriptionKey ?? ''),
            'api_user_length' => strlen($this->apiUser ?? ''),
            'api_key_length' => strlen($this->apiKey ?? ''),
        ]);

        // Try manual Basic Auth header instead of withBasicAuth
        $basic = base64_encode($this->apiUser . ':' . $this->apiKey);

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $basic,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->timeout(60)
         ->connectTimeout(30)
         ->post("{$this->baseUrl}/collection/token/");

        if ($response->failed()) {
            Log::error('MTN MoMo: Failed to get access token', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => "{$this->baseUrl}/collection/token/",
            ]);
            throw new \Exception('Failed to authenticate with MTN MoMo: ' . $response->body());
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];

        Log::info('MTN MoMo: Access token obtained successfully');

        return $this->accessToken;
    }

    /**
     * Request a payment from the customer
     */
    public function requestPayment(array $data): array
    {
        $dto = PaymentRequestDTO::fromArray($data);
        $token = $this->getAccessToken();

        // Generate unique reference
        $reference = $dto->reference ?: 'PAY-' . uniqid();

        $payload = [
            'amount' => $dto->amount,
            'currency' => $dto->currency,
            'externalId' => $reference,
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $this->formatPhoneNumber($dto->phoneNumber),
            ],
            'payerMessage' => $dto->description ?? 'Payment for booking',
            'payeeNote' => 'Yo.Salon Booking Payment',
        ];

        Log::info('MTN MoMo: Initiating STK push', [
            'reference' => $reference,
            'amount' => $dto->amount,
            'phone' => $this->maskPhoneNumber($dto->phoneNumber),
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'X-Reference-Id' => $reference,
            'X-Target-Environment' => config('services.mtn.environment', 'sandbox'),
        ])->post("{$this->baseUrl}/collection/v1_0/requesttopay", $payload);

        if ($response->failed()) {
            Log::error('MTN MoMo: Payment request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'reference' => $reference,
            ]);

            return PaymentResponseDTO::failure(
                'Failed to initiate payment request',
                $response->json()
            )->toArray();
        }

        Log::info('MTN MoMo: STK push initiated successfully', [
            'reference' => $reference,
            'status_code' => $response->status(),
        ]);

        // MTN returns 202 Accepted, we need to poll for status
        return PaymentResponseDTO::success([
            'status' => 'pending',
            'transaction_id' => $reference,
            'provider_reference' => $reference,
            'message' => 'Payment request initiated successfully',
        ])->toArray();
    }

    /**
     * Check the status of a payment
     * NOTE: This should only be called by webhook or admin, not by polling
     * Polling should only read the database
     */
    public function checkPaymentStatus(string $transactionId): array
    {
        $token = $this->getAccessToken();

        Log::info('MTN MoMo: Checking payment status', [
            'transaction_id' => $transactionId,
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            'X-Target-Environment' => config('services.mtn.environment', 'sandbox'),
        ])->get("{$this->baseUrl}/collection/v1_0/requesttopay/{$transactionId}");

        if ($response->failed()) {
            Log::error('MTN MoMo: Failed to check payment status', [
                'status' => $response->status(),
                'transaction_id' => $transactionId,
            ]);

            return PaymentResponseDTO::failure(
                'Failed to check payment status',
                $response->json()
            )->toArray();
        }

        $data = $response->json();
        $status = $this->mapMTNStatus($data['status'] ?? 'unknown');

        Log::info('MTN MoMo: Payment status retrieved', [
            'transaction_id' => $transactionId,
            'status' => $status,
        ]);

        return PaymentResponseDTO::success([
            'status' => $status,
            'transaction_id' => $transactionId,
            'provider_reference' => $data['financialTransactionId'] ?? null,
            'message' => "Payment status: {$status}",
            'raw_response' => $data,
        ])->toArray();
    }

    /**
     * Handle webhook callback from MTN
     */
    public function handleWebhook(array $payload): bool
    {
        // DUPLICATE WEBHOOK PROTECTION: Check if already processed
        $transactionId = $payload['externalId'] ?? null;
        
        if (!$transactionId) {
            Log::error('MTN MoMo: Webhook missing externalId', $payload);
            return false;
        }

        $paymentRequest = \App\Models\PaymentRequest::where('provider_reference', $transactionId)->first();
        
        if (!$paymentRequest) {
            Log::error('MTN MoMo: Payment request not found for webhook', [
                'transaction_id' => $transactionId,
            ]);
            return false;
        }

        // If already successful, don't process again (idempotent)
        if ($paymentRequest->status === 'successful') {
            Log::info('MTN MoMo: Webhook already processed', [
                'payment_request_id' => $paymentRequest->id,
                'transaction_id' => $transactionId,
            ]);
            return true;
        }

        $status = $this->mapMTNStatus($payload['status'] ?? 'unknown');

        // Wrap in DB transaction for atomicity
        return \Illuminate\Support\Facades\DB::transaction(function () use ($paymentRequest, $status, $payload) {
            $paymentRequest->update([
                'status' => $status,
                'provider_reference' => $payload['financialTransactionId'] ?? $paymentRequest->provider_reference,
                'completed_at' => in_array($status, ['successful', 'failed', 'cancelled', 'expired']) ? now() : null,
            ]);

            Log::info('MTN MoMo: Payment status updated via webhook', [
                'payment_request_id' => $paymentRequest->id,
                'status' => $status,
                'transaction_id' => $payload['externalId'],
            ]);

            // If payment successful, create transaction and update booking
            if ($status === 'successful') {
                $this->processSuccessfulPayment($paymentRequest, $payload);
            }

            return true;
        });
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        // MTN uses X-Reference-Id for correlation
        // In production, implement HMAC signature verification
        // For now, verify the reference exists in our system
        $transactionId = $payload['externalId'] ?? null;
        
        if (!$transactionId) {
            Log::warning('MTN MoMo: Webhook verification failed - no externalId');
            return false;
        }

        $exists = \App\Models\PaymentRequest::where('provider_reference', $transactionId)->exists();
        
        if (!$exists) {
            Log::warning('MTN MoMo: Webhook verification failed - unknown transaction', [
                'transaction_id' => $transactionId,
            ]);
            return false;
        }

        Log::info('MTN MoMo: Webhook verified', [
            'transaction_id' => $transactionId,
        ]);
        
        return true;
    }

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return 'mtn_momo';
    }

    /**
     * Format phone number for MTN (remove +, ensure 256 prefix)
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with 256
        if (str_starts_with($phone, '0')) {
            $phone = '256' . substr($phone, 1);
        }
        
        // If doesn't start with 256, add it
        if (!str_starts_with($phone, '256')) {
            $phone = '256' . $phone;
        }
        
        return $phone;
    }

    /**
     * Mask phone number for logging (privacy)
     */
    private function maskPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned) <= 4) return '****';
        return substr($cleaned, 0, 3) . '****' . substr($cleaned, -3);
    }

    /**
     * Map MTN status to our application status
     */
    private function mapMTNStatus(string $mtnStatus): string
    {
        switch ($mtnStatus) {
            case 'SUCCESSFUL':
                return 'successful';
            case 'FAILED':
                return 'failed';
            case 'PENDING':
                return 'pending';
            case 'TIMEDOUT':
                return 'expired';
            case 'CANCELLED':
                return 'cancelled';
            default:
                return 'pending';
        }
    }

    /**
     * Process successful payment - create transaction and update booking
     */
    private function processSuccessfulPayment(\App\Models\PaymentRequest $paymentRequest, array $mtnData): void
    {
        $feeEngine = app(\App\Services\FeeEngine::class);
        $paymentMethod = $paymentRequest->payment_method_id
            ? \App\Models\PaymentMethod::find($paymentRequest->payment_method_id)
            : null;

        $fees = $feeEngine->calculateFees((float) $paymentRequest->amount, $paymentMethod);

        $transaction = \App\Models\Transaction::create([
            'salon_id' => $paymentRequest->salon_id,
            'booking_id' => $paymentRequest->booking_id,
            'customer_id' => $paymentRequest->customer_id,
            'payment_method_id' => $paymentRequest->payment_method_id,
            'type' => 'payment',
            'status' => 'completed',
            'gross_amount' => $fees['gross_amount'],
            'gateway_fee' => $fees['gateway_fee'],
            'platform_fee' => $fees['platform_fee'],
            'tax_amount' => $fees['tax_amount'],
            'net_amount' => $fees['net_amount'],
            'currency' => 'UGX',
            'internal_reference' => 'TXN-' . strtoupper(\Illuminate\Support\Str::random(10)),
            'provider_reference' => $mtnData['financialTransactionId'] ?? null,
            'paid_at' => now(),
        ]);

        // Update booking payment status
        if ($paymentRequest->booking_id) {
            \App\Models\Booking::where('id', $paymentRequest->booking_id)
                ->update(['payment_status' => 'paid']);
        }

        Log::info('MTN MoMo: Payment processed successfully', [
            'payment_request_id' => $paymentRequest->id,
            'transaction_id' => $transaction->id,
        ]);
    }
}

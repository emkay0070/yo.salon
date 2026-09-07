<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentProfile;
use App\Services\Payments\Contracts\DisbursementProvider;
use App\Services\Payments\DTOs\PaymentResponseDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MTNDisbursementProvider implements DisbursementProvider
{
    private ?string $baseUrl = null;
    private ?string $subscriptionKey = null;
    private ?string $apiUser = null;
    private ?string $apiKey = null;
    private ?string $environment = null;
    private ?string $accessToken = null;

    /**
     * Set credentials from the salon's disbursement configuration
     */
    public function setCredentials(array $credentials): void
    {
        $this->subscriptionKey = $credentials['api_subscription_key'] ?? null;
        $this->apiKey = $credentials['api_key'] ?? null;
        $this->apiUser = $credentials['merchant_id'] ?? null;
        $this->environment = $credentials['environment'] ?? 'sandbox';
        
        $this->baseUrl = $this->environment === 'production'
            ? 'https://momodeveloper.mtn.com/disbursement'
            : 'https://sandbox.momodeveloper.mtn.com/disbursement';
    }

    private function ensureCredentials(): void
    {
        if (!$this->subscriptionKey || !$this->apiKey || !$this->apiUser) {
            throw new \Exception('MTN Disbursement credentials not set. Call setCredentials() first.');
        }
    }

    private function getAccessToken(): string
    {
        $this->ensureCredentials();

        if ($this->accessToken) {
            return $this->accessToken;
        }

        $basic = base64_encode($this->apiUser . ':' . $this->apiKey);

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $basic,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->timeout(30)->post("{$this->baseUrl}/token/");

        if ($response->failed()) {
            throw new \Exception('Failed to authenticate with MTN Disbursement API: ' . $response->body());
        }

        $this->accessToken = $response->json('access_token');
        return $this->accessToken;
    }

    public function transfer(
        PaymentProfile $profile,
        float $amount,
        string $currency,
        string $reference,
        string $description = ''
    ): PaymentResponseDTO {
        try {
            $token = $this->getAccessToken();

            // MTN Disbursement requires the phone number without '+'
            $phone = preg_replace('/[^0-9]/', '', $profile->phone_number);

            $payload = [
                'amount' => (string) $amount,
                'currency' => $currency,
                'externalId' => $reference,
                'payee' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $phone,
                ],
                'payerMessage' => $description ?: 'Payout from Yo.Salon',
                'payeeNote' => 'Payout from Yo.Salon',
            ];

            Log::info('MTN Disbursement: Initiating transfer', [
                'reference' => $reference,
                'amount' => $amount,
            ]);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                'X-Reference-Id' => $reference,
                'X-Target-Environment' => $this->environment,
            ])->post("{$this->baseUrl}/v1_0/transfer", $payload);

            if ($response->failed()) {
                $status = $response->status();
                if ($status >= 500 || $status === 408) {
                    // Server error or timeout -> Ambiguous failure
                    throw new \RuntimeException('Ambiguous provider failure: HTTP ' . $status);
                }
                
                Log::error('MTN Disbursement API Error', ['body' => $response->json()]);
                return PaymentResponseDTO::failure('Transfer failed', $response->json());
            }

            // MTN returns 202 Accepted. Status checked via webhook or polling.
            return PaymentResponseDTO::success([
                'status' => 'processing',
                'provider_reference' => $reference, // Used as transactionId internally by MTN
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('MTN Disbursement Connection Error: ' . $e->getMessage());
            // Timeout/Connection error -> Ambiguous
            throw new \RuntimeException('Ambiguous provider failure: Connection Timeout');
        } catch (\Exception $e) {
            // Re-throw RuntimeExceptions (ambiguous failures)
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            Log::error('MTN Disbursement Error: ' . $e->getMessage());
            return PaymentResponseDTO::failure($e->getMessage());
        }
    }

    public function checkStatus(string $providerReference): PaymentResponseDTO
    {
        try {
            $token = $this->getAccessToken();
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                'X-Target-Environment' => $this->environment,
            ])->get("{$this->baseUrl}/v1_0/transfer/{$providerReference}");

            if ($response->failed()) {
                return PaymentResponseDTO::failure('Status check failed', $response->json());
            }

            $data = $response->json();
            $status = $this->mapStatus($data['status'] ?? 'unknown');

            return PaymentResponseDTO::success([
                'status' => $status,
                'provider_reference' => $data['financialTransactionId'] ?? $providerReference,
                'raw' => $data,
            ]);
        } catch (\Exception $e) {
            return PaymentResponseDTO::failure($e->getMessage());
        }
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        $reference = $payload['externalId'] ?? null;
        $status = $this->mapStatus($payload['status'] ?? 'unknown');

        return [
            'event' => 'transfer.updated',
            'reference' => $reference,
            'status' => $status,
        ];
    }

    private function mapStatus(string $mtnStatus): string
    {
        return match (strtoupper($mtnStatus)) {
            'SUCCESSFUL' => 'completed',
            'FAILED' => 'failed',
            'PENDING' => 'processing',
            default => 'processing',
        };
    }
}

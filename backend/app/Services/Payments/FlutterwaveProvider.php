<?php

namespace App\Services\Payments;

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
        if (!$this->validateWebhookSignature($payload, $signature)) {
            Log::warning('Invalid Flutterwave webhook signature');
            throw new \Exception('Invalid webhook signature');
        }

        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        if (!$event || !$data) {
            throw new \Exception('Invalid webhook payload');
        }

        return [
            'event' => $event,
            'reference' => $data['tx_ref'] ?? null,
            'provider_reference' => $data['flw_ref'] ?? null,
            'status' => $data['status'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
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

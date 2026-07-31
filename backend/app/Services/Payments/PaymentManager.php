<?php

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\DTOs\PaymentRequestDTO;
use App\Services\Payments\DTOs\PaymentResponseDTO;
use Illuminate\Support\Facades\Log;

class PaymentManager
{
    private array $providers = [];

    public function __construct()
    {
        // Register payment providers
        $this->registerProvider('mtn_momo', new MTNMomoService());
        // Add other providers later: airtel_money, flutterwave, etc.
    }

    /**
     * Register a payment provider
     */
    public function registerProvider(string $name, PaymentProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Get a payment provider by name
     */
    public function getProvider(string $name): ?PaymentProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Request payment using a specific provider
     */
    public function requestPayment(string $providerName, array $data, ?array $credentials = null): array
    {
        $provider = $this->getProvider($providerName);

        if (!$provider) {
            Log::error("Payment provider not found: {$providerName}");
            return PaymentResponseDTO::failure("Payment provider '{$providerName}' not found")->toArray();
        }

        try {
            // Set credentials if provided (for per-salon credentials)
            if ($credentials && method_exists($provider, 'setCredentials')) {
                $provider->setCredentials($credentials);
            }

            return $provider->requestPayment($data);
        } catch (\Exception $e) {
            Log::error("Payment request failed", [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);
            return PaymentResponseDTO::failure($e->getMessage())->toArray();
        }
    }

    /**
     * Check payment status using a specific provider
     */
    public function checkPaymentStatus(string $providerName, string $transactionId): array
    {
        $provider = $this->getProvider($providerName);

        if (!$provider) {
            Log::error("Payment provider not found: {$providerName}");
            return PaymentResponseDTO::failure("Payment provider '{$providerName}' not found")->toArray();
        }

        try {
            return $provider->checkPaymentStatus($transactionId);
        } catch (\Exception $e) {
            Log::error("Payment status check failed", [
                'provider' => $providerName,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return PaymentResponseDTO::failure($e->getMessage())->toArray();
        }
    }

    /**
     * Handle webhook from a specific provider
     */
    public function handleWebhook(string $providerName, array $payload, string $signature = null): bool
    {
        $provider = $this->getProvider($providerName);

        if (!$provider) {
            Log::error("Payment provider not found for webhook: {$providerName}");
            return false;
        }

        try {
            // Verify signature if provided
            if ($signature && !$provider->verifyWebhookSignature($payload, $signature)) {
                Log::error("Webhook signature verification failed", [
                    'provider' => $providerName,
                ]);
                return false;
            }

            return $provider->handleWebhook($payload);
        } catch (\Exception $e) {
            Log::error("Webhook handling failed", [
                'provider' => $providerName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get all registered providers
     */
    public function getProviders(): array
    {
        return array_keys($this->providers);
    }
}

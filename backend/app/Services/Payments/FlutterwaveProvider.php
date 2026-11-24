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
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $publicKey;
    private string $baseUrl;
    private ?string $webhookSecret;
    private ?string $scenarioKey;

    // OAuth 2.0 client credentials token cache
    private ?string $accessToken = null;
    private ?int $accessTokenExpiresAt = null; // Unix timestamp

    const TOKEN_URL = 'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token';
    const TOKEN_REFRESH_BUFFER_SECONDS = 60;

    public function __construct()
    {
        $this->clientId = config('services.flutterwave.client_id');
        $this->clientSecret = config('services.flutterwave.client_secret');
        $this->publicKey = config('services.flutterwave.public_key');
        $this->baseUrl = config('services.flutterwave.base_url', 'https://developersandbox-api.flutterwave.com');
        $this->webhookSecret = config('services.flutterwave.webhook_secret');
        $this->scenarioKey = config('services.flutterwave.scenario_key');
    }

    /**
     * Initialize a payment via Flutterwave v4 charges API.
     *
     * v4 requires creating a customer and payment method before creating a charge.
     * The charge response includes a redirect URL for the customer to complete payment.
     *
     * @param array $data Payment data including amount, currency, customer info, metadata, reference
     * @return array Response with payment link, reference, and provider data
     */
    public function initializePayment(array $data): array
    {
        $txRef = $data['reference'] ?? $this->generateReference();
        $amount = $data['amount'];
        $currency = $data['currency'] ?? 'UGX';
        $customerEmail = $data['email'] ?? null;
        $customerName = $data['customer_name'] ?? null;
        $customerPhone = $data['phone'] ?? null;
        $redirectUrl = $data['redirect_url'] ?? config('app.url') . '/payment/callback';

        try {
            // Step 1: Create or find Flutterwave customer
            $customerId = $this->getOrCreateCustomer($customerEmail, $customerName, $customerPhone);

            // Step 2: Create a payment method for the customer
            $paymentMethodId = $this->createPaymentMethod($customerId, $customerPhone);

            // Step 3: Create the charge
            $chargePayload = [
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $txRef,
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'redirect_url' => $redirectUrl,
                'recurring' => false,
            ];

            $chargeHeaders = [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
                'X-Idempotency-Key' => str_replace('-', '', uniqid('', true)),
            ];

            // Sandbox-only: force the mobile-money redirect flow so the customer
            // is sent to Flutterwave's hosted authorization page instead of the
            // default push-notification flow (which returns no redirect URL).
            if (!empty($this->scenarioKey)) {
                $chargeHeaders['X-Scenario-Key'] = $this->scenarioKey;
            }

            $response = Http::withHeaders($chargeHeaders)->post($this->baseUrl . '/charges', $chargePayload);

            $result = $response->json();

            if (!$response->successful()) {
                Log::error('Flutterwave payment initialization failed', [
                    'response' => $result,
                    'payload' => $chargePayload,
                ]);
                throw new \Exception('Payment initialization failed: ' . ($result['error']['message'] ?? $result['message'] ?? 'Unknown error'));
            }

            $chargeData = $result['data'] ?? [];
            $nextAction = $chargeData['next_action'] ?? [];
            $nextActionType = $nextAction['type'] ?? null;
            $redirectUrlFromResponse = $nextAction['redirect_url']['url'] ?? null;
            $instruction = $nextAction['payment_instruction']['note'] ?? null;

            // A redirect is required for the customer to authorize the payment.
            // The push-notification flow (payment_instruction) cannot be driven
            // from the browser, so surface it instead of returning a null link.
            if ($redirectUrlFromResponse === null) {
                throw new \Exception('Flutterwave did not return a redirect URL (next_action: ' . ($nextActionType ?? 'unknown') . '). ' . ($instruction ?? ''));
            }

            return [
                'success' => true,
                'reference' => $chargeData['id'] ?? $txRef,
                'provider_reference' => $chargeData['id'] ?? $txRef,
                'payment_link' => $redirectUrlFromResponse,
                'next_action' => $nextActionType,
                'instruction' => $instruction,
                'access_code' => null,
                'data' => $chargeData,
            ];

        } catch (\Exception $e) {
            Log::error('Flutterwave payment initialization failed', [
                'error' => $e->getMessage(),
                'reference' => $txRef,
            ]);
            throw $e;
        }
    }

    /**
     * Verify a Flutterwave v4 charge.
     *
     * @param string $chargeId The Flutterwave charge ID
     * @return array Payment verification status and details
     */
    public function verifyPayment(string $reference): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . '/charges/' . $reference);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Flutterwave payment verification failed', [
                'response' => $result,
                'reference' => $reference,
            ]);
            throw new \Exception('Payment verification failed: ' . ($result['error']['message'] ?? $result['message'] ?? 'Unknown error'));
        }

        $data = $result['data'] ?? [];
        $status = $data['status'] ?? 'failed';
        $isSuccessful = $status === 'successful';

        return [
            'success' => $isSuccessful,
            'status' => $status,
            'reference' => $data['reference'] ?? $reference,
            'provider_reference' => $data['id'] ?? $reference,
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? 'UGX',
            'payment_method' => ($data['payment_method_details']['type'] ?? null),
            'customer' => [
                'email' => null, // v4 charge response doesn't include customer email directly
                'name' => null,
                'phone' => null,
            ],
            'processed_at' => $data['created_datetime'] ?? null,
            'fees' => 0, // v4 returns fees as an array, not a single value
            'data' => $data,
        ];
    }

    /**
     * Handle webhook callback from Flutterwave v4.
     *
     * @param array $payload The webhook payload from the provider
     * @param string $signature The webhook signature for verification
     * @return array Processed webhook data
     */
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

        $reference = $data['id'] ?? $data['reference'] ?? null;
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

    /**
     * Refund a Flutterwave v4 charge.
     *
     * @param string $chargeId The original charge ID
     * @param float|null $amount Amount to refund (null for full refund)
     * @return array Refund transaction details
     */
    public function refundPayment(string $reference, ?float $amount = null): array
    {
        $payload = [
            'charge_id' => $reference,
            'amount' => $amount,
            'reason' => 'requested_by_customer',
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
            'X-Idempotency-Key' => str_replace('-', '', uniqid('', true)),
        ])->post($this->baseUrl . '/refunds', $payload);

        $result = $response->json();

        if (!$response->successful()) {
            Log::error('Flutterwave refund failed', [
                'response' => $result,
                'charge_id' => $reference,
            ]);
            throw new \Exception('Refund failed: ' . ($result['error']['message'] ?? $result['message'] ?? 'Unknown error'));
        }

        $refundData = $result['data'] ?? [];

        return [
            'success' => true,
            'reference' => $refundData['id'] ?? null,
            'amount_refunded' => $refundData['amount'] ?? null,
            'status' => $refundData['status'] ?? 'pending',
            'data' => $refundData,
        ];
    }

    /**
     * Attempt to collect payment for a finalized, frozen invoice.
     *
     * @param Invoice $invoice
     * @param PaymentMethod $method
     * @return PaymentResponseDTO
     */
    public function attemptInvoiceCollection(Invoice $invoice, PaymentMethod $method): PaymentResponseDTO
    {
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
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
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

    /**
     * Obtain a valid OAuth 2.0 access token using client credentials.
     *
     * The token is cached in-memory and reused until it nears expiry
     * (60-second buffer). Tokens have a 10-minute lifetime per Flutterwave docs.
     *
     * @return string The access token to use as a Bearer token.
     * @throws \Exception If the token cannot be obtained.
     */
    private function getAccessToken(): string
    {
        // Return cached token if still valid (with refresh buffer)
        if ($this->accessToken && $this->accessTokenExpiresAt && time() < $this->accessTokenExpiresAt - self::TOKEN_REFRESH_BUFFER_SECONDS) {
            return $this->accessToken;
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \Exception('Flutterwave client credentials not configured. Set FLUTTERWAVE_CLIENT_ID and FLUTTERWAVE_CLIENT_SECRET.');
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        if (!$response->successful()) {
            Log::error('Flutterwave OAuth token fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Flutterwave authentication failed: unable to obtain access token (HTTP ' . $response->status() . ')');
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 600);
        $this->accessTokenExpiresAt = time() + $expiresIn;

        if (!$this->accessToken) {
            throw new \Exception('Flutterwave authentication failed: no access token in response');
        }

        Log::info('Flutterwave OAuth token obtained', [
            'expires_in' => $expiresIn,
        ]);

        return $this->accessToken;
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

    /**
     * Get or create a Flutterwave customer by email.
     *
     * @param string|null $email
     * @param string|null $name
     * @param string|null $phone
     * @return string Customer ID
     * @throws \Exception
     */
    private function getOrCreateCustomer(?string $email, ?string $name = null, ?string $phone = null): string
    {
        if (empty($email)) {
            throw new \Exception('Customer email is required for Flutterwave payment');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
        ];

        // Try to find existing customer by email
        $listResponse = Http::withHeaders($headers)
            ->get($this->baseUrl . '/customers', ['email' => $email]);

        if ($listResponse->successful()) {
            $customers = $listResponse->json()['data'] ?? [];
            foreach ($customers as $customer) {
                if ($customer['email'] ?? '' === $email) {
                    return $customer['id'];
                }
            }
        }

        // Create new customer
        $headers['X-Idempotency-Key'] = str_replace('-', '', uniqid('', true));
        $createResponse = Http::withHeaders($headers)->post($this->baseUrl . '/customers', [
            'email' => $email,
            'phone_number' => $phone,
        ]);

        if (!$createResponse->successful()) {
            $result = $createResponse->json();
            Log::error('Flutterwave customer creation failed', [
                'response' => $result,
                'email' => $email,
            ]);
            throw new \Exception('Failed to create Flutterwave customer: ' . ($result['error']['message'] ?? 'Unknown error'));
        }

        $customerData = $createResponse->json()['data'] ?? [];
        $customerId = $customerData['id'] ?? null;

        if (!$customerId) {
            throw new \Exception('Failed to create Flutterwave customer: no customer ID in response');
        }

        return $customerId;
    }

    /**
     * Create a mobile-money payment method for a Flutterwave customer.
     *
     * Flutterwave v4 charges require a concrete payment method. For Uganda the
     * supported, UGX-capable rail is mobile money, which needs the customer's
     * wallet number split into country code, network and local number.
     *
     * @param string $customerId
     * @param string|null $phone Customer phone in any common UG format
     * @return string Payment method ID
     * @throws \Exception
     */
    private function createPaymentMethod(string $customerId, ?string $phone): string
    {
        if (empty($phone)) {
            throw new \Exception('Customer phone number is required for Flutterwave mobile money payment');
        }

        $localNumber = $this->normalizeUgandaPhone($phone);

        $headers = [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
            'X-Idempotency-Key' => str_replace('-', '', uniqid('', true)),
        ];

        $response = Http::withHeaders($headers)->post($this->baseUrl . '/payment-methods', [
            'customer_id' => $customerId,
            'type' => 'mobile_money',
            'mobile_money' => [
                'country_code' => '256',
                'network' => $this->inferUgandaNetwork($localNumber),
                'phone_number' => $localNumber,
            ],
        ]);

        if (!$response->successful()) {
            $result = $response->json();
            Log::error('Flutterwave payment method creation failed', [
                'response' => $result,
                'customer_id' => $customerId,
            ]);
            throw new \Exception('Failed to create Flutterwave payment method: ' . ($result['error']['message'] ?? 'Unknown error'));
        }

        $pmData = $response->json()['data'] ?? [];
        $paymentMethodId = $pmData['id'] ?? null;

        if (!$paymentMethodId) {
            throw new \Exception('Failed to create Flutterwave payment method: no payment method ID in response');
        }

        return $paymentMethodId;
    }

    /**
     * Normalise a Ugandan phone number to the 9-digit local format Flutterwave
     * expects (country_code 256, phone_number 7XXXXXXXX).
     */
    private function normalizeUgandaPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // Strip a leading country code (256) or trunk prefix (0).
        if (str_starts_with($digits, '256')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Infer the Ugandan mobile network from the local number prefix.
     *
     * MTN Uganda: 76, 77, 78, 39. Airtel Uganda: 70, 74, 75, 20.
     */
    private function inferUgandaNetwork(string $localNumber): string
    {
        $prefix = substr($localNumber, 0, 2);

        return in_array($prefix, ['70', '74', '75', '20'], true) ? 'AIRTEL' : 'MTN';
    }

    private function generateReference(): string
    {
        return 'FLW-' . strtoupper(uniqid()) . '-' . time();
    }
}

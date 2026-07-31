<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        $methods = PaymentMethod::where('salon_id', $salonId)
            ->where('is_active', true)
            ->orderBy('is_primary', 'desc')
            ->get();

        Log::info('Payment methods fetched', [
            'salon_id' => $salonId,
            'count' => $methods->count(),
            'methods' => $methods->pluck('provider'),
        ]);

        return response()->json($methods);
    }

    /**
     * Get public payment methods for a salon (no auth required)
     * Used in booking flow to show available payment options
     */
    public function getPublicMethods(string $slug): JsonResponse
    {
        $salon = \App\Models\Salon::where('slug', $slug)->first();
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $methods = PaymentMethod::where('salon_id', $salon->id)
            ->where('is_active', true)
            ->orderBy('is_primary', 'desc')
            ->get(['id', 'provider', 'type', 'display_name']);

        return response()->json($methods);
    }

    public function store(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        $validated = $request->validate([
            'provider'               => 'required|in:cash,mtn,airtel,flutterwave,pesapal,visa,mastercard',
            'type'                   => 'required|in:cash,mobile_money,gateway,card',
            'display_name'           => 'required|string|max:100',
            'account_name'           => 'nullable|string|max:100',
            'account_identifier'     => 'nullable|string|max:100',
            'merchant_id'            => 'nullable|string|max:100',
            'api_key'                => 'nullable|string',
            'api_secret'             => 'nullable|string',
            'api_subscription_key'   => 'nullable|string',
            'environment'            => 'sometimes|in:sandbox,production',
            'currency'               => 'sometimes|string|max:10',
            'is_primary'             => 'sometimes|boolean',
            'is_active'              => 'sometimes|boolean',
            'metadata'               => 'nullable|array',
        ]);

        $validated['salon_id'] = $salonId;

        // For MTN, verify credentials before creating
        if (in_array($validated['provider'], ['mtn', 'mtn_momo'])) {
            if (empty($validated['merchant_id']) || empty($validated['api_key']) || empty($validated['api_subscription_key'])) {
                return response()->json(['message' => 'Merchant ID, API Key, and Subscription Key are required for MTN MoMo'], 422);
            }

            try {
                $momoService = new \App\Services\Payments\MTNMomoService();
                $momoService->setCredentials([
                    'merchant_id' => $validated['merchant_id'],
                    'api_key' => $validated['api_key'],
                    'api_subscription_key' => $validated['api_subscription_key'],
                    'environment' => $validated['environment'] ?? 'sandbox',
                ]);

                // Test by getting access token
                $token = $momoService->getAccessToken();

                if (!$token) {
                    return response()->json(['message' => 'Failed to verify MTN credentials'], 422);
                }
            } catch (\Exception $e) {
                Log::error('MTN credential verification failed during creation', [
                    'error' => $e->getMessage(),
                ]);
                return response()->json(['message' => 'Credential verification failed: ' . $e->getMessage()], 422);
            }
        }

        // Enforce one primary per salon
        if (!empty($validated['is_primary'])) {
            PaymentMethod::where('salon_id', $salonId)->update(['is_primary' => false]);
        }

        // Filter out empty strings for credential fields to avoid encryption errors
        $createData = [];
        foreach ($validated as $key => $value) {
            if (in_array($key, ['api_key', 'api_secret', 'api_subscription_key', 'merchant_id'])) {
                if ($value !== '' && $value !== null) {
                    $createData[$key] = $value;
                }
            } else {
                $createData[$key] = $value;
            }
        }

        $method = PaymentMethod::create($createData);
        return response()->json($method, 201);
    }

    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return response()->json($paymentMethod->load('salon'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $validated = $request->validate([
            'display_name'           => 'sometimes|string|max:100',
            'account_name'           => 'nullable|string|max:100',
            'account_identifier'     => 'nullable|string|max:100',
            'merchant_id'            => 'nullable|string|max:100',
            'api_key'                => 'nullable|string',
            'api_secret'             => 'nullable|string',
            'api_subscription_key'   => 'nullable|string',
            'environment'            => 'sometimes|in:sandbox,production',
            'currency'               => 'sometimes|string|max:10',
            'is_primary'             => 'sometimes|boolean',
            'is_active'              => 'sometimes|boolean',
            'metadata'               => 'nullable|array',
        ]);

        // Enforce one primary per salon
        if (!empty($validated['is_primary'])) {
            PaymentMethod::where('salon_id', $paymentMethod->salon_id)
                ->where('id', '!=', $paymentMethod->id)
                ->update(['is_primary' => false]);
        }

        // Only update credential fields if they are provided (not empty strings)
        $updateData = [];
        foreach ($validated as $key => $value) {
            // Skip empty strings for credential fields to avoid encryption errors
            if (in_array($key, ['api_key', 'api_secret', 'api_subscription_key', 'merchant_id'])) {
                if ($value !== '' && $value !== null) {
                    $updateData[$key] = $value;
                }
            } else {
                // For non-credential fields, include the value even if null
                $updateData[$key] = $value;
            }
        }

        // Use direct database update to avoid decryption errors on existing corrupted encrypted data
        PaymentMethod::where('id', $paymentMethod->id)->update($updateData);

        // Reload the model to get fresh data
        $paymentMethod->refresh();

        return response()->json($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update(['is_active' => false]); // Soft-disable, don't delete (preserve history)
        return response()->json(['message' => 'Payment method deactivated.']);
    }

    /**
     * Verify merchant credentials for a payment method.
     * This validates that the provided API credentials work with the provider.
     */
    public function verifyCredentials(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $validated = $request->validate([
            'merchant_id'          => 'required|string|max:100',
            'api_key'              => 'required|string',
            'api_secret'           => 'nullable|string',
            'api_subscription_key' => 'required|string',
            'environment'          => 'required|in:sandbox,production',
        ]);

        // Verify credentials by testing with the provider (don't save to DB)
        try {
            if ($paymentMethod->provider === 'mtn' || $paymentMethod->provider === 'mtn_momo') {
                $momoService = new \App\Services\Payments\MTNMomoService();
                $momoService->setCredentials([
                    'merchant_id' => $validated['merchant_id'],
                    'api_key' => $validated['api_key'],
                    'api_subscription_key' => $validated['api_subscription_key'],
                    'environment' => $validated['environment'],
                ]);

                // Test by getting access token
                $token = $momoService->getAccessToken();

                if ($token) {
                    // Only mark as verified, don't save credentials yet
                    $paymentMethod->update([
                        'credentials_verified_at' => now(),
                    ]);

                    return response()->json([
                        'message' => 'Credentials verified successfully. Click Save to persist them.',
                    ]);
                }
            }

            // For other providers or if verification fails
            throw new \Exception('Failed to verify credentials');

        } catch (\Exception $e) {
            Log::error('Payment method credential verification failed', [
                'provider' => $paymentMethod->provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Credential verification failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Test a payment method connection without saving credentials.
     * Useful for validation before saving.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider'             => 'required|in:mtn,airtel,flutterwave',
            'merchant_id'          => 'nullable|string|max:100',
            'api_key'              => 'required|string',
            'api_secret'           => 'required|string',
            'api_subscription_key' => 'nullable|string',
            'environment'          => 'required|in:sandbox,production',
            'country'              => 'nullable|string|max:2',
            'currency'             => 'nullable|string|max:3',
        ]);

        $provider = $validated['provider'];
        $credentials = [
            'api_key' => $validated['api_key'],
            'api_secret' => $validated['api_secret'],
            'api_subscription_key' => $validated['api_subscription_key'] ?? null,
            'merchant_id' => $validated['merchant_id'] ?? null,
            'environment' => $validated['environment'],
            'country' => $validated['country'] ?? 'UG',
            'currency' => $validated['currency'] ?? 'UGX',
        ];

        try {
            $providerInstance = $this->getProviderInstance($provider, $credentials);
            
            // Test by trying to get an access token (this validates credentials)
            if ($provider === 'flutterwave') {
                // For Flutterwave, we can test by making a simple API call
                $testResult = $providerInstance->initializePayment([
                    'amount' => 100,
                    'currency' => 'UGX',
                    'email' => 'test@example.com',
                    'customer_name' => 'Test Customer',
                    'reference' => 'TEST-' . time(),
                ]);
            } else {
                // For MTN and Airtel, test token retrieval
                $token = $this->getProviderToken($providerInstance);
                
                if (!$token) {
                    throw new \Exception('Failed to obtain access token');
                }
                
                $testResult = ['token_obtained' => true];
            }

            return response()->json([
                'success' => true,
                'message' => 'Connection test successful',
                'provider' => $provider,
                'environment' => $validated['environment'],
                'test_result' => $testResult,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment method connection test failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'provider' => $provider,
            ], 422);
        }
    }

    /**
     * Get provider instance based on provider name
     */
    private function getProviderInstance(string $provider, array $credentials): object
    {
        return match($provider) {
            'mtn' => new \App\Services\Payments\MTNProvider($credentials),
            'airtel' => new \App\Services\Payments\AirtelProvider($credentials),
            'flutterwave' => new \App\Services\Payments\FlutterwaveProvider(),
            default => throw new \Exception('Unsupported provider'),
        };
    }

    /**
     * Get provider access token (for MTN/Airtel)
     */
    private function getProviderToken(object $provider): ?string
    {
        // Use reflection to access private method or create a public method in providers
        // For now, we'll implement a simple token test
        if (method_exists($provider, 'getAccessToken')) {
            return $provider->getAccessToken();
        }
        
        return null;
    }
}

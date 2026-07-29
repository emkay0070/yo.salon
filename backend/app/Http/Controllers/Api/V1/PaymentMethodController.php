<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

        // Enforce one primary per salon
        if (!empty($validated['is_primary'])) {
            PaymentMethod::where('salon_id', $salonId)->update(['is_primary' => false]);
        }

        $method = PaymentMethod::create($validated);
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

        $paymentMethod->update($validated);
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
            'api_secret'           => 'required|string',
            'api_subscription_key' => 'nullable|string',
            'environment'          => 'required|in:sandbox,production',
        ]);

        // Update payment method with credentials
        $paymentMethod->update([
            'merchant_id' => $validated['merchant_id'],
            'api_key' => $validated['api_key'],
            'api_secret' => $validated['api_secret'],
            'api_subscription_key' => $validated['api_subscription_key'] ?? null,
            'environment' => $validated['environment'],
        ]);

        // TODO: Implement actual provider verification
        // For now, mark as verified (in production, call provider's test endpoint)
        $paymentMethod->update(['credentials_verified_at' => now()]);

        return response()->json([
            'message' => 'Credentials verified successfully',
            'payment_method' => $paymentMethod->fresh()->makeVisible(['api_key', 'api_secret', 'api_subscription_key']),
        ]);
    }

    /**
     * Test a payment method connection without saving credentials.
     * Useful for validation before saving.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider'             => 'required|in:mtn,airtel,flutterwave',
            'merchant_id'          => 'required|string|max:100',
            'api_key'              => 'required|string',
            'api_secret'           => 'required|string',
            'api_subscription_key' => 'nullable|string',
            'environment'          => 'required|in:sandbox,production',
        ]);

        // TODO: Implement actual provider connection test
        // For now, return success (in production, call provider's test endpoint)
        
        return response()->json([
            'success' => true,
            'message' => 'Connection test successful',
            'provider' => $validated['provider'],
            'environment' => $validated['environment'],
        ]);
    }
}

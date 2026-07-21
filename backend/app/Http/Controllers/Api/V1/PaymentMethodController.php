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
            'provider'           => 'required|in:cash,mtn,airtel,flutterwave,pesapal,visa,mastercard',
            'type'               => 'required|in:cash,mobile_money,gateway,card',
            'display_name'       => 'required|string|max:100',
            'account_name'       => 'nullable|string|max:100',
            'account_identifier' => 'nullable|string|max:100',
            'currency'           => 'sometimes|string|max:10',
            'is_primary'         => 'sometimes|boolean',
            'is_active'          => 'sometimes|boolean',
            'metadata'           => 'nullable|array',
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
            'display_name'       => 'sometimes|string|max:100',
            'account_name'       => 'nullable|string|max:100',
            'account_identifier' => 'nullable|string|max:100',
            'currency'           => 'sometimes|string|max:10',
            'is_primary'         => 'sometimes|boolean',
            'is_active'          => 'sometimes|boolean',
            'metadata'           => 'nullable|array',
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
}

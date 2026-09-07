<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentProfileController extends Controller
{
    /**
     * Get payment profiles for the authenticated user (Specialist or Staff)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // This assumes the user relation maps to Specialist or Staff.
        // For specialist portal, they have a Specialist record.
        // For staff, they have a Staff record.
        
        // As a fallback/flexible approach, we can expect the client to pass owner_type and owner_id
        // if the user manages multiple profiles, or we derive it from context.
        $ownerType = $request->query('owner_type');
        $ownerId = $request->query('owner_id');

        if (!$ownerType || !$ownerId) {
            return response()->json(['message' => 'Missing owner context'], 400);
        }

        $profiles = PaymentProfile::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->get();

        return response()->json($profiles);
    }

    /**
     * Create a new payment profile
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'owner_type' => 'required|string',
            'owner_id' => 'required|uuid',
            'method' => 'required|string|in:mtn,airtel,bank',
            'phone_number' => 'nullable|string',
            'account_number' => 'nullable|string',
            'account_name' => 'nullable|string',
            'bank_code' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'label' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        // If this is set as default, unset others for this owner
        if ($request->input('is_default', false)) {
            PaymentProfile::where('owner_type', $validated['owner_type'])
                ->where('owner_id', $validated['owner_id'])
                ->update(['is_default' => false]);
        }

        $profile = PaymentProfile::create($validated);

        return response()->json($profile, 201);
    }

    /**
     * Update an existing payment profile
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $profile = PaymentProfile::findOrFail($id);

        $validated = $request->validate([
            'method' => 'sometimes|string|in:mtn,airtel,bank',
            'phone_number' => 'nullable|string',
            'account_number' => 'nullable|string',
            'account_name' => 'nullable|string',
            'bank_code' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'label' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        if ($request->input('is_default', false) && !$profile->is_default) {
            PaymentProfile::where('owner_type', $profile->owner_type)
                ->where('owner_id', $profile->owner_id)
                ->where('id', '!=', $profile->id)
                ->update(['is_default' => false]);
        }

        $profile->update($validated);

        return response()->json($profile);
    }

    /**
     * Delete a payment profile
     */
    public function destroy(string $id): JsonResponse
    {
        $profile = PaymentProfile::findOrFail($id);
        
        $wasDefault = $profile->is_default;
        $ownerType = $profile->owner_type;
        $ownerId = $profile->owner_id;

        $profile->delete();

        // If we deleted the default, set another one as default if it exists
        if ($wasDefault) {
            $another = PaymentProfile::where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->first();
            if ($another) {
                $another->update(['is_default' => true]);
            }
        }

        return response()->json(null, 204);
    }
}

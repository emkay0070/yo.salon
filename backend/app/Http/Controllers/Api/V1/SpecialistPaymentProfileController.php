<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentProfile;
use App\Models\Specialist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Specialist-scoped payment profile controller.
 *
 * The authenticated specialist owns every profile they touch.
 * owner_type and owner_id are NEVER accepted from the client — they
 * are always derived from the authenticated user.
 */
class SpecialistPaymentProfileController extends Controller
{
    /**
     * List the authenticated specialist's payment profiles.
     */
    public function index(Request $request): JsonResponse
    {
        $specialist = $request->user(); // auth('specialist')

        $profiles = PaymentProfile::where('owner_type', Specialist::class)
            ->where('owner_id', $specialist->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($p) => $this->formatProfile($p));

        return response()->json($profiles);
    }

    /**
     * Add a new payment profile for the authenticated specialist.
     */
    public function store(Request $request): JsonResponse
    {
        $specialist = $request->user();

        $validated = $request->validate([
            'method'         => 'required|string|in:mtn,airtel,bank',
            'phone_number'   => 'required_if:method,mtn|required_if:method,airtel|nullable|string|max:20',
            'account_number' => 'required_if:method,bank|nullable|string|max:50',
            'account_name'   => 'required_if:method,bank|nullable|string|max:100',
            'bank_code'      => 'nullable|string|max:20',
            'bank_name'      => 'nullable|string|max:100',
            'label'          => 'nullable|string|max:80',
            'is_default'     => 'boolean',
        ]);

        // If making this default, demote all others first
        if ($request->boolean('is_default', false)) {
            PaymentProfile::where('owner_type', Specialist::class)
                ->where('owner_id', $specialist->id)
                ->update(['is_default' => false]);
        }

        $profile = PaymentProfile::create([
            ...$validated,
            'owner_type' => Specialist::class,
            'owner_id'   => $specialist->id,
            'is_default' => $request->boolean('is_default', false),
        ]);

        return response()->json($this->formatProfile($profile), 201);
    }

    /**
     * Update the authenticated specialist's payment profile.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $specialist = $request->user();

        // Ownership enforced — profile must belong to this specialist
        $profile = PaymentProfile::where('owner_type', Specialist::class)
            ->where('owner_id', $specialist->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'method'         => 'sometimes|string|in:mtn,airtel,bank',
            'phone_number'   => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:50',
            'account_name'   => 'nullable|string|max:100',
            'bank_code'      => 'nullable|string|max:20',
            'bank_name'      => 'nullable|string|max:100',
            'label'          => 'nullable|string|max:80',
            'is_default'     => 'boolean',
        ]);

        // If making default, demote others
        if (isset($validated['is_default']) && $validated['is_default'] && !$profile->is_default) {
            PaymentProfile::where('owner_type', Specialist::class)
                ->where('owner_id', $specialist->id)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        // Changing details resets verification
        $sensitiveFields = ['phone_number', 'account_number', 'account_name', 'bank_code'];
        $needsReverification = collect($sensitiveFields)->some(
            fn ($field) => isset($validated[$field]) && $validated[$field] !== $profile->{$field}
        );

        if ($needsReverification) {
            $validated['is_verified'] = false;
            $validated['verified_at'] = null;
        }

        $profile->update($validated);

        return response()->json($this->formatProfile($profile->fresh()));
    }

    /**
     * Remove the authenticated specialist's payment profile.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $specialist = $request->user();

        $profile = PaymentProfile::where('owner_type', Specialist::class)
            ->where('owner_id', $specialist->id)
            ->where('id', $id)
            ->firstOrFail();

        $wasDefault = $profile->is_default;
        $profile->delete();

        // Auto-promote another profile to default
        if ($wasDefault) {
            PaymentProfile::where('owner_type', Specialist::class)
                ->where('owner_id', $specialist->id)
                ->orderBy('created_at')
                ->first()
                ?->update(['is_default' => true]);
        }

        return response()->json(null, 204);
    }

    /**
     * Format a profile for API response.
     * Full details are returned to the owner. Sensitive fields masked for other consumers.
     */
    private function formatProfile(PaymentProfile $profile): array
    {
        return [
            'id'                  => $profile->id,
            'method'              => $profile->method,
            'label'               => $profile->label,
            'phone_number'        => $profile->phone_number,
            'account_name'        => $profile->account_name,
            'account_number'      => $profile->account_number,
            'bank_name'           => $profile->bank_name,
            'bank_code'           => $profile->bank_code,
            'is_default'          => $profile->is_default,
            'is_verified'         => $profile->is_verified,
            'verification_status' => $profile->is_verified ? 'verified' : 'pending',
            'verified_at'         => $profile->verified_at,
            'created_at'          => $profile->created_at,
        ];
    }
}

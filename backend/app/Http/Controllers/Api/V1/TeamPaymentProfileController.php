<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Compensation\CompensationController;
use App\Models\PaymentProfile;
use App\Models\Specialist;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeamPaymentProfileController extends CompensationController
{
    /**
     * Get a team member's masked payment profile (Manager view)
     */
    public function show(string $providerIdentifier, string $memberId, Request $request): JsonResponse
    {
        $provider = $this->authorizeProviderManager($providerIdentifier);

        // Resolve member (Staff or Specialist)
        $member = Staff::where('provider_id', $provider->id)
            ->when($provider->location_id, fn($q) => $q->where('location_id', $provider->location_id))
            ->where('id', $memberId)
            ->first();
        $ownerType = Staff::class;

        if (!$member) {
            // Check if they are a specialist assigned to the provider
            $assignment = \App\Models\SpecialistAssignment::where('provider_id', $provider->id)
                ->when($provider->location_id, fn($q) => $q->where('location_id', $provider->location_id))
                ->where('specialist_id', $memberId)
                ->first();

            if ($assignment) {
                $member = Specialist::find($memberId);
                $ownerType = Specialist::class;
            }
        }

        if (!$member) {
            return response()->json(['message' => 'Team member not found'], 404);
        }

        $profile = PaymentProfile::where('owner_type', $ownerType)
            ->where('owner_id', $member->id)
            ->orderByDesc('is_default')
            ->first();

        if (!$profile) {
            return response()->json(null);
        }

        return response()->json($this->formatMaskedProfile($profile));
    }

    private function formatMaskedProfile(PaymentProfile $profile): array
    {
        return [
            'method'              => $profile->method,
            'label'               => $profile->label,
            'destination'         => $this->mask($profile),
            'is_verified'         => $profile->is_verified,
            'verification_status' => $profile->is_verified ? 'verified' : 'pending',
            'created_at'          => $profile->created_at,
        ];
    }

    private function mask(PaymentProfile $profile): string
    {
        return match ($profile->method) {
            'mtn', 'airtel' => $this->maskPhone($profile->phone_number ?? ''),
            'bank'          => ($profile->bank_name ?? 'Bank') . ' ••••' . substr($profile->account_number ?? '0000', -4),
            default         => '••••',
        };
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 4) return '••••';
        return '••••' . substr($digits, -4);
    }
}

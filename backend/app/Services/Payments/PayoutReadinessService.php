<?php

namespace App\Services\Payments;

use App\Domain\Finance\Settlement\Settlement;
use App\Models\PaymentProfile;
use App\Models\PaymentMethod;

class PayoutReadinessService
{
    /**
     * Evaluate whether automated payout is available for a given settlement.
     *
     * This is the ONLY place that decides payout readiness.
     * The frontend never makes this decision — it only renders what this returns.
     *
     * Decision chain:
     *   Settlement → recipient → PaymentProfile?
     *      NO  → manual only
     *      YES → Settlement → payable → Salon → PaymentMethod (disbursement)?
     *              NO  → manual only
     *              YES → automated_available = true
     */
    public function check(Settlement $settlement): PayoutReadinessDTO
    {
        // Step 1: Does the recipient have a payout destination?
        $profile = PaymentProfile::where('owner_type', $settlement->recipient_type)
            ->where('owner_id', $settlement->recipient_id)
            ->orderByDesc('is_default')
            ->first();

        if (!$profile) {
            return PayoutReadinessDTO::manualOnly(
                reason: 'Recipient has not added a payment destination.'
            );
        }

        // Cash never goes automated — always a manual recording
        if ($profile->method === 'cash') {
            return PayoutReadinessDTO::manualOnly(
                reason: 'Cash payouts are always recorded manually.',
                destination: $this->mask($profile),
                verificationStatus: $profile->is_verified ? 'verified' : 'pending',
            );
        }

        // Step 2: Does the salon have a disbursement configuration for this method?
        $payable = $settlement->payable;
        $salonId = $payable->salon_id ?? $payable->id;

        $config = PaymentMethod::where('salon_id', $salonId)
            ->where('provider', $profile->method . '_disbursement')
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return new PayoutReadinessDTO(
                automatedAvailable: false,
                method: $profile->method,
                destination: $this->mask($profile),
                verificationStatus: $profile->is_verified ? 'verified' : 'pending',
                manualAvailable: true,
                reason: 'Salon has not configured automated disbursement for ' . strtoupper($profile->method) . '.',
            );
        }

        // All conditions met — automated payout is available
        return new PayoutReadinessDTO(
            automatedAvailable: true,
            method: $profile->method,
            destination: $this->mask($profile),
            verificationStatus: $profile->is_verified ? 'verified' : 'pending',
            manualAvailable: true,
            reason: null,
        );
    }

    /**
     * Mask sensitive destination details for public/manager display.
     */
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

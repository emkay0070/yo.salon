<?php

namespace App\Services;

use App\Models\PaymentMethod;

class FeeEngine
{
    /**
     * Calculate the various fees and net amount for a transaction.
     * 
     * @param float $grossAmount
     * @param PaymentMethod|null $paymentMethod
     * @return array
     */
    public function calculateFees(float $grossAmount, ?PaymentMethod $paymentMethod): array
    {
        $gatewayFee = 0.0;
        $platformFee = 0.0;
        $taxAmount = 0.0;

        // Platform fee is generally a fixed amount or percentage.
        // For Yo.Salon, we can define a standard platform fee, e.g. UGX 2,500 
        // We'll set it to 0 for Cash to be fair to salons, or keep it. Let's make it 2500 for digital.
        
        $methodType = $paymentMethod ? strtolower($paymentMethod->type) : 'cash';
        
        if ($methodType === 'cash') {
            // Cash transactions typically have no gateway or platform fees in basic tiers
            $gatewayFee = 0.0;
            $platformFee = 0.0;
        } elseif ($methodType === 'mobile_money') {
            // Mobile money gateways usually charge ~2%
            $gatewayFee = $grossAmount * 0.02;
            $platformFee = 2500.0; // Flat platform fee
        } elseif ($methodType === 'card' || $methodType === 'credit_card') {
            // Cards usually charge ~3.5%
            $gatewayFee = $grossAmount * 0.035;
            $platformFee = 2500.0; 
        } else {
            // Default fallback
            $gatewayFee = $grossAmount * 0.02;
            $platformFee = 2500.0;
        }

        // Calculate Net Amount
        $netAmount = $grossAmount - $gatewayFee - $platformFee - $taxAmount;

        return [
            'gross_amount' => $grossAmount,
            'gateway_fee' => $gatewayFee,
            'platform_fee' => $platformFee,
            'tax_amount' => $taxAmount,
            'net_amount' => $netAmount,
        ];
    }
}

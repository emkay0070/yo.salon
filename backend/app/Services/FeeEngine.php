<?php

namespace App\Services;

use App\Models\PaymentMethod;

/**
 * FeeEngine - Optional fee calculation service
 * 
 * This service is optional and can be used when the platform
 * wants to implement fee-based revenue models. For now,
 * fees are not calculated in the core payment flow.
 */
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
        // For now, return zero fees - platform operates without transaction fees
        // This can be enabled later when implementing subscription-based revenue
        
        return [
            'gross_amount' => $grossAmount,
            'gateway_fee' => 0.0,
            'platform_fee' => 0.0,
            'tax_amount' => 0.0,
            'net_amount' => $grossAmount,
        ];
    }

    /**
     * Calculate fees with custom configuration (for future use)
     * 
     * @param float $grossAmount
     * @param PaymentMethod|null $paymentMethod
     * @param array $config Custom fee configuration
     * @return array
     */
    public function calculateFeesWithConfig(float $grossAmount, ?PaymentMethod $paymentMethod, array $config): array
    {
        $gatewayFee = 0.0;
        $platformFee = 0.0;
        $taxAmount = 0.0;

        $methodType = $paymentMethod ? strtolower($paymentMethod->type) : 'cash';
        
        // Use custom config if provided, otherwise use defaults
        $mobileMoneyRate = $config['mobile_money_rate'] ?? 0.02;
        $cardRate = $config['card_rate'] ?? 0.035;
        $platformFeeAmount = $config['platform_fee'] ?? 0;
        
        if ($methodType === 'cash') {
            $gatewayFee = 0.0;
            $platformFee = 0.0;
        } elseif ($methodType === 'mobile_money') {
            $gatewayFee = $grossAmount * $mobileMoneyRate;
            $platformFee = $platformFeeAmount;
        } elseif ($methodType === 'card' || $methodType === 'credit_card') {
            $gatewayFee = $grossAmount * $cardRate;
            $platformFee = $platformFeeAmount;
        } else {
            $gatewayFee = $grossAmount * 0.02;
            $platformFee = $platformFeeAmount;
        }

        $netAmount = $grossAmount - $gatewayFee - $platformFee - $taxAmount;

        return [
            'gross_amount' => $grossAmount,
            'gateway_fee' => $gatewayFee,
            'platform_fee' => $platformFee,
            'tax_amount' => $taxAmount,
            'net_amount' => $netAmount,
        ];
    }

    /**
     * Calculate fees from provider response (actual gateway fees)
     * 
     * This method uses the actual fees returned by the payment provider
     * to ensure accurate net amount calculation.
     * 
     * @param float $grossAmount
     * @param PaymentMethod|null $paymentMethod
     * @param array $providerResponse Provider's verification response
     * @return array
     */
    public function calculateFeesFromProvider(float $grossAmount, ?PaymentMethod $paymentMethod, array $providerResponse): array
    {
        // Extract gateway fee from provider response
        $gatewayFee = (float) ($providerResponse['fees'] ?? 0.0);
        
        // Platform fee remains zero (zero platform commission philosophy)
        $platformFee = 0.0;
        $taxAmount = 0.0;
        
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

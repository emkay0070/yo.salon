<?php

namespace App\Domain\Catalog\Engines;

use App\Models\Service;
use App\Models\Location;
use App\Models\LocationBookingConfig;
use App\Models\ProviderBookingConfig;

/**
 * PricingEngine
 * 
 * Computes pricing and deposit rules for a specific service at a specific branch (Location).
 * Takes in a Service model that has already been joined with its branch pivot overrides.
 */
class PricingEngine
{
    public function __construct(
        protected Location $location,
        protected Service $resolvedService
    ) {}

    /**
     * Effective price for a booking at this location.
     * Resolution: Location override → Provider (service) price
     */
    public function effectivePrice(): float
    {
        return $this->resolvedService->pivot_price_override
            ?? $this->resolvedService->price;
    }

    /**
     * Is a deposit required for this booking?
     * Resolution: Location-service override → Location payment policy override → Provider payment policy
     */
    public function requiresDeposit(): bool
    {
        // Explicit override on the pivot takes precedence
        if (!is_null($this->resolvedService->pivot_deposit_required_override)) {
            return (bool) $this->resolvedService->pivot_deposit_required_override;
        }

        $paymentPolicy = $this->resolvePaymentPolicy();
        
        $strategy = $paymentPolicy['strategy'] ?? 'no_payment';
        if ($strategy !== 'deposit' && $strategy !== 'full_payment') {
            return false;
        }
        
        // Match legacy 'deposit_required_for' mappings if present, default to true
        $depositRequiredFor = $paymentPolicy['deposit']['required_for'] ?? 'all';
        $minAmount = $paymentPolicy['deposit']['min_service_amount'] ?? 0;
        
        return match ($depositRequiredFor) {
            'all'     => true,
            'above'   => $this->effectivePrice() >= $minAmount,
            default   => true, // if strategy is deposit and no constraints, require it
        };
    }

    /**
     * Deposit amount in currency units (not percentage).
     */
    public function depositAmount(): float
    {
        if (!$this->requiresDeposit()) {
            return 0;
        }

        $paymentPolicy = $this->resolvePaymentPolicy();
        $strategy = $paymentPolicy['strategy'] ?? 'no_payment';
        
        if ($strategy === 'full_payment') {
            return $this->effectivePrice();
        }

        $type = $paymentPolicy['deposit']['type'] ?? 'percentage';
        $amount = $paymentPolicy['deposit']['amount'] ?? 0;
        $price = $this->effectivePrice();

        return match ($type) {
            'percentage' => round($price * ($amount / 100), 2),
            'fixed'      => (float) $amount,
            default      => 0,
        };
    }
    
    protected function resolvePaymentPolicy(): array
    {
        $locationConfig = LocationBookingConfig::where('location_id', $this->location->id)->first();
        if ($locationConfig && !empty($locationConfig->payment_policy_override)) {
            return $locationConfig->payment_policy_override;
        }
        
        $providerConfig = ProviderBookingConfig::where('provider_id', $this->location->provider_id)->first();
        if ($providerConfig) {
            return $providerConfig->payment_policy ?? $providerConfig->getDefaultPaymentPolicy();
        }
        
        // absolute fallback
        return (new ProviderBookingConfig())->getDefaultPaymentPolicy();
    }
}

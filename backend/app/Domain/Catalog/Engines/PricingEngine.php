<?php

namespace App\Domain\Catalog\Engines;

use App\Models\Salon;
use App\Models\Service;

/**
 * PricingEngine
 * 
 * Computes pricing and deposit rules for a specific service at a specific branch.
 * Takes in a Service model that has already been joined with its branch pivot overrides.
 */
class PricingEngine
{
    public function __construct(
        protected Salon $salon,
        protected Service $resolvedService
    ) {}

    /**
     * Effective price for a booking at this salon.
     * Resolution: Salon override → Provider (service) price
     */
    public function effectivePrice(): float
    {
        return $this->resolvedService->pivot_price_override
            ?? $this->resolvedService->price;
    }

    /**
     * Is a deposit required for this booking?
     * Resolution: Salon-service override → Salon booking deposit policy
     */
    public function requiresDeposit(): bool
    {
        // Explicit override on the pivot takes precedence
        if (!is_null($this->resolvedService->pivot_deposit_required_override)) {
            return (bool) $this->resolvedService->pivot_deposit_required_override;
        }

        // Fall back to salon booking deposit policy
        if (!$this->salon->booking_deposit_enabled) {
            return false;
        }

        return match ($this->salon->deposit_required_for) {
            'all'     => true,
            'above'   => $this->effectivePrice() >= ($this->salon->deposit_min_service_amount ?? 0),
            default   => false,
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

        $price = $this->effectivePrice();

        return match ($this->salon->deposit_type) {
            'percentage' => round($price * ($this->salon->deposit_value / 100), 2),
            'fixed'      => (float) ($this->salon->deposit_value ?? 0),
            default      => 0,
        };
    }
}

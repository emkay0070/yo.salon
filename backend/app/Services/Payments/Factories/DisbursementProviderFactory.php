<?php

namespace App\Services\Payments\Factories;

use App\Services\Payments\Contracts\DisbursementProvider;
use App\Services\Payments\Providers\MTNDisbursementProvider;
use App\Services\Payments\Providers\AirtelDisbursementProvider;
use App\Services\Payments\Providers\BankDisbursementProvider;

class DisbursementProviderFactory
{
    /**
     * Resolve the appropriate disbursement provider based on the method.
     * 
     * @param string $method The payout method (mtn, airtel, bank, etc.)
     * @return DisbursementProvider
     * @throws \InvalidArgumentException If the method is not supported
     */
    public static function make(string $method): DisbursementProvider
    {
        return match ($method) {
            'mtn', 'mtn_momo' => new MTNDisbursementProvider(),
            'airtel', 'airtel_money' => new AirtelDisbursementProvider(),
            'bank', 'bank_transfer' => new BankDisbursementProvider(),
            default => throw new \InvalidArgumentException("Disbursement provider not supported for method: {$method}"),
        };
    }
}

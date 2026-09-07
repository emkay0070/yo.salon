<?php

namespace App\Domain\Finance\PostingPolicies;

use App\Models\Transaction;

interface PostingPolicyInterface
{
    /**
     * Determine which ledger account type receives the gateway fee expense.
     * Returns the owner entity (salon, platform, or null if absorbed/ignored).
     *
     * @return string 'salon' | 'platform'
     */
    public function gatewayFeeBearer(Transaction $transaction): string;

    /**
     * Returns the asset account type for this custody model.
     *
     * @return string 'mobile_money' | 'cash' | 'bank'
     */
    public function assetAccountType(Transaction $transaction): string;
}

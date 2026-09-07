<?php

namespace App\Domain\Finance\PostingPolicies;

use App\Models\Transaction;
use App\Models\PaymentAccount;

class PostingPolicyResolver
{
    /**
     * Resolve the appropriate posting policy for a given transaction and custody model.
     */
    public function resolve(Transaction $transaction, string $custodyModel): PostingPolicyInterface
    {
        // Future: If marketplace is active or custody is platform, return a different policy
        // if ($custodyModel === PaymentAccount::TYPE_SUBACCOUNT || $custodyModel === PaymentAccount::TYPE_ROUTING) {
        //     return new PlatformFacilitatedPostingPolicy();
        // }

        return new DefaultMerchantPostingPolicy();
    }
}

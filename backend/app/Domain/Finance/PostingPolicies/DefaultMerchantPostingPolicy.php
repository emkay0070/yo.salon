<?php

namespace App\Domain\Finance\PostingPolicies;

use App\Models\Transaction;

class DefaultMerchantPostingPolicy implements PostingPolicyInterface
{
    public function gatewayFeeBearer(Transaction $transaction): string
    {
        return 'salon'; // Salon bears the gateway fee for merchant/manual custody
    }

    public function assetAccountType(Transaction $transaction): string
    {
        $type = $transaction->paymentMethod?->type;

        return match($type) {
            'mobile_money' => 'mobile_money',
            'card'         => 'bank',
            'cash'         => 'cash',
            default        => 'cash',
        };
    }
}

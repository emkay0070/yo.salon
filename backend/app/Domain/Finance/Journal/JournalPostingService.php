<?php

namespace App\Domain\Finance\Journal;

use App\Models\Transaction;
use App\Models\PaymentAccount;
use App\Models\Salon;
use App\Domain\Finance\FinancialTransaction;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Domain\Finance\Events\JournalPosted;
use App\Domain\Finance\PostingPolicies\PostingPolicyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalPostingService
{
    public function __construct(
        protected PostingPolicyResolver $postingPolicyResolver
    ) {}

    /**
     * Post the financial journal for a completed Transaction.
     * Idempotent: safe to call multiple times for the same transaction.
     */
    public function postForTransaction(Transaction $transaction): void
    {
        // 1. Idempotency check
        if (FinancialTransaction::where('reference_type', Transaction::class)
            ->where('reference_id', $transaction->id)
            ->exists()) {
            Log::info('Journal already posted for transaction', ['transaction_id' => $transaction->id]);
            return;
        }

        // 2. Resolve custody model
        $custodyModel = $this->resolveCustodyModel($transaction);

        // 3. Refuse to post platform-facilitated flows (Phase 3)
        if (in_array($custodyModel, [PaymentAccount::TYPE_SUBACCOUNT, PaymentAccount::TYPE_ROUTING])) {
            Log::warning('Journal posting for platform-facilitated flow is deferred', [
                'transaction_id' => $transaction->id,
                'custody_model' => $custodyModel
            ]);
            return;
        }

        // 4. Load PostingPolicy for this transaction
        $policy = $this->postingPolicyResolver->resolve($transaction, $custodyModel);

        // 5. Build journal movements
        $movements = $this->buildMovements($transaction, $policy);

        if (empty($movements)) {
            Log::info('No journal movements generated for transaction', ['transaction_id' => $transaction->id]);
            return;
        }

        // 6. Assert balance (debit sum == credit sum)
        $this->assertBalance($movements);

        // 7. Post inside DB transaction
        DB::transaction(function () use ($transaction, $movements) {
            $financialTransaction = FinancialTransaction::createWithJournals(
                type: $transaction->type === 'refund' ? 'refund' : 'payment',
                description: ucfirst($transaction->type) . " for booking #{$transaction->booking_id}",
                journalData: [
                    [
                        'type' => $transaction->type === 'refund' ? 'refund' : 'payment',
                        'description' => ucfirst($transaction->type) . " for booking #{$transaction->booking_id}",
                        'movements' => $movements,
                    ]
                ],
                referenceType: Transaction::class,
                referenceId: $transaction->id
            );


        });
    }

    protected function resolveCustodyModel(Transaction $transaction): string
    {
        if ($transaction->paymentAccount) {
            return $transaction->paymentAccount->account_type;
        }

        return match ($transaction->paymentMethod?->type) {
            'mobile_money', 'card' => PaymentAccount::TYPE_MERCHANT,
            'cash'                 => PaymentAccount::TYPE_MANUAL,
            default                => PaymentAccount::TYPE_MANUAL,
        };
    }

    protected function buildMovements(Transaction $transaction, $policy): array
    {
        $movements = [];
        $isRefund = $transaction->type === 'refund';
        $salonId = $transaction->salon_id;

        $assetType = $policy->assetAccountType($transaction);
        $feeBearer = $policy->gatewayFeeBearer($transaction);

        // Get accounts
        $assetAccount = LedgerAccount::getOrCreateFor(Salon::class, $salonId, $assetType, $transaction->currency);
        $revenueAccount = LedgerAccount::getOrCreateFor(Salon::class, $salonId, 'revenue', $transaction->currency);
        
        $processingCostAccount = null;
        if ($feeBearer === 'salon') {
            $processingCostAccount = LedgerAccount::getOrCreateFor(Salon::class, $salonId, 'processing_cost', $transaction->currency);
        }

        $grossAmount = (float) $transaction->gross_amount;
        $netAmount = (float) $transaction->net_amount;
        $gatewayFee = (float) $transaction->gateway_fee;

        // For refunds, gross_amount is negative. Check absolute value.
        if (abs($grossAmount) <= 0) {
            return [];
        }

        if ($isRefund) {
            // REVERSAL JOURNAL
            // Use absolute values since refund amounts are stored as negative
            $absGross = abs($grossAmount);
            $absNet = abs($netAmount);
            $absFee = abs($gatewayFee);

            $movements[] = [
                'ledger_account_id' => $revenueAccount->id,
                'type' => 'debit',
                'amount' => $absGross,
                'description' => "Refund for booking #{$transaction->booking_id}",
                'metadata' => ['transaction_id' => $transaction->id],
            ];

            $movements[] = [
                'ledger_account_id' => $assetAccount->id,
                'type' => 'credit',
                'amount' => $absNet,
                'description' => "Refund payout for booking #{$transaction->booking_id}",
                'metadata' => ['transaction_id' => $transaction->id],
            ];

            // If fee is not refunded, we still credit processing cost for accounting balance
            if ($processingCostAccount && $absFee > 0) {
                $movements[] = [
                    'ledger_account_id' => $processingCostAccount->id,
                    'type' => 'credit',
                    'amount' => $absFee,
                    'description' => "Processing cost reversal for refund #{$transaction->booking_id}",
                    'metadata' => ['transaction_id' => $transaction->id],
                ];
            }
        } else {
            // PAYMENT JOURNAL
            $movements[] = [
                'ledger_account_id' => $assetAccount->id,
                'type' => 'debit',
                'amount' => $netAmount,
                'description' => "Payment received for booking #{$transaction->booking_id}",
                'metadata' => ['transaction_id' => $transaction->id],
            ];

            if ($processingCostAccount && $gatewayFee > 0) {
                $movements[] = [
                    'ledger_account_id' => $processingCostAccount->id,
                    'type' => 'debit',
                    'amount' => $gatewayFee,
                    'description' => "Processing fee for booking #{$transaction->booking_id}",
                    'metadata' => ['transaction_id' => $transaction->id],
                ];
            }

            $movements[] = [
                'ledger_account_id' => $revenueAccount->id,
                'type' => 'credit',
                'amount' => $grossAmount,
                'description' => "Service revenue for booking #{$transaction->booking_id}",
                'metadata' => ['transaction_id' => $transaction->id],
            ];
        }

        return $movements;
    }

    protected function assertBalance(array $movements): void
    {
        $debits = 0.0;
        $credits = 0.0;

        foreach ($movements as $movement) {
            if ($movement['type'] === 'debit') {
                $debits += (float) $movement['amount'];
            } else {
                $credits += (float) $movement['amount'];
            }
        }

        // Use a small epsilon for floating point comparison
        if (abs($debits - $credits) > 0.001) {
            throw new \RuntimeException(sprintf('Journal movements do not balance. Debits: %s, Credits: %s', $debits, $credits));
        }
    }
}

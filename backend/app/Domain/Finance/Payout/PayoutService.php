<?php

namespace App\Domain\Finance\Payout;

use App\Domain\Finance\Payout\Execution\AutomatedExecution;
use App\Domain\Finance\Payout\Execution\ManualExecution;
use App\Domain\Finance\Settlement\Settlement;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    /**
     * Initiate and optionally execute a payout against a settlement.
     * 
     * @param Settlement $settlement The obligation to fulfill
     * @param float $amount The amount to pay
     * @param string $method 'mtn', 'airtel', 'bank', 'cash'
     * @param string $executionMode 'manual' or 'automated'
     * @param array $payload Additional context (provider_reference, processor_id, etc.)
     * @return Payout
     */
    public function initiatePayout(
        Settlement $settlement,
        float $amount,
        string $method,
        string $executionMode = 'manual',
        array $payload = []
    ): Payout {
        if ($settlement->status === Settlement::STATUS_PAID) {
            throw new \LogicException('Cannot payout a completed settlement.');
        }

        if ($amount <= 0 || $amount > $settlement->getRemainingAmountAttribute()) {
            throw new \InvalidArgumentException('Invalid payout amount.');
        }

        return DB::transaction(function () use ($settlement, $amount, $method, $executionMode, $payload) {
            
            // Lock the settlement to prevent race conditions (double spending)
            $lockedSettlement = Settlement::where('id', $settlement->id)->lockForUpdate()->first();
            
            if ($amount > $lockedSettlement->getRemainingAmountAttribute()) {
                throw new \InvalidArgumentException('Invalid payout amount due to concurrent modifications.');
            }

            $payout = Payout::create([
                'idempotency_key'    => (string) \Illuminate\Support\Str::uuid(),
                'settlement_id'      => $settlement->id,
                'recipient_type'     => $settlement->recipient_type,
                'recipient_id'       => $settlement->recipient_id,
                'amount'             => $amount,
                'currency'           => $settlement->currency,
                'method'             => $method,
                'execution_mode'     => $executionMode,
                'status'             => 'pending',
                'provider'           => $payload['provider'] ?? null,
                'provider_reference' => $payload['provider_reference'] ?? null,
                'processed_by'       => $payload['processor_id'] ?? null,
                'initiated_at'       => now(),
            ]);

            return $payout;
        });

        // Execute outside the transaction to avoid holding the lock during HTTP calls
        $this->executePayout($payout, $payload);

        return $payout;
    }

    /**
     * Execute an existing pending/retrying payout.
     */
    public function executePayout(Payout $payout, array $payload = []): void
    {
        $strategy = $this->resolveExecutionStrategy($payout->execution_mode);
        
        if (!$strategy->supports($payout->method)) {
            throw new \RuntimeException("Execution mode '{$payout->execution_mode}' does not support method '{$payout->method}'.");
        }

        $strategy->execute($payout, $payload);
    }

    private function resolveExecutionStrategy(string $mode)
    {
        return match ($mode) {
            'manual'    => new ManualExecution(),
            'automated' => new AutomatedExecution(),
            default     => throw new \InvalidArgumentException("Unknown execution mode: {$mode}"),
        };
    }
}

<?php

namespace App\Domain\Finance\Payout\Execution;

use App\Domain\Finance\Payout\Payout;

/**
 * Interface for Payout Execution Strategies.
 */
interface ExecutionStrategy
{
    /**
     * Determine if this strategy can handle the given payout method.
     */
    public function supports(string $method): bool;

    /**
     * Execute the payout.
     * 
     * @param Payout $payout The pending payout record.
     * @param array $payload Any additional data needed for execution (e.g. references, bank details).
     * @return void
     */
    public function execute(Payout $payout, array $payload = []): void;
}

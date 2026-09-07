<?php

namespace App\Domain\Finance\Payout\Execution;

use App\Domain\Finance\Payout\Payout;

class ManualExecution implements ExecutionStrategy
{
    public function supports(string $method): bool
    {
        // For V1, manual execution supports everything.
        return true; 
    }

    public function execute(Payout $payout, array $payload = []): void
    {
        // In manual mode, we assume the manager has already transferred the money,
        // and is simply recording the fact. So it completes immediately.
        
        $payout->markAsCompleted($payload['provider_reference'] ?? null);
    }
}

<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Domain\Finance\Settlement\Settlement;
use App\Services\Payments\PayoutReadinessService;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPayouts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(PayoutReadinessService $readinessService): void
    {
        Log::info('ProcessScheduledPayouts: Starting sweep for eligible settlements.');

        // 1. Find all pending, due settlements
        Settlement::where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->chunk(100, function ($settlements) use ($readinessService) {
                foreach ($settlements as $settlement) {
                    $this->processSettlement($settlement, $readinessService);
                }
            });

        Log::info('ProcessScheduledPayouts: Completed sweep.');
    }

    private function processSettlement(Settlement $settlement, PayoutReadinessService $readinessService): void
    {
        try {
            // Re-check remaining amount
            if ($settlement->getRemainingAmountAttribute() <= 0) {
                return;
            }

            // 2. Check if automation is enabled and ready
            $readiness = $readinessService->check($settlement);

            if (!$readiness->automatedAvailable) {
                // Not eligible for automated payout
                return;
            }

            // 3. Ensure no active/processing payouts exist for this settlement
            $activePayouts = $settlement->payouts()
                ->whereIn('status', ['pending', 'processing', 'retrying'])
                ->exists();

            if ($activePayouts) {
                // Skip - there's already a payout in flight
                return;
            }

            // 4. Dispatch the execution job
            ExecutePayoutJob::dispatch(
                $settlement->id,
                $settlement->getRemainingAmountAttribute(),
                $readiness->method
            );

        } catch (\Exception $e) {
            Log::error("ProcessScheduledPayouts Error for Settlement {$settlement->id}: " . $e->getMessage());
        }
    }
}

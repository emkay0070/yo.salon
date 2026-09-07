<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Domain\Finance\Settlement\Settlement;
use App\Domain\Finance\Payout\PayoutService;
use App\Domain\Finance\Payout\Payout;
use App\Services\Payments\Factories\DisbursementProviderFactory;
use Illuminate\Support\Facades\Log;

class ExecutePayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public string $settlementId;
    public float $amount;
    public string $method;

    /**
     * Create a new job instance.
     */
    public function __construct(string $settlementId, float $amount, string $method)
    {
        $this->settlementId = $settlementId;
        $this->amount = $amount;
        $this->method = $method;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1m, 5m, 15m
    }

    /**
     * Execute the job.
     */
    public function handle(PayoutService $payoutService): void
    {
        $settlement = Settlement::findOrFail($this->settlementId);

        $existingPayout = null;

        if ($this->attempts() > 1) {
            // We are retrying. We need to check if there's an existing payout for this settlement
            // that is stuck in a state where we aren't sure if it succeeded.
            $existingPayout = $settlement->payouts()
                ->where('method', $this->method)
                ->whereIn('status', ['processing', 'retrying', 'pending'])
                ->latest()
                ->first();

            if ($existingPayout) {
                // Before we do anything, RECONCILE!
                $this->reconcilePayout($existingPayout);
                
                // If reconciliation marked it as completed, we are done.
                if ($existingPayout->refresh()->status === 'completed') {
                    Log::info("ExecutePayoutJob: Payout {$existingPayout->id} was completed during reconciliation. Stopping retry.");
                    return;
                }
                
                // If it's still processing, we shouldn't initiate another one yet.
                // We'll throw to trigger the next backoff.
                if ($existingPayout->status === 'processing') {
                    throw new \Exception("ExecutePayoutJob: Payout {$existingPayout->id} is still processing. Cannot retry yet.");
                }
            }
        }

        try {
            if ($existingPayout && $this->attempts() > 1) {
                // It's a retry and it's not completed/processing, so we can re-execute the SAME payout
                // (which preserves the idempotency key)
                $payoutService->executePayout($existingPayout);
            } else {
                // Check again to ensure we don't overpay before initiating a new one
                if ($settlement->getRemainingAmountAttribute() <= 0) {
                    Log::info("ExecutePayoutJob: Settlement {$this->settlementId} is already fully paid or has active payouts.");
                    return;
                }

                $payoutService->initiatePayout(
                    $settlement,
                    $this->amount,
                    $this->method,
                    'automated'
                );
            }
        } catch (\Exception $e) {
            Log::error("ExecutePayoutJob: Failed to execute payout for settlement {$this->settlementId}: " . $e->getMessage());
            // Throwing triggers the job retry and exponential backoff
            throw $e;
        }
    }
    
    private function reconcilePayout(Payout $payout): void
    {
        try {
            $providerName = explode('_', $payout->method)[0] ?? $payout->method; // e.g., 'mtn_disbursement' -> 'mtn'
            $provider = DisbursementProviderFactory::make($providerName);
            
            // Reconcile via status API
            $response = $provider->checkStatus($payout->idempotency_key);
            
            if ($response->success) {
                $status = $response->status; // completed, failed, processing
                
                if ($status === 'completed') {
                    $payout->markAsCompleted($response->providerReference);
                } elseif ($status === 'failed') {
                    $payout->markAsFailed('Failed via status reconciliation check');
                }
            }
        } catch (\Exception $e) {
            Log::warning("ExecutePayoutJob: Failed to reconcile payout {$payout->id}: " . $e->getMessage());
        }
    }
}

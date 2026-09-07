<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Domain\Finance\Payout\Payout;
use App\Services\Payments\Factories\DisbursementProviderFactory;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReconcileProcessingPayouts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ReconcileProcessingPayouts: Starting sweep for stuck payouts.');

        // Find payouts stuck in processing for more than 2 hours
        Payout::where('status', 'processing')
            ->where('updated_at', '<=', Carbon::now()->subHours(2))
            ->chunk(50, function ($payouts) {
                foreach ($payouts as $payout) {
                    $this->reconcile($payout);
                }
            });

        Log::info('ReconcileProcessingPayouts: Completed sweep.');
    }

    private function reconcile(Payout $payout): void
    {
        try {
            $providerName = explode('_', $payout->method)[0] ?? $payout->method;
            $provider = DisbursementProviderFactory::make($providerName);
            
            // Assuming MTN/others use the idempotency_key as their reference ID
            $referenceId = $payout->idempotency_key ?: $payout->reference;

            if (!$referenceId) {
                Log::warning("ReconcileProcessingPayouts: Payout {$payout->id} has no reference to check.");
                return;
            }

            $response = $provider->checkStatus($referenceId);
            
            if ($response->success) {
                $status = $response->status;
                
                if ($status === 'completed') {
                    $payout->markAsCompleted($response->providerReference);
                    Log::info("ReconcileProcessingPayouts: Payout {$payout->id} successfully reconciled as completed.");
                } elseif ($status === 'failed') {
                    $payout->markAsFailed('Failed via fallback reconciliation sweep');
                    Log::info("ReconcileProcessingPayouts: Payout {$payout->id} successfully reconciled as failed.");
                } else {
                    Log::info("ReconcileProcessingPayouts: Payout {$payout->id} is still processing at provider.");
                }
            } else {
                Log::warning("ReconcileProcessingPayouts: Failed to check status for {$payout->id}: {$response->message}");
            }
        } catch (\Exception $e) {
            Log::error("ReconcileProcessingPayouts Error for Payout {$payout->id}: " . $e->getMessage());
        }
    }
}

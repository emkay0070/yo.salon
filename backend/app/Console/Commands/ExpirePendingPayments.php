<?php

namespace App\Console\Commands;

use App\Models\PaymentRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:expire-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending payment requests that have passed their expiry time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredCount = PaymentRequest::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired', 'completed_at' => now()]);

        if ($expiredCount > 0) {
            Log::info('Expired pending payment requests', [
                'count' => $expiredCount,
            ]);
            $this->info("Expired {$expiredCount} pending payment requests.");
        } else {
            $this->info('No pending payment requests to expire.');
        }

        return Command::SUCCESS;
    }
}

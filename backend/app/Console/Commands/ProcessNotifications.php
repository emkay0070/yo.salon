<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessNotifications extends Command
{
    protected $signature = 'notifications:process {--limit=50}';
    protected $description = 'Process pending notification jobs';

    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Processing up to {$limit} pending notification jobs...");

        try {
            $results = $this->notificationService->processPendingJobs($limit);

            $this->table(
                ['Status', 'Count'],
                [
                    ['Sent', $results['sent']],
                    ['Failed', $results['failed']],
                    ['Skipped', $results['skipped']],
                ]
            );

            $this->info("Notification processing completed.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Notification processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error("Failed to process notifications: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:process {--limit=50 : Maximum number of reminders to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due booking reminders and create notification jobs';

    /**
     * Execute the console command.
     */
    public function handle(ReminderService $reminderService): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Processing due reminders (limit: {$limit})...");

        try {
            $results = $reminderService->processDueReminders($limit);

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Scheduled', $results['scheduled']],
                    ['Skipped', $results['skipped']],
                    ['Failed', $results['failed']],
                ]
            );

            $this->info("Reminder processing completed.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Reminder processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error("Reminder processing failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}

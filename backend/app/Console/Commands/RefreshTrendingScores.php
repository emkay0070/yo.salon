<?php

namespace App\Console\Commands;

use App\Services\TrendingScoreService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trending:refresh {--type= : Specific type to refresh (salon, service, specialist)}')]
#[Description('Refresh trending scores for salons, services, and specialists')]
class RefreshTrendingScores extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refreshing trending scores...');

        $service = app(TrendingScoreService::class);

        $type = $this->option('type');

        if (!$type || $type === 'salon') {
            $this->info('Refreshing salon trending scores...');
            $service->refreshAllSalonScores();
            $this->info('Salon trending scores refreshed.');
        }

        if (!$type || $type === 'service') {
            $this->info('Refreshing service trending scores...');
            $service->refreshAllServiceScores();
            $this->info('Service trending scores refreshed.');
        }

        if (!$type || $type === 'specialist') {
            $this->info('Refreshing specialist trending scores...');
            $service->refreshAllSpecialistScores();
            $this->info('Specialist trending scores refreshed.');
        }

        $this->info('All trending scores refreshed successfully.');

        return Command::SUCCESS;
    }
}

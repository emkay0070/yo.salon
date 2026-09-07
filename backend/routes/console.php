<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Jobs\ProcessSubscriptionRenewals;

// Schedule trending score refresh daily at midnight
Schedule::command('trending:refresh')->daily();

// Process subscription renewals every hour
Schedule::job(new ProcessSubscriptionRenewals)->hourly();

// Sweep eligible pending settlements for automated payouts
Schedule::job(new \App\Jobs\ProcessScheduledPayouts)->hourly();

// Reconcile payouts stuck in processing state
Schedule::job(new \App\Jobs\ReconcileProcessingPayouts)->hourly();

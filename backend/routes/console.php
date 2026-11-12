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

// Auto-approve pending bookings that have passed the configured threshold.
// Runs every 15 minutes — auto-approval windows are in hours, not seconds.
Schedule::call(function () {
    app(\App\Services\BookingService::class)->processAutoApprovals();
})->everyFifteenMinutes()->name('booking.auto-approval')->withoutOverlapping();

// Process booking operational time states based on scheduled appointment time.
// Runs every minute for precise time-state tracking.
Schedule::command('bookings:process-time-states')->everyMinute()->name('booking.time-states')->withoutOverlapping();

// Expire pending payment requests that have passed their expiry time.
// Runs every minute to promptly release booking holds when payments expire.
Schedule::command('payments:expire-pending')->everyMinute()->name('payments.expire-pending')->withoutOverlapping();


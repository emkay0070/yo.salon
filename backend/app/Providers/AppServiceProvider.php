<?php

namespace App\Providers;

use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\FlutterwaveProvider;
use App\Services\Payments\MTNMomoService;
use App\Services\Payments\AirtelProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentProviderInterface::class, FlutterwaveProvider::class);
        $this->app->bind('payment.flutterwave', FlutterwaveProvider::class);
        $this->app->bind('payment.mtn', MTNMomoService::class);
        $this->app->bind('payment.airtel', AirtelProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * All event listeners are registered via Laravel's Event Auto-Discovery, which
     * scans App\Listeners and registers any public method matching handle* or __invoke
     * that has a typed first parameter (DiscoverEvents.php, line 87).
     *
     * This includes PostJournalForPayment@handlePaymentCaptured and
     * PostJournalForPayment@handleRefundCaptured — the handle* wildcard matches
     * non-default method names as well (confirmed via Str::is source).
     *
     * NO manual Event::listen() registrations belong here. Any such registration
     * for a listener already in App\Listeners would cause that listener to fire twice.
     *
     * NOTE: A previous comment incorrectly stated that AddBookingActivity,
     * CreateBookingNotification, AddPaymentActivity, CreatePaymentNotification and
     * CreateRefundNotification define both handle() and __invoke() methods, causing
     * duplicate execution. Investigation confirmed that none of these listeners
     * define an __invoke() method — each has only handle(). Event Auto-Discovery
     * therefore registers each listener exactly once. No duplicate execution occurs.
     */
    public function boot(): void
    {
        // Intentionally empty. All listeners managed by Event Auto-Discovery.
    }
}


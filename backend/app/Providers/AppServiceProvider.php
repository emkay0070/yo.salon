<?php

namespace App\Providers;

use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\FlutterwaveProvider;
use App\Services\Payments\MTNProvider;
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
        $this->app->bind('payment.mtn', MTNProvider::class);
        $this->app->bind('payment.airtel', AirtelProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register event listeners - separated by responsibility
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PaymentConfirmed::class,
            [\App\Listeners\ProcessPaymentInFinanceDomain::class, 'handle']
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PaymentConfirmed::class,
            [\App\Listeners\CreatePaymentNotification::class, 'handle']
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PaymentConfirmed::class,
            [\App\Listeners\AddPaymentActivity::class, 'handle']
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PaymentConfirmed::class,
            [\App\Listeners\BroadcastNotification::class, 'handle']
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Finance\Events\PaymentCaptured::class,
            [\App\Listeners\PostJournalForPayment::class, 'handlePaymentCaptured']
        );

        // Refund event listeners
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\RefundConfirmed::class,
            [\App\Listeners\ProcessRefundInFinanceDomain::class, 'handle']
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Finance\Events\RefundCaptured::class,
            [\App\Listeners\PostJournalForPayment::class, 'handleRefundCaptured']
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Finance\Events\RevenueDistributionRequested::class,
            [\App\Listeners\ApplyRevenueDistribution::class, 'handle']
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Finance\Events\EarningRecognized::class,
            [\App\Listeners\Finance\RecordEarning::class, 'handle']
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\BookingCreated::class,
            [\App\Listeners\CreateBookingNotification::class, 'handle']
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\BookingCreated::class,
            [\App\Listeners\AddBookingActivity::class, 'handle']
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\BookingCreated::class,
            [\App\Listeners\BroadcastNotification::class, 'handle']
        );
    }
}

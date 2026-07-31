<?php

namespace App\Providers;

use App\Services\Payments\PaymentProviderInterface;
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
        //
    }
}

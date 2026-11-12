<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request as HttpRequest;
use App\Services\BookingOrchestrator;

class TestWalkin extends Command
{
    protected $signature = 'test:walkin';
    protected $description = 'Test walk-in booking creation';

    public function handle()
    {
        $user = \App\Models\User::first();
        if (!$user) {
            $this->error('No user found in DB');
            return;
        }
        $this->info("Using user: {$user->id} ({$user->email})");

        $provider = \App\Models\Provider::with('locations', 'services')->first();
        if (!$provider) {
            $this->error('No provider found in DB');
            return;
        }
        $location = $provider->locations->first();
        $service = $provider->services->first();

        $this->info("Provider: {$provider->id} ({$provider->display_name})");
        $this->info("Location: " . ($location->id ?? 'NONE'));
        $this->info("Service: " . ($service->id ?? 'NONE'));

        if (!$location || !$service) {
            $this->error('Provider has no location or service');
            return;
        }

        $payload = [
            'provider_id'     => $provider->id,
            'location_id'     => $location->id,
            'service_id'      => [$service->id],
            'customer_name'   => 'Walk-in Guest',
            'date'            => now()->format('Y-m-d'),
            'time'            => now()->format('H:i'),
            'internal_booking'=> true,
        ];

        $this->info("Payload: " . json_encode($payload, JSON_PRETTY_PRINT));

        try {
            $orchestrator = app(BookingOrchestrator::class);
            $result = $orchestrator->reserveAndBook($payload);
            $this->info("SUCCESS!");
            $this->info("Booking ID: " . $result['booking']->id);
            $this->info("Booking Status: " . $result['booking']->status);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->error("VALIDATION ERROR:");
            $this->error(json_encode($e->errors(), JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->error("EXCEPTION: " . $e->getMessage());
            $this->error("Trace:\n" . $e->getTraceAsString());
        }
    }
}

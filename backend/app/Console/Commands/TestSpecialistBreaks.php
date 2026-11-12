<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Specialist;
use App\Models\User;
use App\Models\Provider;
use App\Models\Location;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Service;
use App\Models\SpecialistAccount;
use App\Models\SpecialistBreak;
use App\Services\SessionService;
use App\Services\SpecialistBreakService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class TestSpecialistBreaks extends Command
{
    protected $signature = 'test:specialist-breaks';
    protected $description = 'Test ad-hoc specialist breaks and authorization logic';

    public function handle(SessionService $sessionService, SpecialistBreakService $breakService)
    {
        $this->info('Setting up test data..');

        // Cleanup
        SpecialistBreak::query()->delete();
        BookingService::query()->delete();
        Booking::query()->delete();
        
        $provider = Provider::first();
        if (!$provider) {
            $this->error("No provider found. Run database seeders first.");
            return 1;
        }

        $location1 = Location::where('provider_id', $provider->id)->first();
        $location2 = Location::where('provider_id', $provider->id)->where('id', '!=', $location1->id)->first();

        $assignment = \App\Models\SpecialistAssignment::where('status', 'ACTIVE')->where('provider_id', $provider->id)->first();
        if (!$assignment) {
            $this->error("No active specialist assignment found.");
            return 1;
        }
        $marko = $assignment->specialist;
        $markoAccount = SpecialistAccount::firstOrCreate(['specialist_id' => $marko->id], ['email' => 'marko_break@test.com', 'password' => bcrypt('password')]);

        $sarahUser = User::firstOrCreate(['email' => 'sarah_receptionist_break@test.com'], ['name' => 'Sarah Receptionist', 'password' => bcrypt('password')]);
        \DB::table('provider_users')->insertOrIgnore(['id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $sarahUser->id, 'provider_id' => $provider->id, 'role' => 'receptionist', 'authority_scope' => 'location_scoped', 'created_at' => now(), 'updated_at' => now()]);
        $sarahProviderUser = \App\Models\ProviderUser::where('user_id', $sarahUser->id)->first();
        \App\Models\ProviderUserLocation::firstOrCreate(['provider_user_id' => $sarahProviderUser->id, 'location_id' => $location1->id]);
        
        $bobUser = User::firstOrCreate(['email' => 'bob_unauth_break@test.com'], ['name' => 'Bob', 'password' => bcrypt('password')]);
        \DB::table('provider_users')->insertOrIgnore(['id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $bobUser->id, 'provider_id' => $provider->id, 'role' => 'receptionist', 'authority_scope' => 'location_scoped', 'created_at' => now(), 'updated_at' => now()]);
        $bobProviderUser = \App\Models\ProviderUser::where('user_id', $bobUser->id)->first();
        \App\Models\ProviderUserLocation::firstOrCreate(['provider_user_id' => $bobProviderUser->id, 'location_id' => $location2->id ?? \Illuminate\Support\Str::uuid()]);

        // Service
        $service = Service::firstOrCreate(['provider_id' => $provider->id, 'name' => 'Haircut', 'duration' => 30, 'price' => 50]);

        $this->info("\n--- Running Scenarios ---");

        // Scenario C: Available specialist can start a break
        $this->runScenario('C: Available specialist can start a break', function() use ($breakService, $marko, $markoAccount, $provider, $location1) {
            $break = $breakService->startBreak($marko, $markoAccount, 10, $provider->id, $location1->id);
            if (!$break || $break->actual_end_at !== null) {
                throw new \Exception("Break was not started correctly.");
            }
        });

        // Scenario E: Specialist cannot start two simultaneous breaks
        $this->runScenario('E: Specialist cannot start two simultaneous breaks', function() use ($breakService, $marko, $markoAccount, $provider, $location1) {
            try {
                $breakService->startBreak($marko, $markoAccount, 10, $provider->id, $location1->id);
                throw new \Exception("Should have failed to start second break.");
            } catch (ValidationException $e) {
                // Expected
            }
        });

        // Scenario F: Break blocks SessionService::startSession
        $this->runScenario('F: Break blocks SessionService::startSession', function() use ($sessionService, $provider, $location1, $marko, $service, $markoAccount) {
            $customer = \App\Models\Customer::createGuest($provider->id, 'Customer Dummy');
            $booking = Booking::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'provider_id' => $provider->id, 'location_id' => $location1->id, 'specialist_id' => $marko->id, 'service_id' => $service->id, 'customer_id' => $customer->id, 'date' => now()->format('Y-m-d'), 'time' => now()->format('H:i'), 'status' => 'confirmed']);
            $bookingService = BookingService::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'booking_id' => $booking->id, 'service_id' => $service->id, 'start_time' => now(), 'end_time' => now()->addMinutes(30), 'session_status' => 'scheduled']);
            
            try {
                $sessionService->startSession($bookingService, clone $markoAccount);
                throw new \Exception("Should have failed to start session due to active break.");
            } catch (ValidationException $e) {
                // Expected
            }
            $bookingService->delete();
            $booking->delete();
        });

        // Scenario G: Ending break makes specialist available again
        $this->runScenario('G: Ending break makes specialist available again', function() use ($breakService, $marko, $markoAccount, $provider, $location1) {
            $breakService->endBreak($marko, $markoAccount, $provider->id, $location1->id);
            $activeBreaks = SpecialistBreak::where('specialist_id', $marko->id)->whereNull('actual_end_at')->count();
            if ($activeBreaks > 0) {
                throw new \Exception("Break was not ended properly.");
            }
        });

        // Scenario I: Authorized floor operator can start/end a break
        $this->runScenario('I: Authorized floor operator can start/end a break', function() use ($breakService, $marko, $sarahUser, $provider, $location1) {
            $breakService->startBreak($marko, $sarahUser, 15, $provider->id, $location1->id);
            $breakService->endBreak($marko, $sarahUser, $provider->id, $location1->id);
        });

        // Scenario J & K: Unauthorized provider user cannot
        $this->runScenario('J/K: Unauthorized / wrong location user cannot start break', function() use ($breakService, $marko, $bobUser, $provider, $location1) {
            try {
                $breakService->startBreak($marko, $bobUser, 15, $provider->id, $location1->id);
                throw new \Exception("Should have failed authorization for wrong location.");
            } catch (ValidationException $e) {
                // Expected
            }
        });

        // Scenario D: Specialist cannot start break while serving
        $this->runScenario('D: Specialist cannot start break while serving', function() use ($sessionService, $breakService, $provider, $location1, $marko, $service, $markoAccount) {
            $customer = \App\Models\Customer::createGuest($provider->id, 'Customer Dummy 2');
            $booking = Booking::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'provider_id' => $provider->id, 'location_id' => $location1->id, 'specialist_id' => $marko->id, 'service_id' => $service->id, 'customer_id' => $customer->id, 'date' => now()->format('Y-m-d'), 'time' => now()->format('H:i'), 'status' => 'confirmed']);
            $bookingService = BookingService::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'booking_id' => $booking->id, 'service_id' => $service->id, 'start_time' => now(), 'end_time' => now()->addMinutes(30), 'session_status' => 'scheduled']);
            
            $sessionService->startSession($bookingService, clone $markoAccount);

            try {
                $breakService->startBreak($marko, clone $markoAccount, 10, $provider->id, $location1->id);
                throw new \Exception("Should have failed to start break due to active session.");
            } catch (ValidationException $e) {
                // Expected
            }

            $sessionService->completeSession($bookingService, clone $markoAccount);
            $bookingService->delete();
            $booking->delete();
        });

        // Scenario H: Expired break allows SessionService::startSession
        $this->runScenario('H: Expired break allows SessionService::startSession', function() use ($sessionService, $breakService, $provider, $location1, $marko, $service, $markoAccount) {
            $customer = \App\Models\Customer::createGuest($provider->id, 'Customer Dummy 3');
            $booking = Booking::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'provider_id' => $provider->id, 'location_id' => $location1->id, 'specialist_id' => $marko->id, 'service_id' => $service->id, 'customer_id' => $customer->id, 'date' => now()->format('Y-m-d'), 'time' => now()->format('H:i'), 'status' => 'confirmed']);
            $bookingService = BookingService::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'booking_id' => $booking->id, 'service_id' => $service->id, 'start_time' => now(), 'end_time' => now()->addMinutes(30), 'session_status' => 'scheduled']);
            
            // Create an expired break manually
            SpecialistBreak::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'specialist_id' => $marko->id,
                'started_by_actor_type' => get_class($markoAccount),
                'started_by_actor_id' => $markoAccount->id,
                'started_at' => now()->subMinutes(20),
                'expected_end_at' => now()->subMinutes(10), // Expired 10 mins ago
            ]);

            // This should succeed, not throw exception
            $sessionService->startSession($bookingService, clone $markoAccount);
            $sessionService->completeSession($bookingService, clone $markoAccount);
            $bookingService->delete();
            $booking->delete();
        });

        $this->info("\nTests completed.");
        return 0;
    }

    private function runScenario($name, $callback)
    {
        try {
            $callback();
            $this->info("✅ Scenario {$name}");
        } catch (\Exception $e) {
            $this->error("❌ Scenario {$name} failed: " . $e->getMessage());
        }
    }
}

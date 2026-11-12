<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Provider;
use App\Models\Location;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Service;
use App\Models\Booking;
use App\Models\BookingService as BookingServiceModel;
use App\Models\ProviderUser;
use App\Models\Customer;
use App\Services\SessionService;
use App\Models\SpecialistAccount;
use Illuminate\Validation\ValidationException;

class TestSessionAuth extends Command
{
    protected $signature = 'test:session-auth';
    protected $description = 'Test session authorization rules';

    public function handle()
    {
        $this->info("Setting up test data...");

        // 1. Setup Data using an existing valid SpecialistAssignment
        $assignment = SpecialistAssignment::where('status', 'ACTIVE')->first();
        $provider = $assignment->provider;
        $location1 = $assignment->location;
        $marko = $assignment->specialist;
        
        $location2 = Location::where('provider_id', $provider->id)->where('id', '!=', $location1->id)->first() ?? $location1;

        // 2. Setup Service
        // Grab the first service ID from the assignment's services array, or fallback
        $serviceId = is_array($assignment->services) && count($assignment->services) > 0 ? $assignment->services[0] : Service::where('provider_id', $provider->id)->first()->id;
        $service = Service::find($serviceId);

        // 3. Setup Specialist (Marko) Account
        $markoAccount = SpecialistAccount::firstOrCreate(
            ['specialist_id' => $marko->id],
            ['email' => 'marko_' . uniqid() . '@test.com', 'password' => bcrypt('password'), 'phone' => '0000000001']
        );

        // 4. Setup Users
        // Just grab the first few ProviderUsers for this provider to act as our roles
        $providerUsers = ProviderUser::where('provider_id', $provider->id)->get();
        if ($providerUsers->isEmpty()) {
            $this->error("No provider users found in database for provider {$provider->id}");
            return;
        }

        $aliceProviderUser = $providerUsers->where('role', 'owner')->first() ?? $providerUsers->first();
        $aliceUser = User::find($aliceProviderUser->user_id);

        $sarahProviderUser = $providerUsers->where('role', 'receptionist')->first() ?? $providerUsers->last();
        $sarahUser = User::find($sarahProviderUser->user_id);
        
        $sarahProviderUser->update(['role' => 'receptionist', 'authority_scope' => 'location_scoped']);
        \App\Models\ProviderUserLocation::firstOrCreate(['provider_user_id' => $sarahProviderUser->id, 'location_id' => $location1->id]);

        $bobUser = User::firstOrCreate(['email' => 'bob_unauth_new@test.com'], ['name' => 'Bob', 'password' => bcrypt('password')]);
        // Insert directly to bypass fillable
        \DB::table('provider_users')->insertOrIgnore([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $bobUser->id,
            'provider_id' => $provider->id,
            'role' => 'receptionist',
            'authority_scope' => 'location_scoped',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bobProviderUser = ProviderUser::where('user_id', $bobUser->id)->first();
        if ($location2 && $location1->id !== $location2->id) {
            \App\Models\ProviderUserLocation::firstOrCreate(['provider_user_id' => $bobProviderUser->id, 'location_id' => $location2->id]);
        } else {
            $dummyLocation = Location::firstOrCreate(['provider_id' => $provider->id, 'name' => 'Dummy Location', 'slug' => 'dummy-'.uniqid()]);
            \App\Models\ProviderUserLocation::firstOrCreate(['provider_user_id' => $bobProviderUser->id, 'location_id' => $dummyLocation->id]);
        }

        // Provider Owner (Alice - All locations)
        // Set to provider_wide
        if ($aliceProviderUser->authority_scope !== 'provider_wide') {
            $aliceProviderUser->update(['role' => 'owner', 'authority_scope' => 'provider_wide']);
        }

        // 5. Setup Customers & Bookings
        $customerSarah = Customer::createGuest($provider->id, 'Customer Sarah');
        $bookingSarah = Booking::create([
            'provider_id' => $provider->id,
            'location_id' => $location1->id,
            'specialist_id' => $marko->id,
            'service_id' => $service->id,
            'customer_id' => $customerSarah->id,
            'date' => now()->toDateString(),
            'time' => '10:00',
            'status' => 'arrived'
        ]);
        $bookingServiceSarah = BookingServiceModel::create([
            'booking_id' => $bookingSarah->id,
            'service_id' => $service->id,
            'start_time' => now(),
            'end_time' => now()->addMinutes(30),
            'session_status' => 'scheduled'
        ]);

        $customerJohn = Customer::createGuest($provider->id, 'Customer John');
        $bookingJohn = Booking::create([
            'provider_id' => $provider->id,
            'location_id' => $location1->id,
            'specialist_id' => $marko->id,
            'service_id' => $service->id,
            'customer_id' => $customerJohn->id,
            'date' => now()->toDateString(),
            'time' => '10:30',
            'status' => 'waiting'
        ]);
        $bookingServiceJohn = BookingServiceModel::create([
            'booking_id' => $bookingJohn->id,
            'service_id' => $service->id,
            'start_time' => now()->addMinutes(30),
            'end_time' => now()->addMinutes(60),
            'session_status' => 'scheduled'
        ]);

        $sessionService = app(SessionService::class);

        $this->info("\n--- Running Scenarios ---");

        // Scenario A: Marko starts his own assigned customer (Sarah)
        try {
            $sessionService->startSession($bookingServiceSarah, $markoAccount);
            $this->info("✅ Scenario A: Marko started his own session.");
        } catch (\Exception $e) {
            $this->error("❌ Scenario A failed: " . $e->getMessage());
        }

        // Scenario D: Marko is already serving Sarah. Receptionist attempts to start John's Marko booking.
        try {
            $sessionService->startSession($bookingServiceJohn, $sarahUser);
            $this->error("❌ Scenario D failed: Should have blocked John's session.");
        } catch (ValidationException $e) {
            $this->info("✅ Scenario D: Blocked John's session because Marko is serving Sarah.");
        }

        // Scenario E & F: Break states are not yet implemented.
        $this->warn("⚠️ Scenario E & F: Break state not yet implemented on the backend. This is the remaining gap.");

        // Scenario G: Complete service
        try {
            $sessionService->completeSession($bookingServiceSarah, $markoAccount);
            $this->info("✅ Scenario G: Marko completed Sarah's session.");
            $bookingSarah->refresh();
            if ($bookingSarah->status === 'completed') {
                $this->info("✅ Booking status successfully transitioned to 'completed'.");
            } else {
                $this->error("❌ Booking status did not transition to 'completed'.");
            }
        } catch (\Exception $e) {
            $this->error("❌ Scenario G failed: " . $e->getMessage());
        }

        // Scenario H: Attempt duplicate completion
        try {
            $sessionService->completeSession($bookingServiceSarah, $sarahUser);
            $this->error("❌ Scenario H failed: Should have blocked duplicate completion.");
        } catch (ValidationException $e) {
            $this->info("✅ Scenario H: Blocked duplicate completion properly.");
        }

        // Scenario B: Receptionist starts Marko's assigned customer (John)
        try {
            $sessionService->startSession($bookingServiceJohn, $sarahUser);
            $this->info("✅ Scenario B: Receptionist Sarah successfully started Marko's customer (John).");
            $bookingServiceJohn->refresh();
            if ($bookingServiceJohn->started_by_actor_type === get_class($sarahUser) && $bookingServiceJohn->started_by_actor_id == $sarahUser->id) {
                $this->info("✅ Actor was correctly recorded as Sarah.");
            } else {
                $this->error("❌ Actor was not recorded correctly.");
            }
        } catch (\Exception $e) {
            $this->error("❌ Scenario B failed: " . $e->getMessage());
        }

        // Scenario C & J: Unauthorized staff member (Bob at Loc 2) attempts to complete John's session (at Loc 1)
        try {
            $sessionService->completeSession($bookingServiceJohn, $bobUser);
            $this->error("❌ Scenario C & J failed: Bob should have been blocked.");
        } catch (ValidationException $e) {
            $this->info("✅ Scenario C & J: Unauthorized staff Bob was blocked.");
        }

        // Scenario I: Provider-wide user (Alice) at permitted location
        try {
            $sessionService->completeSession($bookingServiceJohn, $aliceUser);
            $this->info("✅ Scenario I: Provider-wide owner Alice successfully completed John's session.");
        } catch (\Exception $e) {
            $this->error("❌ Scenario I failed: " . $e->getMessage());
        }

        $this->info("\nTests completed.");
    }
}

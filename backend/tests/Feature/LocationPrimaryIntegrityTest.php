<?php

namespace Tests\Feature;

use App\Domain\Specialist\Services\IndependentWorkspaceProvisioner;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\LocationSchedule;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\User;
use App\Services\LocationService;
use App\Services\OnboardingService;
use Database\Seeders\BusinessCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class LocationPrimaryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function locationAttributes(string $name): array
    {
        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'address' => '1 Test Street',
            'city' => 'Kampala',
            'country' => 'Uganda',
            'is_active' => true,
            'timezone' => 'Africa/Kampala',
        ];
    }

    public function test_first_location_becomes_primary_and_subsequent_locations_do_not()
    {
        $provider = Provider::factory()->create();
        $service = app(LocationService::class);

        $first = $service->createLocation($provider, $this->locationAttributes('First Location'));
        $second = $service->createLocation($provider, $this->locationAttributes('Second Location'));

        $this->assertTrue($first->fresh()->is_primary);
        $this->assertFalse($second->fresh()->is_primary);
        $this->assertSame(1, $provider->locations()->where('is_primary', true)->count());
        $this->assertSame(2, $provider->locations()->count());
        $this->assertSame($first->id, $provider->fresh()->primaryLocation->id);
    }

    public function test_caller_supplied_primary_flag_cannot_promote_a_subsequent_location()
    {
        $provider = Provider::factory()->create();
        $service = app(LocationService::class);

        $first = $service->createLocation($provider, $this->locationAttributes('First Location'));
        $second = $service->createLocation($provider, array_merge(
            $this->locationAttributes('Second Location'),
            ['is_primary' => true]
        ));

        $this->assertTrue($first->fresh()->is_primary);
        $this->assertFalse($second->fresh()->is_primary);
        $this->assertSame(1, $provider->locations()->where('is_primary', true)->count());
    }

    public function test_multiple_locations_can_belong_to_one_provider()
    {
        $provider = Provider::factory()->create();
        $service = app(LocationService::class);

        $locations = collect([
            $service->createLocation($provider, $this->locationAttributes('One')),
            $service->createLocation($provider, $this->locationAttributes('Two')),
            $service->createLocation($provider, $this->locationAttributes('Three')),
        ]);

        $this->assertCount(3, $provider->locations()->get());
        $this->assertSame(1, $provider->locations()->where('is_primary', true)->count());
        $this->assertSame($locations->first()->id, $provider->fresh()->primaryLocation->id);
    }

    public function test_different_providers_can_each_have_a_primary_location()
    {
        $firstProvider = Provider::factory()->create();
        $secondProvider = Provider::factory()->create();
        $service = app(LocationService::class);

        $firstLocation = $service->createLocation($firstProvider, $this->locationAttributes('Provider One'));
        $secondLocation = $service->createLocation($secondProvider, $this->locationAttributes('Provider Two'));

        $this->assertTrue($firstLocation->fresh()->is_primary);
        $this->assertTrue($secondLocation->fresh()->is_primary);
        $this->assertSame($firstLocation->id, $firstProvider->fresh()->primaryLocation->id);
        $this->assertSame($secondLocation->id, $secondProvider->fresh()->primaryLocation->id);
    }

    public function test_zero_location_providers_remain_valid()
    {
        $provider = Provider::factory()->create();

        $this->assertSame(0, $provider->locations()->count());
        $this->assertNull($provider->fresh()->primaryLocation);
    }

    public function test_database_rejects_a_second_primary_location_for_the_same_provider()
    {
        $provider = Provider::factory()->create();
        $service = app(LocationService::class);
        $primary = $service->createLocation($provider, $this->locationAttributes('Primary'));

        try {
            DB::transaction(function () use ($provider) {
                DB::table('locations')->insert([
                    'id' => (string) Str::uuid(),
                    'provider_id' => $provider->id,
                    'name' => 'Direct Duplicate Primary',
                    'slug' => 'direct-duplicate-'.Str::lower(Str::random(8)),
                    'address' => '2 Test Street',
                    'is_primary' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $this->fail('Expected the database to reject a second primary Location.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0] ?? null);
        }

        $this->assertSame($primary->id, $provider->fresh()->primaryLocation->id);
        $this->assertSame(1, $provider->locations()->where('is_primary', true)->count());
    }

    public function test_primary_switching_demotes_the_previous_primary_and_promotes_the_target()
    {
        $provider = Provider::factory()->create();
        $service = app(LocationService::class);

        $first = $service->createLocation($provider, $this->locationAttributes('First'));
        $second = $service->createLocation($provider, $this->locationAttributes('Second'));

        $switched = $service->switchPrimary($provider, $second);

        $this->assertTrue($switched->is_primary);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $provider->locations()->where('is_primary', true)->count());
        $this->assertSame($second->id, $provider->fresh()->primaryLocation->id);
    }

    public function test_switching_to_the_current_primary_is_idempotent()
    {
        $provider = Provider::factory()->create();
        $service = app(LocationService::class);

        $first = $service->createLocation($provider, $this->locationAttributes('First'));
        $second = $service->createLocation($provider, $this->locationAttributes('Second'));

        $service->switchPrimary($provider, $second);
        $service->switchPrimary($provider, $second);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $provider->locations()->where('is_primary', true)->count());
    }

    public function test_a_location_from_another_provider_cannot_become_primary()
    {
        $firstProvider = Provider::factory()->create();
        $secondProvider = Provider::factory()->create();
        $service = app(LocationService::class);

        $originalPrimary = $service->createLocation($firstProvider, $this->locationAttributes('Original'));
        $foreignLocation = $service->createLocation($secondProvider, $this->locationAttributes('Foreign'));

        try {
            $service->switchPrimary($firstProvider, $foreignLocation);
            $this->fail('Expected switching to a foreign Location to fail.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('does not belong', $exception->getMessage());
        }

        $this->assertTrue($originalPrimary->fresh()->is_primary);
        $this->assertTrue($foreignLocation->fresh()->is_primary);
        $this->assertSame(1, $firstProvider->locations()->where('is_primary', true)->count());
        $this->assertSame(1, $secondProvider->locations()->where('is_primary', true)->count());
    }

    public function test_failed_switch_transaction_leaves_the_original_primary_intact()
    {
        $provider = Provider::factory()->create();
        $service = app(LocationService::class);

        $first = $service->createLocation($provider, $this->locationAttributes('First'));
        $second = $service->createLocation($provider, $this->locationAttributes('Second'));

        try {
            DB::transaction(function () use ($service, $provider, $second) {
                $service->switchPrimary($provider, $second);
                throw new RuntimeException('Force rollback after switching.');
            });
            $this->fail('Expected the outer transaction to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Force rollback after switching.', $exception->getMessage());
        }

        $this->assertTrue($first->fresh()->is_primary);
        $this->assertFalse($second->fresh()->is_primary);
        $this->assertSame(1, $provider->locations()->where('is_primary', true)->count());
    }

    public function test_switching_primary_does_not_rewrite_historical_booking_locations()
    {
        $provider = Provider::factory()->create();
        $service = app(LocationService::class);

        $first = $service->createLocation($provider, $this->locationAttributes('First'));
        $second = $service->createLocation($provider, $this->locationAttributes('Second'));

        $customer = Customer::factory()->create();
        $bookedService = Service::factory()->create(['provider_id' => $provider->id]);

        $booking = Booking::create([
            'provider_id' => $provider->id,
            'location_id' => $first->id,
            'customer_id' => $customer->id,
            'service_id' => $bookedService->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00',
            'status' => 'confirmed',
        ]);

        $service->switchPrimary($provider, $second);

        $this->assertSame($first->id, $booking->fresh()->location_id);
        $this->assertSame($first->id, $booking->fresh()->location->id);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_onboarding_creates_the_first_location_as_primary()
    {
        $this->seed(BusinessCategorySeeder::class);

        $user = User::factory()->create();
        $provider = app(OnboardingService::class)->complete($user, [
            'business' => [
                'name' => 'Phase Three A Salon',
                'category' => 'spa',
                'description' => 'Onboarding primary-location test',
                'address' => '10 Onboarding Road',
                'city' => 'Kampala',
                'country' => 'Uganda',
                'phone' => '+256700000000',
                'email' => 'phase3a-onboarding@example.com',
                'timezone' => 'Africa/Kampala',
            ],
            'wallet' => [
                'openingHours' => [
                    'monday' => ['open' => '09:00', 'close' => '18:00'],
                    'tuesday' => ['open' => '09:00', 'close' => '18:00'],
                    'wednesday' => ['open' => '09:00', 'close' => '18:00'],
                    'thursday' => ['open' => '09:00', 'close' => '18:00'],
                    'friday' => ['open' => '09:00', 'close' => '18:00'],
                    'saturday' => ['open' => '09:00', 'close' => '18:00'],
                    'sunday' => ['open' => '09:00', 'close' => '18:00'],
                ],
            ],
        ]);

        $locations = $provider->locations()->get();

        $this->assertCount(1, $locations);
        $this->assertTrue($locations->first()->is_primary);
        $this->assertSame($locations->first()->id, $provider->fresh()->primaryLocation->id);
        $this->assertSame('09:00:00', LocationSchedule::where('location_id', $locations->first()->id)
            ->where('day_of_week', 'Monday')
            ->value('open_time'));

        $second = app(LocationService::class)->createLocation($provider, $this->locationAttributes('Second'));
        $this->assertFalse($second->fresh()->is_primary);
        $this->assertSame(1, $provider->locations()->where('is_primary', true)->count());
    }

    public function test_onboarding_without_address_or_coordinates_creates_no_location()
    {
        $this->seed(BusinessCategorySeeder::class);

        $user = User::factory()->create();
        $provider = app(OnboardingService::class)->complete($user, [
            'business' => [
                'name' => 'Zero Location Onboarding Provider',
                'category' => 'spa',
            ],
        ]);

        $this->assertSame(0, $provider->locations()->count());
        $this->assertNull($provider->fresh()->primaryLocation);
    }

    public function test_independent_workspace_provisioning_creates_the_workspace_as_primary()
    {
        $specialist = Specialist::factory()->create([
            'email' => 'workspace-'.Str::lower(Str::random(10)).'@example.com',
        ]);

        $provisioner = app(IndependentWorkspaceProvisioner::class);
        $provisioner->ensureIndependentWorkspace($specialist);

        $provider = Provider::query()
            ->where('email', $specialist->email)
            ->whereHas('providerType', fn ($q) => $q->where('key', Provider::TYPE_SPECIALIST))
            ->firstOrFail();

        $locations = $provider->locations()->get();
        $this->assertCount(1, $locations);
        $this->assertTrue($locations->first()->is_primary);
        $this->assertSame($locations->first()->id, $provider->fresh()->primaryLocation->id);

        $assignment = SpecialistAssignment::query()
            ->where('specialist_id', $specialist->id)
            ->where('provider_id', $provider->id)
            ->firstOrFail();

        $this->assertTrue($assignment->is_primary);
        $this->assertSame($locations->first()->id, $assignment->location_id);

        // Idempotent provisioning must not create another primary workspace.
        $provisioner->ensureIndependentWorkspace($specialist->fresh());

        $this->assertSame(1, $provider->locations()->count());
        $this->assertSame(1, $provider->locations()->where('is_primary', true)->count());
        $this->assertSame($locations->first()->id, $provider->fresh()->primaryLocation->id);
    }
}

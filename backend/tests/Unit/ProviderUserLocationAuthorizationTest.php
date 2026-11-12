<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Provider;
use App\Models\Location;
use App\Models\ProviderUser;
use App\Models\ProviderUserLocation;
use App\Services\ProviderUserAuthorityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProviderUserLocationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private ProviderUserAuthorityService $authorityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorityService = app(ProviderUserAuthorityService::class);
    }

    public function test_provider_wide_user_can_access_provider_wide_scope()
    {
        $provider = Provider::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        
        $providerUser = ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'owner',
            'authority_scope' => 'provider_wide',
        ]);

        $this->actingAs($user);
        
        $result = $this->authorityService->validateLocationScope($provider->id, null);
        
        $this->assertTrue($result);
    }

    public function test_provider_wide_user_can_access_any_provider_location()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create(['status' => 'active']);
        
        $providerUser = ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'owner',
            'authority_scope' => 'provider_wide',
        ]);

        $this->actingAs($user);
        
        $result = $this->authorityService->validateLocationScope($provider->id, $location->id);
        
        $this->assertTrue($result);
    }

    public function test_location_scoped_user_cannot_access_provider_wide_scope()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create(['status' => 'active']);
        
        $providerUser = ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'staff',
            'authority_scope' => 'location_scoped',
        ]);

        ProviderUserLocation::create([
            'provider_user_id' => $providerUser->id,
            'location_id' => $location->id,
        ]);

        $this->actingAs($user);
        
        $result = $this->authorityService->validateLocationScope($provider->id, null);
        
        $this->assertFalse($result);
    }

    public function test_location_scoped_user_can_access_permitted_location()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create(['status' => 'active']);
        
        $providerUser = ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'staff',
            'authority_scope' => 'location_scoped',
        ]);

        ProviderUserLocation::create([
            'provider_user_id' => $providerUser->id,
            'location_id' => $location->id,
        ]);

        $this->actingAs($user);
        
        $result = $this->authorityService->validateLocationScope($provider->id, $location->id);
        
        $this->assertTrue($result);
    }

    public function test_location_scoped_user_cannot_access_unpermitted_location()
    {
        $provider = Provider::factory()->create();
        $permittedLocation = Location::factory()->create(['provider_id' => $provider->id]);
        $otherLocation = Location::factory()->create(['provider_id' => $provider->id]);
        $user = User::factory()->create(['status' => 'active']);
        
        $providerUser = ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'staff',
            'authority_scope' => 'location_scoped',
        ]);

        ProviderUserLocation::create([
            'provider_user_id' => $providerUser->id,
            'location_id' => $permittedLocation->id,
        ]);

        $this->actingAs($user);
        
        $result = $this->authorityService->validateLocationScope($provider->id, $otherLocation->id);
        
        $this->assertFalse($result);
    }

    public function test_location_scoped_user_cannot_access_location_from_different_provider()
    {
        $providerA = Provider::factory()->create();
        $providerB = Provider::factory()->create();
        $locationA = Location::factory()->create(['provider_id' => $providerA->id]);
        $locationB = Location::factory()->create(['provider_id' => $providerB->id]);
        $user = User::factory()->create(['status' => 'active']);
        
        $providerUser = ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $providerA->id,
            'role' => 'staff',
            'authority_scope' => 'location_scoped',
        ]);

        ProviderUserLocation::create([
            'provider_user_id' => $providerUser->id,
            'location_id' => $locationA->id,
        ]);

        $this->actingAs($user);
        
        // Try to access location from different provider
        $result = $this->authorityService->validateLocationScope($providerA->id, $locationB->id);
        
        $this->assertFalse($result);
    }

    public function test_unauthenticated_user_cannot_access_any_scope()
    {
        $provider = Provider::factory()->create();
        
        $result = $this->authorityService->validateLocationScope($provider->id, null);
        
        $this->assertFalse($result);
    }

    public function test_user_without_provider_user_record_cannot_access_scope()
    {
        $provider = Provider::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        
        $this->actingAs($user);
        
        $result = $this->authorityService->validateLocationScope($provider->id, null);
        
        $this->assertFalse($result);
    }
}

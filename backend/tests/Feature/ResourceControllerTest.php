<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResourceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_resource_for_their_own_provider_location()
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);

        // Attach user to provider
        $user->providers()->attach($provider->id, ['id' => Str::uuid(), 'role' => 'owner']);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/resources', [
                'location_id' => $location->id,
                'name' => 'Test Resource',
                'type' => 'room',
                'capacity' => 1,
            ])
            ->assertStatus(201)
            ->assertJson([
                'location_id' => $location->id,
                'name' => 'Test Resource',
                'type' => 'room',
            ]);
    }

    public function test_user_cannot_create_resource_for_another_provider_location()
    {
        $user = User::factory()->create();
        $providerA = Provider::factory()->create();
        $providerB = Provider::factory()->create();
        $locationB = Location::factory()->create(['provider_id' => $providerB->id]);

        // Attach user only to providerA
        $user->providers()->attach($providerA->id, ['id' => Str::uuid(), 'role' => 'owner']);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/resources', [
                'location_id' => $locationB->id,
                'name' => 'Test Resource',
                'type' => 'room',
                'capacity' => 1,
            ])
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized: you do not own this location',
            ]);
    }

    public function test_user_with_multiple_providers_can_create_resource_for_either_provider_location()
    {
        $user = User::factory()->create();
        $providerA = Provider::factory()->create();
        $providerB = Provider::factory()->create();
        $locationA = Location::factory()->create(['provider_id' => $providerA->id]);
        $locationB = Location::factory()->create(['provider_id' => $providerB->id]);

        // Attach user to both providers
        $user->providers()->attach($providerA->id, ['id' => Str::uuid(), 'role' => 'owner']);
        $user->providers()->attach($providerB->id, ['id' => Str::uuid(), 'role' => 'owner']);

        $token = $user->createToken('test-token')->plainTextToken;

        // Create resource for providerA's location
        $this->withToken($token)
            ->postJson('/api/v1/resources', [
                'location_id' => $locationA->id,
                'name' => 'Resource A',
                'type' => 'room',
                'capacity' => 1,
            ])
            ->assertStatus(201);

        // Create resource for providerB's location
        $this->withToken($token)
            ->postJson('/api/v1/resources', [
                'location_id' => $locationB->id,
                'name' => 'Resource B',
                'type' => 'room',
                'capacity' => 1,
            ])
            ->assertStatus(201);
    }

    public function test_user_with_no_providers_cannot_create_resource()
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);

        // User has no providers attached

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/resources', [
                'location_id' => $location->id,
                'name' => 'Test Resource',
                'type' => 'room',
                'capacity' => 1,
            ]);

        // Should be unauthorized (403) since user has no providers
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Unauthorized: you do not own this location',
        ]);
    }
}

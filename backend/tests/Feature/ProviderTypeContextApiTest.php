<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\ProviderUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderTypeContextApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Link a user to a provider as an owner and return the user.
     *
     * Uses ProviderUserFactory because ProviderUser does not auto-generate
     * its UUID primary key outside of factory (unguarded) creation.
     */
    private function authenticateAsProviderOwner(Provider $provider): User
    {
        $user = User::factory()->create(['status' => 'active']);

        ProviderUser::factory()->owner()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
        ]);

        return $user;
    }

    public function test_dashboard_live_stats_includes_provider_type_context(): void
    {
        $salonType = ProviderType::byKey('salon')->first();
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'provider_type_id' => $salonType->id,
        ]);

        $user = $this->authenticateAsProviderOwner($provider);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/live-stats');

        $response->assertStatus(200);
        $data = $response->json();

        // Existing fields are preserved
        $this->assertArrayHasKey('date', $data);
        $this->assertArrayHasKey('auto_approvals', $data);

        // Provider type context is exposed
        $this->assertArrayHasKey('provider_type', $data);
        $this->assertSame($provider->id, $data['provider_type']['provider_id']);
        $this->assertSame('salon', $data['provider_type']['provider_type']);
        $this->assertNotNull($data['provider_type']['provider_type_definition']);
        $this->assertSame('Salon', $data['provider_type']['provider_type_definition']['display_name']);
        $this->assertFalse($data['provider_type']['is_independent']);
        $this->assertNull($data['provider_type']['error']);
    }

    public function test_dashboard_live_stats_shows_independent_specialist_context(): void
    {
        $specialistType = ProviderType::byKey('independent_specialist')->first();
        $provider = Provider::factory()->create([
            'type' => 'independent_specialist',
            'provider_type_id' => $specialistType->id,
        ]);

        $user = $this->authenticateAsProviderOwner($provider);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/live-stats');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertSame('independent_specialist', $data['provider_type']['provider_type']);
        $this->assertTrue($data['provider_type']['is_independent']);
        $this->assertTrue($data['provider_type']['is_owner_managed']);
        $this->assertSame('Independent Specialist', $data['provider_type']['provider_type_definition']['display_name']);
    }

    public function test_dashboard_live_stats_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/dashboard/live-stats');
        // Sanctum-protected route returns 401 for unauthenticated requests
        $response->assertStatus(401);
    }

    public function test_dashboard_live_stats_requires_provider_association(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        // User has no provider association

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/live-stats');

        $response->assertStatus(403);
    }

    public function test_dashboard_response_preserves_backward_compatibility(): void
    {
        $salonType = ProviderType::byKey('salon')->first();
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'provider_type_id' => $salonType->id,
        ]);

        $user = $this->authenticateAsProviderOwner($provider);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/live-stats');

        $response->assertStatus(200);
        $data = $response->json();

        // Verify existing fields are still present and unchanged
        $this->assertArrayHasKey('date', $data);
        $this->assertArrayHasKey('new_bookings_today', $data);
        $this->assertArrayHasKey('payments_today', $data);
        $this->assertArrayHasKey('awaiting_approval', $data);
        $this->assertArrayHasKey('customers_waiting', $data);
        $this->assertArrayHasKey('revenue_today', $data);
        $this->assertArrayHasKey('auto_approvals', $data);

        // Provider type context is additive
        $this->assertArrayHasKey('provider_type', $data);
    }

    public function test_provider_type_context_inactive_type_still_resolves_in_api(): void
    {
        // Existing provider first, then deactivate its type. Inactive types
        // remain resolvable but are not available for new Providers.
        $spaType = ProviderType::byKey('spa')->first();
        $provider = Provider::factory()->create([
            'type' => 'spa',
            'provider_type_id' => $spaType->id,
        ]);

        $spaType->update(['is_active' => false]);

        $user = $this->authenticateAsProviderOwner($provider);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/live-stats');

        $response->assertStatus(200);
        $data = $response->json();

        // Existing provider with inactive type still resolves
        $this->assertSame('spa', $data['provider_type']['provider_type']);
        $this->assertFalse($data['provider_type']['provider_type_definition']['is_active']);
        $this->assertNull($data['provider_type']['error']);
    }

    public function test_cache_invalidation_refreshes_context(): void
    {
        $registry = app(\App\Domain\Provider\ProviderTypeRegistry::class);

        // Cache the registry
        $registry->for('salon');

        // Update a provider type name
        $salonType = ProviderType::byKey('salon')->first();
        $salonType->update(['name' => 'Salon Studio']);

        // The registry cache should be invalidated via model event
        $definition = $registry->for('salon');
        $this->assertSame('Salon Studio', $definition->displayName);
    }
}

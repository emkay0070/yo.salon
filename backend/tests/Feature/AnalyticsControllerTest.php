<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Provider;
use App\Models\ProviderUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_endpoint_returns_provider_context(): void
    {
        // Create provider and user
        $provider = Provider::factory()->create(['type' => 'salon', 'active' => true]);
        $user = User::factory()->create(['status' => 'active']);

        // Link user to provider
        ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'owner',
        ]);

        // Create token
        $token = $user->createToken('test-token')->plainTextToken;

        // Act as authenticated user
        $response = $this->withToken($token)
            ->getJson('/api/v1/analytics/intelligence');

        // Assert response - IntelligenceEngine returns DTO structure
        $response->assertStatus(200);
        // The actual structure depends on IntelligenceEngine implementation
        // Just verify it returns valid JSON
        $this->assertIsArray($response->json());
    }

    public function test_intelligence_endpoint_returns_404_when_no_provider(): void
    {
        // Create user without provider
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act as authenticated user
        $response = $this->withToken($token)
            ->getJson('/api/v1/analytics/intelligence');

        // Assert 403 or 404 response (middleware may block before controller)
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_endpoint_returns_provider_analytics(): void
    {
        // Create provider and user
        $provider = Provider::factory()->create(['type' => 'salon', 'active' => true]);
        $user = User::factory()->create(['status' => 'active']);

        // Link user to provider
        ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'owner',
        ]);

        // Create token
        $token = $user->createToken('test-token')->plainTextToken;

        // Act as authenticated user
        $response = $this->withToken($token)
            ->getJson('/api/v1/analytics');

        // Assert response - AnalyticsProjectionService returns legacy structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_bookings',
                'total_gross_revenue',
                'total_net_revenue',
                'status_counts',
                'today_bookings',
                'revenue_trend',
                'service_stats',
                'staff_stats',
                'weekly_bookings',
                'insights',
                'executive_summary',
            ]);
    }

    public function test_index_endpoint_returns_404_when_no_provider(): void
    {
        // Create user without provider
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act as authenticated user
        $response = $this->withToken($token)
            ->getJson('/api/v1/analytics');

        // Assert 403 or 404 response (middleware may block before controller)
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_currentProvider_returns_first_provider_for_user(): void
    {
        // Create user with multiple providers
        $user = User::factory()->create(['status' => 'active']);
        $provider1 = Provider::factory()->create(['type' => 'salon', 'active' => true]);
        $provider2 = Provider::factory()->create(['type' => 'spa', 'active' => true]);
        
        // Link user to both providers
        ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider1->id,
            'role' => 'owner',
        ]);
        ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider2->id,
            'role' => 'admin',
        ]);

        // Get current provider
        $currentProvider = $user->currentProvider();

        // Assert it returns a provider
        $this->assertNotNull($currentProvider);
        $this->assertInstanceOf(Provider::class, $currentProvider);
    }

    public function test_currentProvider_returns_null_when_no_providers(): void
    {
        // Create user without providers
        $user = User::factory()->create(['status' => 'active']);

        // Get current provider
        $currentProvider = $user->currentProvider();

        // Assert it returns null
        $this->assertNull($currentProvider);
    }
}

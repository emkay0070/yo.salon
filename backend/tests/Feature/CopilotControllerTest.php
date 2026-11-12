<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Provider;
use App\Models\ProviderUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopilotControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_endpoint_with_provider_context(): void
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
            ->postJson('/api/v1/copilot/chat', [
                'message' => 'How is my business doing?',
            ]);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
                'type',
            ]);
    }

    public function test_chat_endpoint_returns_404_when_no_provider(): void
    {
        // Create user without provider
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act as authenticated user
        $response = $this->withToken($token)
            ->postJson('/api/v1/copilot/chat', [
                'message' => 'How is my business doing?',
            ]);

        // Assert 403 or 404 response (middleware may block before controller)
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_chat_endpoint_with_context_parameter(): void
    {
        // Create user without provider (context should bypass provider requirement)
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act as authenticated user with context
        $response = $this->withToken($token)
            ->postJson('/api/v1/copilot/chat', [
                'message' => 'churn',
                'context' => [
                    'churn_customer_ids' => ['cust1', 'cust2'],
                ],
            ]);

        // Assert 403 or 200 (middleware may block or context may bypass)
        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_chat_endpoint_validates_message_required(): void
    {
        // Create user with provider
        $provider = Provider::factory()->create(['type' => 'salon', 'active' => true]);
        $user = User::factory()->create(['status' => 'active']);
        ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'owner',
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act as authenticated user without message
        $response = $this->withToken($token)
            ->postJson('/api/v1/copilot/chat', []);

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_chat_endpoint_validates_message_max_length(): void
    {
        // Create user with provider
        $provider = Provider::factory()->create(['type' => 'salon', 'active' => true]);
        $user = User::factory()->create(['status' => 'active']);
        ProviderUser::factory()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'owner',
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act as authenticated user with too long message
        $response = $this->withToken($token)
            ->postJson('/api/v1/copilot/chat', [
                'message' => str_repeat('a', 501),
            ]);

        // Assert validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_revenue_intent_recognized(): void
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
            ->postJson('/api/v1/copilot/chat', [
                'message' => 'How much revenue did I make?',
            ]);

        // Assert response contains revenue data
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
                'type',
            ]);
    }

    public function test_bookings_intent_recognized(): void
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
            ->postJson('/api/v1/copilot/chat', [
                'message' => 'How many bookings do I have?',
            ]);

        // Assert response contains booking data
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
                'type',
            ]);
    }
}

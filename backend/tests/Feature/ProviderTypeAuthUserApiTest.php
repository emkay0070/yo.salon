<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveProviderContext;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\ProviderUser;
use App\Models\User;
use App\Services\ProviderTypeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2B: GET /api/v1/auth/user exposes the resolved provider type
 * context (`provider_type_context`) to the application layer.
 *
 * Resolution reuses ProviderTypeContext — server-side provider selection
 * only (middleware request attribute, then authenticated user's
 * currentProvider()). Provider type is never derived from client input.
 */
class ProviderTypeAuthUserApiTest extends TestCase
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

    private function makeProviderOfType(string $type): Provider
    {
        // Factories establish both the canonical FK and the legacy
        // compatibility value explicitly.
        return Provider::factory()->ofType($type)->create();
    }

    public function test_auth_user_exposes_resolved_provider_type_context(): void
    {
        $provider = $this->makeProviderOfType('salon');
        $user = $this->authenticateAsProviderOwner($provider);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200);
        $context = $response->json('provider_type_context');

        $this->assertNotNull($context);
        $this->assertSame($provider->id, $context['provider_id']);
        $this->assertSame('salon', $context['provider_type']);

        $definition = $context['provider_type_definition'];
        $this->assertNotNull($definition);
        $this->assertNotNull($definition['id']);
        $this->assertSame('salon', $definition['type']);
        $this->assertSame('Salon', $definition['display_name']);
        $this->assertFalse($definition['is_independent']);
        $this->assertFalse($definition['is_owner_managed']);
        $this->assertTrue($definition['is_active']);

        $this->assertFalse($context['is_independent']);
        $this->assertFalse($context['is_owner_managed']);
        $this->assertNull($context['error']);
    }

    public function test_auth_user_supports_all_five_provider_types(): void
    {
        $expected = [
            'salon' => ['Salon', false, false],
            'independent_specialist' => ['Independent Specialist', true, true],
            'spa' => ['Spa', false, false],
            'beauty_school' => ['Beauty School', false, false],
            'mobile_team' => ['Mobile Team', false, false],
        ];

        foreach ($expected as $typeKey => [$displayName, $isIndependent, $isOwnerManaged]) {
            $provider = $this->makeProviderOfType($typeKey);
            $user = $this->authenticateAsProviderOwner($provider);

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/auth/user');

            $response->assertStatus(200);
            $context = $response->json('provider_type_context');

            $this->assertSame($provider->id, $context['provider_id'], $typeKey);
            $this->assertSame($typeKey, $context['provider_type'], $typeKey);
            $this->assertSame($typeKey, $context['provider_type_definition']['type'], $typeKey);
            $this->assertSame($displayName, $context['provider_type_definition']['display_name'], $typeKey);
            $this->assertTrue($context['provider_type_definition']['is_active'], $typeKey);
            $this->assertSame($isIndependent, $context['is_independent'], $typeKey);
            $this->assertSame($isOwnerManaged, $context['is_owner_managed'], $typeKey);
            $this->assertNull($context['error'], $typeKey);
        }
    }

    public function test_auth_user_context_resolves_middleware_selected_provider(): void
    {
        $provider = $this->makeProviderOfType('spa');
        $user = $this->authenticateAsProviderOwner($provider);
        $this->actingAs($user, 'sanctum');

        // 1. The real owner middleware injects provider context server-side.
        $request = Request::create('/api/v1/dashboard/live-stats', 'GET');
        $middleware = new ResolveProviderContext();
        $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame($provider->id, $request->attributes->get('provider_id'));

        // 2. ProviderTypeContext resolves from the middleware-selected
        //    request attribute (the same code path /auth/user uses).
        $context = app(ProviderTypeContext::class)
            ->resolveFromRequest($request)
            ->toArray();

        $this->assertSame($provider->id, $context['provider_id']);
        $this->assertSame('spa', $context['provider_type']);
        $this->assertNull($context['error']);
    }

    public function test_auth_user_context_attribute_takes_precedence_over_authenticated_fallback(): void
    {
        $ownProvider = $this->makeProviderOfType('salon');
        $user = $this->authenticateAsProviderOwner($ownProvider);

        // Another provider, selected by middleware (request attribute).
        $selectedProvider = $this->makeProviderOfType('beauty_school');

        $this->actingAs($user, 'sanctum');

        $request = Request::create('/api/v1/auth/user', 'GET');
        $request->attributes->set('provider_id', $selectedProvider->id);

        $context = app(ProviderTypeContext::class)
            ->resolveFromRequest($request)
            ->toArray();

        // Middleware-selected provider wins over the authenticated fallback.
        $this->assertSame($selectedProvider->id, $context['provider_id']);
        $this->assertSame('beauty_school', $context['provider_type']);
        $this->assertNull($context['error']);
    }

    public function test_auth_user_independent_specialist_context(): void
    {
        $provider = $this->makeProviderOfType('independent_specialist');
        $user = $this->authenticateAsProviderOwner($provider);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200);
        $context = $response->json('provider_type_context');

        $this->assertSame('independent_specialist', $context['provider_type']);
        $this->assertTrue($context['is_independent']);
        $this->assertTrue($context['is_owner_managed']);
        $this->assertSame(
            'Independent Specialist',
            $context['provider_type_definition']['display_name']
        );
        $this->assertNull($context['error']);
    }

    public function test_auth_user_missing_provider_returns_explicit_error(): void
    {
        // Active user with no provider association.
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        // /auth/user sits outside the provider.context middleware group:
        // no new 403s are introduced for provider-less users.
        $response->assertStatus(200);
        $context = $response->json('provider_type_context');

        $this->assertNull($context['provider_id']);
        $this->assertNull($context['provider_type']);
        $this->assertNull($context['provider_type_definition']);
        $this->assertSame('No provider found in context.', $context['error']);

        // Existing response contract unchanged.
        $this->assertArrayHasKey('user', $response->json());
        $this->assertArrayHasKey('provider_authorities', $response->json());
    }

    public function test_auth_user_resolves_provider_type_when_legacy_column_is_dropped(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('providers', 'type'),
            'Phase 5 verification: providers.type column must be dropped from the database.'
        );

        $provider = $this->makeProviderOfType('salon');
        $user = $this->authenticateAsProviderOwner($provider);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200);
        $context = $response->json('provider_type_context');

        // provider_type_id is authoritative; resolved purely via relationship.
        $this->assertSame('salon', $context['provider_type']);
        $this->assertNotNull($context['provider_type_definition']);
        $this->assertSame('Salon', $context['provider_type_definition']['display_name']);
        $this->assertNull($context['error']);
    }

    public function test_auth_user_inactive_provider_type_still_resolves(): void
    {
        $provider = $this->makeProviderOfType('spa');

        // Existing provider remains resolvable after its type is deactivated.
        $spaType = ProviderType::byKey('spa')->first();
        $spaType->update(['is_active' => false]);

        $user = $this->authenticateAsProviderOwner($provider);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200);
        $context = $response->json('provider_type_context');

        // Existing provider with inactive type remains resolvable.
        $this->assertSame('spa', $context['provider_type']);
        $this->assertSame('Spa', $context['provider_type_definition']['display_name']);
        $this->assertFalse($context['provider_type_definition']['is_active']);
        $this->assertNull($context['error']);
    }

    public function test_auth_user_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
    }

    public function test_auth_user_ignores_client_supplied_provider_type_spoofing(): void
    {
        $provider = $this->makeProviderOfType('salon');
        $user = $this->authenticateAsProviderOwner($provider);

        $attackerChosen = $this->makeProviderOfType('independent_specialist');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(
                '/api/v1/auth/user?provider_type=independent_specialist&provider_id=' . $attackerChosen->id,
                ['X-Provider-Id' => $attackerChosen->id]
            );

        $response->assertStatus(200);
        $context = $response->json('provider_type_context');

        // Server-side resolution is authoritative: client-supplied type key,
        // provider_id query and X-Provider-Id header are all ignored.
        $this->assertSame($provider->id, $context['provider_id']);
        $this->assertSame('salon', $context['provider_type']);
        $this->assertSame('Salon', $context['provider_type_definition']['display_name']);
        $this->assertNull($context['error']);
    }

    public function test_auth_user_response_preserves_backward_compatibility(): void
    {
        $provider = $this->makeProviderOfType('salon');
        $user = $this->authenticateAsProviderOwner($provider);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200);
        $data = $response->json();

        // Pre-existing contract fields remain present and unchanged.
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('current_step', $data);
        $this->assertArrayHasKey('next_route', $data);
        $this->assertArrayHasKey('provider_authorities', $data);
        $this->assertSame('active', $data['status']);
        $this->assertIsArray($data['provider_authorities']);
        $this->assertNotEmpty($data['provider_authorities']);

        // provider_type_context is additive.
        $this->assertArrayHasKey('provider_type_context', $data);
    }

    public function test_auth_user_provider_switching_uses_most_recent_relationship(): void
    {
        $firstProvider = $this->makeProviderOfType('salon');
        $secondProvider = $this->makeProviderOfType('spa');

        $user = User::factory()->create(['status' => 'active']);

        // First membership is older; second is the most recent relationship.
        ProviderUser::factory()->owner()->create([
            'user_id' => $user->id,
            'provider_id' => $firstProvider->id,
            'created_at' => now()->subHour(),
        ]);
        ProviderUser::factory()->owner()->create([
            'user_id' => $user->id,
            'provider_id' => $secondProvider->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200);
        $this->assertSame(
            $secondProvider->id,
            $response->json('provider_type_context.provider_id')
        );
        $this->assertSame(
            'spa',
            $response->json('provider_type_context.provider_type')
        );

        // Switching: making the first relationship the most recent
        // deterministically switches the resolved context.
        DB::table('provider_users')
            ->where('user_id', $user->id)
            ->where('provider_id', $firstProvider->id)
            ->update(['created_at' => now()->addMinute()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(200);
        $this->assertSame(
            $firstProvider->id,
            $response->json('provider_type_context.provider_id')
        );
        $this->assertSame(
            'salon',
            $response->json('provider_type_context.provider_type')
        );
    }
}

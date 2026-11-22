<?php

namespace Tests\Unit\Domain\Provider;

use App\Models\Provider;
use App\Models\ProviderType;
use App\Services\ProviderTypeContext;
use App\Domain\Provider\ProviderTypeRegistry;
use App\Domain\Provider\Exceptions\UnsupportedProviderTypeError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderTypeContextTest extends TestCase
{
    use RefreshDatabase;

    /** @var ProviderTypeContext */
    private $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = app(ProviderTypeContext::class);
    }

    public function test_resolves_salon_provider_type_context(): void
    {
        $salonType = ProviderType::byKey('salon')->first();
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'provider_type_id' => $salonType->id,
        ]);

        $ctx = $this->context->resolveForProvider($provider);

        $this->assertFalse($ctx->hasError());
        $this->assertSame($provider->id, $ctx->getProvider()->id);
        $this->assertSame('salon', $ctx->getTypeKey());
        $this->assertNotNull($ctx->getDefinition());
        $this->assertSame('Salon', $ctx->getDefinition()->displayName);
        $this->assertFalse($ctx->isIndependent());
        $this->assertFalse($ctx->isOwnerManaged());
        $this->assertTrue($ctx->isTypeActive());
    }

    public function test_resolves_independent_specialist_provider_type_context(): void
    {
        $specialistType = ProviderType::byKey('independent_specialist')->first();
        $provider = Provider::factory()->create([
            'type' => 'independent_specialist',
            'provider_type_id' => $specialistType->id,
        ]);

        $ctx = $this->context->resolveForProvider($provider);

        $this->assertFalse($ctx->hasError());
        $this->assertSame('independent_specialist', $ctx->getTypeKey());
        $this->assertNotNull($ctx->getDefinition());
        $this->assertSame('Independent Specialist', $ctx->getDefinition()->displayName);
        $this->assertTrue($ctx->isIndependent());
        $this->assertTrue($ctx->isOwnerManaged());
        $this->assertTrue($ctx->isTypeActive());
    }

    public function test_resolves_spa_provider_type_context(): void
    {
        $spaType = ProviderType::byKey('spa')->first();
        $provider = Provider::factory()->create([
            'type' => 'spa',
            'provider_type_id' => $spaType->id,
        ]);

        $ctx = $this->context->resolveForProvider($provider);

        $this->assertFalse($ctx->hasError());
        $this->assertSame('spa', $ctx->getTypeKey());
        $this->assertNotNull($ctx->getDefinition());
        $this->assertSame('Spa', $ctx->getDefinition()->displayName);
        $this->assertFalse($ctx->isIndependent());
        $this->assertFalse($ctx->isOwnerManaged());
    }

    public function test_resolves_beauty_school_provider_type_context(): void
    {
        $beautySchoolType = ProviderType::byKey('beauty_school')->first();
        $provider = Provider::factory()->create([
            'type' => 'beauty_school',
            'provider_type_id' => $beautySchoolType->id,
        ]);

        $ctx = $this->context->resolveForProvider($provider);

        $this->assertFalse($ctx->hasError());
        $this->assertSame('beauty_school', $ctx->getTypeKey());
        $this->assertNotNull($ctx->getDefinition());
        $this->assertSame('Beauty School', $ctx->getDefinition()->displayName);
        $this->assertFalse($ctx->isIndependent());
        $this->assertFalse($ctx->isOwnerManaged());
    }

    public function test_resolves_mobile_team_provider_type_context(): void
    {
        $mobileTeamType = ProviderType::byKey('mobile_team')->first();
        $provider = Provider::factory()->create([
            'type' => 'mobile_team',
            'provider_type_id' => $mobileTeamType->id,
        ]);

        $ctx = $this->context->resolveForProvider($provider);

        $this->assertFalse($ctx->hasError());
        $this->assertSame('mobile_team', $ctx->getTypeKey());
        $this->assertNotNull($ctx->getDefinition());
        $this->assertSame('Mobile Team', $ctx->getDefinition()->displayName);
        $this->assertFalse($ctx->isIndependent());
        $this->assertFalse($ctx->isOwnerManaged());
    }

    public function test_missing_provider_returns_error(): void
    {
        $ctx = $this->context->resolveForProviderId('00000000-0000-0000-0000-000000000000');

        $this->assertTrue($ctx->hasError());
        $this->assertNull($ctx->getProvider());
        $this->assertNull($ctx->getTypeKey());
        $this->assertNull($ctx->getDefinition());
        $this->assertStringContainsString('No provider found', $ctx->getError());
    }

    public function test_context_resolves_when_legacy_type_column_is_dropped(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('providers', 'type'),
            'Phase 5 verification: providers.type column must be dropped from the database.'
        );

        $salonType = ProviderType::byKey('salon')->first();
        $provider = Provider::factory()->create([
            'provider_type_id' => $salonType->id,
        ]);

        $ctx = $this->context->resolveForProvider($provider->fresh());

        $this->assertFalse($ctx->hasError());
        $this->assertSame('salon', $ctx->getTypeKey());
        $this->assertSame('Salon', $ctx->getDefinition()->displayName);
    }

    public function test_missing_provider_type_relationship_returns_error(): void
    {
        // Unresolvable FK reference (not persisted; the DB FK protects real
        // rows). The context must fail explicitly instead of falling back.
        $provider = new Provider([
            'provider_type_id' => \Illuminate\Support\Str::uuid()->toString(),
            'type' => 'salon',
            'display_name' => 'Unresolvable Provider',
        ]);

        $ctx = $this->context->resolveForProvider($provider);

        $this->assertTrue($ctx->hasError());
        $this->assertNull($ctx->getTypeKey());
        $this->assertNull($ctx->getDefinition());
        $this->assertStringContainsString('not supported', $ctx->getError());
    }

    public function test_inactive_type_remains_resolvable_for_existing_provider(): void
    {
        // Existing provider first, then deactivate its type. Inactive types
        // remain resolvable; they are simply not available for new Providers.
        $spaType = ProviderType::byKey('spa')->first();
        $provider = Provider::factory()->create([
            'type' => 'spa',
            'provider_type_id' => $spaType->id,
        ]);

        $spaType->update(['is_active' => false]);

        $ctx = $this->context->resolveForProvider($provider->fresh());

        $this->assertFalse($ctx->hasError());
        $this->assertSame('spa', $ctx->getTypeKey());
        $this->assertNotNull($ctx->getDefinition());
        $this->assertFalse($ctx->isTypeActive()); // Type is inactive
        $this->assertSame('Spa', $ctx->getDefinition()->displayName); // But still resolvable
    }

    public function test_to_array_returns_complete_context(): void
    {
        $salonType = ProviderType::byKey('salon')->first();
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'provider_type_id' => $salonType->id,
        ]);

        $ctx = $this->context->resolveForProvider($provider);
        $array = $ctx->toArray();

        $this->assertSame($provider->id, $array['provider_id']);
        $this->assertSame('salon', $array['provider_type']);
        $this->assertNotNull($array['provider_type_definition']);
        $this->assertSame('salon', $array['provider_type_definition']['type']);
        $this->assertSame('Salon', $array['provider_type_definition']['display_name']);
        $this->assertFalse($array['provider_type_definition']['is_independent']);
        $this->assertTrue($array['provider_type_definition']['is_active']);
        $this->assertFalse($array['is_independent']);
        $this->assertFalse($array['is_owner_managed']);
        $this->assertNull($array['error']);
    }

    public function test_registry_loads_all_five_types(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        // Verify all 5 types are loaded
        $all = $registry->all();
        $this->assertCount(5, $all);
        $this->assertArrayHasKey('salon', $all);
        $this->assertArrayHasKey('independent_specialist', $all);
        $this->assertArrayHasKey('spa', $all);
        $this->assertArrayHasKey('beauty_school', $all);
        $this->assertArrayHasKey('mobile_team', $all);
    }

    public function test_is_available_for_new_registeration_respects_is_active(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        // All 5 types are active by default
        foreach (['salon', 'independent_specialist', 'spa', 'beauty_school', 'mobile_team'] as $key) {
            $this->assertTrue($registry->isAvailableForNewRegistration($key));
        }

        // Deactivate spa
        $spaType = ProviderType::byKey('spa')->first();
        $spaType->update(['is_active' => false]);

        // spa is no longer available for new registration
        $this->assertFalse($registry->isAvailableForNewRegistration('spa'));
        // But spa is still supported (resolvable for existing providers)
        $this->assertTrue($registry->isSupported('spa'));
    }

    public function test_get_type_id_returns_database_id(): void
    {
        $registry = app(ProviderTypeRegistry::class);
        $salonType = ProviderType::byKey('salon')->first();

        $this->assertSame($salonType->id, $registry->getTypeId('salon'));
        $this->assertNull($registry->getTypeId('nonexistent_type'));
    }

    public function test_context_resolves_through_provider_type_relationship(): void
    {
        $mobileTeamType = ProviderType::byKey('mobile_team')->first();
        $provider = Provider::factory()->create([
            'type' => 'mobile_team',
            'provider_type_id' => $mobileTeamType->id,
        ]);

        $ctx = $this->context->resolveForProvider($provider);

        $this->assertFalse($ctx->hasError());
        $this->assertSame('mobile_team', $ctx->getTypeKey());
        $this->assertNotNull($ctx->getDefinition());
        $this->assertSame('Mobile Team', $ctx->getDefinition()->displayName);
    }

    public function test_registry_cache_invalidated_on_type_change(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        // Cache the registry
        $registry->for('salon');

        // Update a provider type name
        $salonType = ProviderType::byKey('salon')->first();
        $salonType->update(['name' => 'Salon Studio']);

        // The registry cache should be invalidated via model event
        $definition = $registry->for('salon');
        $this->assertSame('Salon Studio', $definition->displayName);
    }

    public function test_resolve_from_request_uses_provider_id_request_attribute(): void
    {
        // Explicitly selected provider (set by provider.context middleware)
        $specialistType = ProviderType::byKey('independent_specialist')->first();
        $selected = Provider::factory()->create([
            'type' => 'independent_specialist',
            'provider_type_id' => $specialistType->id,
        ]);

        $request = \Illuminate\Http\Request::create('/api/v1/test', 'GET');
        $request->attributes->set('provider_id', $selected->id);

        $ctx = app(ProviderTypeContext::class)->resolveFromRequest($request);

        $this->assertFalse($ctx->hasError());
        $this->assertSame($selected->id, $ctx->getProvider()->id);
        $this->assertSame('independent_specialist', $ctx->getTypeKey());
        $this->assertTrue($ctx->isIndependent());
    }

    public function test_resolve_from_request_falls_back_to_authenticated_user_provider(): void
    {
        // No provider_id request attribute; authenticated user has a provider
        $spaType = ProviderType::byKey('spa')->first();
        $provider = Provider::factory()->create([
            'type' => 'spa',
            'provider_type_id' => $spaType->id,
        ]);
        $user = \App\Models\User::factory()->create(['status' => 'active']);
        \App\Models\ProviderUser::factory()->owner()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
        ]);

        $this->actingAs($user);

        $request = \Illuminate\Http\Request::create('/api/v1/test', 'GET');
        $ctx = app(ProviderTypeContext::class)->resolveFromRequest($request);

        $this->assertFalse($ctx->hasError());
        $this->assertSame($provider->id, $ctx->getProvider()->id);
        $this->assertSame('spa', $ctx->getTypeKey());
    }

    public function test_resolve_from_request_without_provider_returns_error(): void
    {
        // No provider_id attribute and no authenticated user
        $request = \Illuminate\Http\Request::create('/api/v1/test', 'GET');

        $ctx = app(ProviderTypeContext::class)->resolveFromRequest($request);

        $this->assertTrue($ctx->hasError());
        $this->assertNull($ctx->getProvider());
        $this->assertStringContainsString('No provider found', $ctx->getError());
    }
}

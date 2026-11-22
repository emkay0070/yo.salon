<?php

namespace Tests\Unit\Domain\Provider;

use App\Models\Organization;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Domain\Provider\ProviderTypeRegistry;
use App\Domain\Provider\ProviderTypeDefinition;
use App\Domain\Provider\Exceptions\UnsupportedProviderTypeError;
use App\Services\OnboardingService;
use App\Services\CustomerSpecialistService;
use App\Domain\Specialist\Services\IndependentWorkspaceProvisioner;
use App\Models\Specialist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProviderTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_types_table_is_seeded_with_five_operational_types(): void
    {
        $keys = ProviderType::active()->pluck('key')->sort()->values()->all();

        $this->assertSame([
            'beauty_school',
            'independent_specialist',
            'mobile_team',
            'salon',
            'spa',
        ], $keys);
    }

    public function test_provider_type_keys_are_stable_and_unique(): void
    {
        $this->assertSame(5, ProviderType::active()->count());
        $this->assertSame(5, ProviderType::active()->distinct('key')->count());

        foreach (['salon', 'independent_specialist', 'spa', 'beauty_school', 'mobile_team'] as $key) {
            $this->assertNotNull(ProviderType::byKey($key)->first());
        }
    }

    public function test_provider_type_display_names(): void
    {
        $this->assertSame('Salon', ProviderType::byKey('salon')->value('name'));
        $this->assertSame('Independent Specialist', ProviderType::byKey('independent_specialist')->value('name'));
        $this->assertSame('Spa', ProviderType::byKey('spa')->value('name'));
        $this->assertSame('Beauty School', ProviderType::byKey('beauty_school')->value('name'));
        $this->assertSame('Mobile Team', ProviderType::byKey('mobile_team')->value('name'));
    }

    public function test_independent_specialist_has_is_independent_true(): void
    {
        $this->assertTrue(ProviderType::byKey('independent_specialist')->value('is_independent'));
        $this->assertTrue(ProviderType::byKey('independent_specialist')->value('is_owner_managed'));

        foreach (['salon', 'spa', 'beauty_school', 'mobile_team'] as $key) {
            $this->assertFalse(ProviderType::byKey($key)->value('is_independent'));
            $this->assertFalse(ProviderType::byKey($key)->value('is_owner_managed'));
        }
    }

    public function test_registry_resolves_all_seeded_types(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        foreach (['salon', 'independent_specialist', 'spa', 'beauty_school', 'mobile_team'] as $key) {
            $definition = $registry->for($key);
            $this->assertInstanceOf(ProviderTypeDefinition::class, $definition);
            $this->assertSame($key, $definition->type);
        }
    }

    public function test_registry_throws_for_unknown_type(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        $this->expectException(UnsupportedProviderTypeError::class);
        $this->expectExceptionMessage("Provider type 'unknown_type' is not supported.");

        $registry->for('unknown_type');
    }

    public function test_registry_is_supported_returns_true_for_known_types(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        foreach (['salon', 'independent_specialist', 'spa', 'beauty_school', 'mobile_team'] as $key) {
            $this->assertTrue($registry->isSupported($key));
        }

        $this->assertFalse($registry->isSupported('unknown_type'));
    }

    public function test_registry_is_cached_and_invalidated_on_change(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        // First call loads from DB and caches.
        $definition1 = $registry->for('salon');
        $this->assertSame('Salon', $definition1->displayName);

        // Modify a provider type in the database via Eloquent (fires model events).
        $salonType = ProviderType::byKey('salon')->first();
        $salonType->update(['name' => 'Salon Studio']);

        // Cache should be invalidated via model event.
        $definition2 = $registry->for('salon');
        $this->assertSame('Salon Studio', $definition2->displayName);
    }

    public function test_registry_cache_is_cleared_when_type_created(): void
    {
        $registry = app(ProviderTypeRegistry::class);
        $this->assertFalse($registry->isSupported('temp_type'));

        ProviderType::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'key' => 'temp_type',
            'name' => 'Temp Type',
            'is_active' => true,
            'is_independent' => false,
            'is_owner_managed' => false,
        ]);

        $this->assertTrue($registry->isSupported('temp_type'));

        // Clean up
        ProviderType::byKey('temp_type')->delete();
    }

    public function test_provider_has_foreign_key_to_provider_type(): void
    {
        $salonType = ProviderType::byKey('salon')->first();

        $provider = Provider::factory()->create();

        $this->assertNotNull($provider->provider_type_id);
        $this->assertSame($salonType->id, $provider->provider_type_id);
        $this->assertInstanceOf(ProviderType::class, $provider->providerType);
        $this->assertSame('salon', $provider->providerType->key);
    }

    public function test_provider_factory_creates_provider_with_correct_type_id(): void
    {
        $specialistType = ProviderType::byKey('independent_specialist')->first();

        $provider = Provider::factory()->independentSpecialist()->create();

        $this->assertSame('independent_specialist', $provider->type);
        $this->assertSame($specialistType->id, $provider->provider_type_id);
    }

    public function test_get_type_definition_uses_registry(): void
    {
        $salonType = ProviderType::byKey('salon')->first();
        $provider = Provider::factory()->create(['type' => 'salon', 'provider_type_id' => $salonType->id]);

        $definition = $provider->getTypeDefinition();

        $this->assertInstanceOf(ProviderTypeDefinition::class, $definition);
        $this->assertSame('salon', $definition->type);
        $this->assertFalse($definition->isIndependent);
    }

    public function test_unknown_type_in_provider_cannot_be_created_via_registry(): void
    {
        // The registry throws for unknown types.
        $registry = app(ProviderTypeRegistry::class);

        $this->expectException(UnsupportedProviderTypeError::class);
        $registry->for('not_a_real_type');
    }

    public function test_onboarding_creates_provider_with_provider_type_id(): void
    {
        // Seed the business categories needed for onboarding type mapping.
        DB::table('business_categories')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'provider_type' => 'salon',
            'slug' => 'hair_salon',
            'name' => 'Hair Salon',
            'short_name' => 'Salon',
            'description' => 'Hair • Styling • Beauty',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('business_categories')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'provider_type' => 'spa',
            'slug' => 'spa',
            'name' => 'Spa',
            'short_name' => 'Spa',
            'description' => 'Treatments • Relaxation • Wellness',
            'is_active' => true,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('business_categories')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'provider_type' => 'mobile_team',
            'slug' => 'mobile_beauty',
            'name' => 'Mobile Beauty',
            'short_name' => 'Mobile',
            'description' => 'On-demand beauty services',
            'is_active' => true,
            'sort_order' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $onboardingService = app(OnboardingService::class);

        // Use reflection to access the private method for testing
        $reflection = new \ReflectionClass($onboardingService);
        $method = $reflection->getMethod('mapCategoryToProviderType');
        $method->setAccessible(true);

        // Known category with provider_type in business_categories
        $type = $method->invoke($onboardingService, 'hair_salon');
        $this->assertSame('salon', $type);

        $type = $method->invoke($onboardingService, 'spa');
        $this->assertSame('spa', $type);

        $type = $method->invoke($onboardingService, 'mobile_beauty');
        $this->assertSame('mobile_team', $type);
    }

    public function test_onboarding_throws_for_unknown_category(): void
    {
        $onboardingService = app(OnboardingService::class);

        $reflection = new \ReflectionClass($onboardingService);
        $method = $reflection->getMethod('mapCategoryToProviderType');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($onboardingService, 'nonexistent_category_slug');
    }

    public function test_onboarding_throws_for_empty_category_slug(): void
    {
        $onboardingService = app(OnboardingService::class);

        $reflection = new \ReflectionClass($onboardingService);
        $method = $reflection->getMethod('mapCategoryToProviderType');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($onboardingService, null);
    }

    public function test_no_silent_salon_fallback_in_onboarding(): void
    {
        // Verify that an unknown category does not silently resolve to 'salon'
        $onboardingService = app(OnboardingService::class);

        $reflection = new \ReflectionClass($onboardingService);
        $method = $reflection->getMethod('mapCategoryToProviderType');
        $method->setAccessible(true);

        try {
            $method->invoke($onboardingService, 'totally_fake_category');
            $this->fail('Expected InvalidArgumentException for unknown category');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('totally_fake_category', $e->getMessage());
        }
    }

    public function test_customer_specialist_service_throws_for_unknown_provider(): void
    {
        $service = app(CustomerSpecialistService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('determineRelationshipOrigin');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($service, \Illuminate\Support\Str::uuid()->toString());
    }

    public function test_independent_workspace_provisioner_creates_provider_with_type_id(): void
    {
        $provisioner = app(IndependentWorkspaceProvisioner::class);

        $specialist = Specialist::factory()->create();

        $provisioner->ensureIndependentWorkspace($specialist);

        $specialistType = ProviderType::byKey('independent_specialist')->first();

        $provider = Provider::where('provider_type_id', $specialistType->id)->first();

        $this->assertNotNull($provider);
        $this->assertSame($specialistType->id, $provider->provider_type_id);
    }

    public function test_eloquent_created_provider_gets_provider_type_id_via_model_event(): void
    {
        $org = Organization::factory()->create();
        $spaType = ProviderType::byKey('spa')->first();

        // Create via Eloquent (not query builder) so the creating event fires.
        $provider = Provider::create([
            'id'              => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $org->id,
            'type'            => 'spa',
            'status'          => 'active',
            'display_name'    => 'Test Spa',
            'slug'            => 'test-spa-' . \Illuminate\Support\Str::random(8),
            'active'          => true,
        ]);

        $this->assertNotNull($provider->provider_type_id);
        $this->assertSame($spaType->id, $provider->provider_type_id);
    }

    public function test_supplier_type_is_not_registered(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        $this->assertFalse($registry->isSupported('supplier'));

        $this->expectException(UnsupportedProviderTypeError::class);
        $registry->for('supplier');
    }

    public function test_all_method_returns_all_registered_types(): void
    {
        $registry = app(ProviderTypeRegistry::class);

        $all = $registry->all();

        $this->assertCount(5, $all);
        $this->assertArrayHasKey('salon', $all);
        $this->assertArrayHasKey('independent_specialist', $all);
        $this->assertArrayHasKey('spa', $all);
        $this->assertArrayHasKey('beauty_school', $all);
        $this->assertArrayHasKey('mobile_team', $all);
    }

    public function test_provider_can_be_created_with_only_provider_type_id(): void
    {
        $org = Organization::factory()->create();
        $spaType = ProviderType::byKey('spa')->first();

        $provider = Provider::create([
            'organization_id'  => $org->id,
            'provider_type_id' => $spaType->id,
            'status'           => 'active',
            'display_name'     => 'Canonical Spa',
            'slug'             => 'canonical-spa-' . \Illuminate\Support\Str::random(8),
            'active'           => true,
        ]);

        $this->assertSame($spaType->id, $provider->provider_type_id);
        // Legacy compatibility value is derived from the FK.
        $this->assertSame('spa', $provider->type);
        $this->assertSame('spa', $provider->providerTypeKey());
        $this->assertSame('spa', $provider->getTypeDefinition()->type);
    }

    public function test_conflicting_type_and_provider_type_id_is_rejected(): void
    {
        $salonType = ProviderType::byKey('salon')->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Conflicting provider types');

        Provider::create([
            'type' => 'spa',
            'provider_type_id' => $salonType->id,
            'status' => 'active',
            'display_name' => 'Conflicted Provider',
            'slug' => 'conflicted-provider-' . \Illuminate\Support\Str::random(8),
            'active' => true,
        ]);
    }

    public function test_provider_creation_without_any_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('without a Provider Type');

        Provider::create([
            'status' => 'active',
            'display_name' => 'Unclassified Provider',
            'slug' => 'unclassified-provider-' . \Illuminate\Support\Str::random(8),
            'active' => true,
        ]);
    }

    public function test_unsupported_legacy_type_is_rejected_on_creation(): void
    {
        $this->expectException(UnsupportedProviderTypeError::class);
        $this->expectExceptionMessage("Provider type 'supplier' is not supported.");

        Provider::create([
            'type' => 'supplier',
            'status' => 'active',
            'display_name' => 'Supplier Provider',
            'slug' => 'supplier-provider-' . \Illuminate\Support\Str::random(8),
            'active' => true,
        ]);
    }

    public function test_unknown_provider_type_id_is_rejected_on_creation(): void
    {
        $this->expectException(UnsupportedProviderTypeError::class);

        Provider::create([
            'provider_type_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => 'active',
            'display_name' => 'Unknown Type Provider',
            'slug' => 'unknown-type-provider-' . \Illuminate\Support\Str::random(8),
            'active' => true,
        ]);
    }

    public function test_inactive_provider_type_is_not_available_for_new_providers(): void
    {
        $spaType = ProviderType::byKey('spa')->first();
        $spaType->update(['is_active' => false]);

        $this->expectException(UnsupportedProviderTypeError::class);

        Provider::create([
            'provider_type_id' => $spaType->id,
            'status' => 'active',
            'display_name' => 'Inactive Type Provider',
            'slug' => 'inactive-type-provider-' . \Illuminate\Support\Str::random(8),
            'active' => true,
        ]);
    }

    public function test_providers_table_has_dropped_legacy_type_column(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('providers', 'type'),
            'Phase 5 verification: providers.type column must be dropped from the database.'
        );
    }

    public function test_get_type_definition_uses_fk_and_type_accessor_works(): void
    {
        $salonType = ProviderType::byKey('salon')->first();
        $provider = Provider::factory()->create([
            'provider_type_id' => $salonType->id,
        ]);

        $definition = $provider->fresh()->getTypeDefinition();

        $this->assertSame('salon', $definition->type);
        $this->assertSame('Salon', $definition->displayName);
        $this->assertSame('salon', $provider->type);
    }

    public function test_scope_by_type_uses_provider_type_fk(): void
    {
        $spaType = ProviderType::byKey('spa')->first();
        $spa = Provider::factory()->create([
            'type' => 'spa',
            'provider_type_id' => $spaType->id,
        ]);
        Provider::factory()->create(['type' => 'salon']);

        $results = Provider::byType('spa')->pluck('id');

        $this->assertTrue($results->contains($spa->id));
        $this->assertCount(1, $results);
        $this->assertCount(0, Provider::byType('unknown_type')->get());
    }

    public function test_factory_states_establish_canonical_foreign_keys(): void
    {
        foreach ([
            'spa' => 'spa',
            'beautySchool' => 'beauty_school',
            'mobileTeam' => 'mobile_team',
            'independentSpecialist' => 'independent_specialist',
        ] as $factoryState => $typeKey) {
            $provider = Provider::factory()->{$factoryState}()->create();
            $type = ProviderType::byKey($typeKey)->first();

            $this->assertSame($type->id, $provider->provider_type_id, $factoryState);
            $this->assertSame($typeKey, $provider->type, $factoryState);
            $this->assertSame($typeKey, $provider->providerTypeKey(), $factoryState);
        }
    }

    public function test_factory_of_type_supports_canonical_type_creation(): void
    {
        $provider = Provider::factory()->ofType('beauty_school')->create();

        $type = ProviderType::byKey('beauty_school')->first();

        $this->assertSame($type->id, $provider->provider_type_id);
        $this->assertSame('beauty_school', $provider->providerTypeKey());
    }

    public function test_legacy_type_override_in_factory_stays_consistent(): void
    {
        $provider = Provider::factory()->create(['type' => 'spa']);

        $type = ProviderType::byKey('spa')->first();

        $this->assertSame($type->id, $provider->provider_type_id);
        $this->assertSame('spa', $provider->type);
    }

    public function test_provider_type_can_be_updated_using_virtual_type_attribute(): void
    {
        $provider = Provider::factory()->create(); // default is salon
        $this->assertSame('salon', $provider->type);

        $provider->update(['type' => 'spa']);

        $spaType = ProviderType::byKey('spa')->first();
        $this->assertSame($spaType->id, $provider->fresh()->provider_type_id);
        $this->assertSame('spa', $provider->fresh()->type);
        $this->assertSame('spa', $provider->fresh()->providerTypeKey());
    }

    public function test_provider_type_can_be_updated_using_provider_type_id(): void
    {
        $provider = Provider::factory()->create(); // default is salon
        $beautySchoolType = ProviderType::byKey('beauty_school')->first();

        $provider->update(['provider_type_id' => $beautySchoolType->id]);

        $this->assertSame($beautySchoolType->id, $provider->fresh()->provider_type_id);
        $this->assertSame('beauty_school', $provider->fresh()->type);
        $this->assertSame('beauty_school', $provider->fresh()->providerTypeKey());
    }

    public function test_provider_type_update_rejects_unknown_type(): void
    {
        $provider = Provider::factory()->create();

        $this->expectException(UnsupportedProviderTypeError::class);
        $provider->update(['type' => 'nonexistent_type']);
    }

    public function test_provider_type_update_rejects_conflicting_values(): void
    {
        $provider = Provider::factory()->create();
        $salonType = ProviderType::byKey('salon')->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Conflicting provider types');

        $provider->update([
            'type' => 'spa',
            'provider_type_id' => $salonType->id,
        ]);
    }
}

<?php

namespace Tests\Unit\Domain\Provider;

use App\Domain\Provider\Exceptions\UnsupportedProviderTypeError;
use App\Domain\Provider\ProviderTypeDefinition;
use App\Domain\Provider\ProviderTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderTypeRegistryTest extends TestCase
{
    public function test_it_returns_definition_for_salon()
    {
        $registry = app(ProviderTypeRegistry::class);
        $definition = $registry->for('salon');

        $this->assertInstanceOf(ProviderTypeDefinition::class, $definition);
        $this->assertEquals('salon', $definition->type);
        $this->assertEquals('Salon', $definition->displayName);
        $this->assertFalse($definition->isIndependent);
        $this->assertFalse($definition->isOwnerManaged);
    }

    public function test_it_returns_definition_for_independent_specialist()
    {
        $registry = app(ProviderTypeRegistry::class);
        $definition = $registry->for('independent_specialist');

        $this->assertInstanceOf(ProviderTypeDefinition::class, $definition);
        $this->assertEquals('independent_specialist', $definition->type);
        $this->assertEquals('Independent Specialist', $definition->displayName);
        $this->assertTrue($definition->isIndependent);
        $this->assertTrue($definition->isOwnerManaged);
    }

    public function test_it_returns_definition_for_spa()
    {
        $registry = app(ProviderTypeRegistry::class);
        $definition = $registry->for('spa');

        $this->assertInstanceOf(ProviderTypeDefinition::class, $definition);
        $this->assertEquals('spa', $definition->type);
        $this->assertEquals('Spa', $definition->displayName);
        $this->assertFalse($definition->isIndependent);
        $this->assertFalse($definition->isOwnerManaged);
    }

    public function test_it_returns_definition_for_beauty_school()
    {
        $registry = app(ProviderTypeRegistry::class);
        $definition = $registry->for('beauty_school');

        $this->assertInstanceOf(ProviderTypeDefinition::class, $definition);
        $this->assertEquals('beauty_school', $definition->type);
        $this->assertEquals('Beauty School', $definition->displayName);
        $this->assertFalse($definition->isIndependent);
        $this->assertFalse($definition->isOwnerManaged);
    }

    public function test_it_returns_definition_for_mobile_team()
    {
        $registry = app(ProviderTypeRegistry::class);
        $definition = $registry->for('mobile_team');

        $this->assertInstanceOf(ProviderTypeDefinition::class, $definition);
        $this->assertEquals('mobile_team', $definition->type);
        $this->assertEquals('Mobile Team', $definition->displayName);
        $this->assertFalse($definition->isIndependent);
        $this->assertFalse($definition->isOwnerManaged);
    }

    public function test_it_throws_exception_for_unsupported_type()
    {
        $this->expectException(UnsupportedProviderTypeError::class);
        $this->expectExceptionMessage("Provider type 'unknown_type' is not supported.");

        $registry = app(ProviderTypeRegistry::class);
        $registry->for('unknown_type');
    }

    public function test_it_checks_if_type_is_supported()
    {
        $registry = app(ProviderTypeRegistry::class);

        $this->assertTrue($registry->isSupported('salon'));
        $this->assertTrue($registry->isSupported('independent_specialist'));
        $this->assertTrue($registry->isSupported('spa'));
        $this->assertTrue($registry->isSupported('beauty_school'));
        $this->assertTrue($registry->isSupported('mobile_team'));
        $this->assertFalse($registry->isSupported('unknown_type'));
    }

    public function test_it_returns_all_definitions()
    {
        $registry = app(ProviderTypeRegistry::class);
        $all = $registry->all();

        $this->assertIsArray($all);
        $this->assertCount(5, $all);
        $this->assertArrayHasKey('salon', $all);
        $this->assertArrayHasKey('independent_specialist', $all);
        $this->assertArrayHasKey('spa', $all);
        $this->assertArrayHasKey('beauty_school', $all);
        $this->assertArrayHasKey('mobile_team', $all);
    }

    public function test_registry_is_singleton()
    {
        $registry1 = app(ProviderTypeRegistry::class);
        $registry2 = app(ProviderTypeRegistry::class);

        $this->assertSame($registry1, $registry2);
    }
}

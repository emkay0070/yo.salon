<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProviderTypeDropColumnMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function getDropMigration(): object
    {
        return require database_path('migrations/2026_11_20_000000_drop_type_from_providers_table.php');
    }

    public function test_schema_has_provider_type_id_and_does_not_have_type_column(): void
    {
        $this->assertTrue(
            Schema::hasTable('provider_types'),
            'provider_types table must exist.'
        );

        $this->assertTrue(
            Schema::hasColumn('providers', 'provider_type_id'),
            'providers table must have provider_type_id column.'
        );

        $this->assertFalse(
            Schema::hasColumn('providers', 'type'),
            'providers.type column must be dropped.'
        );
    }

    public function test_provider_records_retain_valid_provider_type_id_foreign_key(): void
    {
        $providers = [
            Provider::factory()->create(['provider_type_id' => ProviderType::byKey('salon')->value('id')]),
            Provider::factory()->create(['provider_type_id' => ProviderType::byKey('spa')->value('id')]),
            Provider::factory()->create(['provider_type_id' => ProviderType::byKey('independent_specialist')->value('id')]),
            Provider::factory()->create(['provider_type_id' => ProviderType::byKey('beauty_school')->value('id')]),
            Provider::factory()->create(['provider_type_id' => ProviderType::byKey('mobile_team')->value('id')]),
        ];

        foreach ($providers as $provider) {
            $raw = DB::table('providers')->where('id', $provider->id)->first();
            $this->assertNotNull($raw->provider_type_id);

            $typeRecord = DB::table('provider_types')->where('id', $raw->provider_type_id)->first();
            $this->assertNotNull($typeRecord);
            $this->assertSame($provider->providerTypeKey(), $typeRecord->key);
        }
    }

    public function test_migration_down_restores_legacy_type_column_and_backfills_from_provider_types(): void
    {
        $types = ['salon', 'spa', 'independent_specialist', 'beauty_school', 'mobile_team'];
        $created = [];

        foreach ($types as $key) {
            $typeId = ProviderType::byKey($key)->value('id');
            $provider = Provider::factory()->create([
                'provider_type_id' => $typeId,
            ]);
            $created[$key] = $provider->id;
        }

        // Run migration down()
        $migration = $this->getDropMigration();
        $migration->down();

        // Verify column and index are restored
        $this->assertTrue(
            Schema::hasColumn('providers', 'type'),
            'Migration down() must restore the providers.type column.'
        );

        // Verify accurate backfill for each provider
        foreach ($created as $expectedType => $providerId) {
            $raw = DB::table('providers')->where('id', $providerId)->first();
            $this->assertSame(
                $expectedType,
                $raw->type,
                "Provider {$providerId} legacy type must be backfilled as '{$expectedType}'."
            );
        }

        // Run migration up() again to verify re-application works seamlessly
        $migration->up();

        $this->assertFalse(
            Schema::hasColumn('providers', 'type'),
            'Migration up() must drop providers.type column cleanly on re-application.'
        );

        // Verify model continues to resolve virtual type attribute
        foreach ($created as $expectedType => $providerId) {
            $provider = Provider::find($providerId);
            $this->assertSame($expectedType, $provider->type);
            $this->assertSame($expectedType, $provider->providerTypeKey());
        }
    }
}

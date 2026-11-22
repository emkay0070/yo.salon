<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LocationPrimaryMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const INDEX_NAME = 'locations_one_primary_per_provider';

    private function migration(): object
    {
        return require database_path('migrations/2026_11_18_000000_enforce_one_primary_location_per_provider.php');
    }

    public function test_migration_creates_partial_unique_index_and_changes_the_default_to_false()
    {
        $index = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('indexname', self::INDEX_NAME)
            ->first();

        $this->assertNotNull($index, 'The primary-location partial unique index should exist.');
        $this->assertStringContainsString('UNIQUE INDEX', strtoupper($index->indexdef));
        $this->assertStringContainsString('WHERE (is_primary = true)', $index->indexdef);

        $default = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'locations')
            ->where('column_name', 'is_primary')
            ->value('column_default');

        $this->assertSame('false', $default);
    }

    public function test_new_locations_default_to_non_primary_at_the_database_level()
    {
        $provider = Provider::factory()->create();
        $locationId = (string) Str::uuid();

        DB::table('locations')->insert([
            'id' => $locationId,
            'provider_id' => $provider->id,
            'name' => 'Default Flag Location',
            'slug' => 'default-flag-'.Str::lower(Str::random(8)),
            'address' => '1 Default Street',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(Location::findOrFail($locationId)->is_primary);
    }

    public function test_rollback_drops_index_and_restores_previous_default()
    {
        $migration = $this->migration();

        $migration->down();

        $index = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('indexname', self::INDEX_NAME)
            ->first();
        $default = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'locations')
            ->where('column_name', 'is_primary')
            ->value('column_default');

        $this->assertNull($index);
        $this->assertSame('true', $default);

        // Restore the forward migration state for the remainder of this test.
        $migration->up();

        $this->assertNotNull(DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('indexname', self::INDEX_NAME)
            ->first());
        $this->assertSame('false', DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'locations')
            ->where('column_name', 'is_primary')
            ->value('column_default'));
    }

    public function test_migration_stops_with_actionable_error_when_duplicate_primaries_exist()
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);

        $provider = Provider::factory()->create();
        $first = Location::factory()->primary()->create([
            'provider_id' => $provider->id,
            'name' => 'Duplicate One',
            'slug' => 'duplicate-one',
        ]);
        $second = Location::factory()->primary()->create([
            'provider_id' => $provider->id,
            'name' => 'Duplicate Two',
            'slug' => 'duplicate-two',
        ]);

        try {
            $this->migration()->up();
            $this->fail('Expected the migration to stop because duplicate primary Locations exist.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate primary Locations were found', $exception->getMessage());
            $this->assertStringContainsString($provider->id, $exception->getMessage());
            $this->assertStringContainsString($first->id, $exception->getMessage());
            $this->assertStringContainsString($second->id, $exception->getMessage());
        }

        $this->assertNull(DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where('indexname', self::INDEX_NAME)
            ->first());

        $this->assertSame('false', DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'locations')
            ->where('column_name', 'is_primary')
            ->value('column_default'));

        $this->assertSame(2, $provider->locations()->where('is_primary', true)->count());
    }
}

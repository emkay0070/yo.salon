<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Create the provider_types table as the authoritative catalogue of
     * supported provider type identities, and link Providers to it via
     * a provider_type_id foreign key.
     *
     * The existing providers.type PostgreSQL ENUM is preserved unchanged.
     */
    public function up(): void
    {
        Schema::create('provider_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_independent')->default(false);
            $table->boolean('is_owner_managed')->default(false);
            $table->timestamps();

            $table->index('key');
            $table->index('is_active');
        });

        // Seed the five existing operational provider types.
        $now = now();
        $types = [
            [
                'id' => Str::uuid()->toString(),
                'key' => 'salon',
                'name' => 'Salon',
                'is_active' => true,
                'is_independent' => false,
                'is_owner_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'key' => 'independent_specialist',
                'name' => 'Independent Specialist',
                'is_active' => true,
                'is_independent' => true,
                'is_owner_managed' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'key' => 'spa',
                'name' => 'Spa',
                'is_active' => true,
                'is_independent' => false,
                'is_owner_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'key' => 'beauty_school',
                'name' => 'Beauty School',
                'is_active' => true,
                'is_independent' => false,
                'is_owner_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'key' => 'mobile_team',
                'name' => 'Mobile Team',
                'is_active' => true,
                'is_independent' => false,
                'is_owner_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($types as $type) {
            DB::table('provider_types')->insert($type);
        }

        // Build a lookup from provider type key -> provider_types.id
        $typeMap = [];
        foreach (DB::table('provider_types')->where('is_active', true)->get() as $row) {
            $typeMap[$row->key] = $row->id;
        }

        // Detect any existing Provider type values that have no matching registry entry.
        $unmapped = DB::table('providers')
            ->whereNotIn('type', array_keys($typeMap))
            ->distinct()
            ->pluck('type')
            ->all();

        if (!empty($unmapped)) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot migrate providers to provider_type_id: the following provider.type values have no matching provider_types registry entry: %s. '
                    .'Add the missing types to the registry before running this migration.',
                    implode(', ', $unmapped)
                )
            );
        }

        // Add the nullable foreign key column first.
        Schema::table('providers', function (Blueprint $table) {
            $table->uuid('provider_type_id')->nullable()->after('type');
            $table->index('provider_type_id');
        });

        // Backfill existing Providers using their current type value.
        foreach ($typeMap as $key => $typeId) {
            DB::table('providers')
                ->where('type', $key)
                ->whereNull('provider_type_id')
                ->update(['provider_type_id' => $typeId]);
        }

        // Verify all providers now have a provider_type_id.
        $missing = DB::table('providers')->whereNull('provider_type_id')->count();
        if ($missing > 0) {
            throw new \RuntimeException(
                sprintf(
                    'Backfill incomplete: %d Provider(s) still have no provider_type_id.',
                    $missing
                )
            );
        }

        // Enforce NOT NULL and referential integrity.
        Schema::table('providers', function (Blueprint $table) {
            $table->foreign('provider_type_id')
                ->references('id')
                ->on('provider_types')
                ->onDelete('restrict');
            $table->uuid('provider_type_id')->nullable(false)->change();
        });
    }

    /**
     * Roll back by removing the foreign key and column, then dropping the table.
     */
    public function down(): void
    {
        if (Schema::hasTable('providers') && Schema::hasColumn('providers', 'provider_type_id')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->dropForeign(['provider_type_id']);
                $table->dropIndex(['provider_type_id']);
                $table->dropColumn('provider_type_id');
            });
        }

        Schema::dropIfExists('provider_types');
    }
};

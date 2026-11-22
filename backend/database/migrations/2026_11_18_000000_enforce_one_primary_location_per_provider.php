<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'locations_one_primary_per_provider';

    /**
     * Enforce at most one primary Location per Provider.
     *
     * This migration is deliberately additive and does not repair existing
     * records. If any Provider already has more than one primary Location,
     * the migration stops before creating the index and reports the affected
     * Providers so they can be reviewed manually.
     */
    public function up(): void
    {
        if (! Schema::hasTable('locations')) {
            return;
        }

        $duplicates = DB::table('locations')
            ->select('provider_id', DB::raw('COUNT(*) AS primary_count'))
            ->where('is_primary', true)
            ->groupBy('provider_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('provider_id')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $details = $duplicates->map(function (object $duplicate): string {
                $locationIds = DB::table('locations')
                    ->where('provider_id', $duplicate->provider_id)
                    ->where('is_primary', true)
                    ->orderBy('id')
                    ->pluck('id')
                    ->implode(', ');

                return sprintf(
                    'provider %s has %d primary Locations (%s)',
                    $duplicate->provider_id,
                    (int) $duplicate->primary_count,
                    $locationIds
                );
            })->implode('; ');

            throw new RuntimeException(
                'Cannot enforce one primary Location per Provider: duplicate primary Locations were found. '
                .'No records were changed. Review and correct these Providers manually, then rerun the migration. '
                .$details
            );
        }

        // New Locations are non-primary by default. Application code explicitly
        // selects the first Location as primary while holding the Provider row lock.
        DB::statement('ALTER TABLE locations ALTER COLUMN is_primary SET DEFAULT false');

        // Final database-level safeguard against concurrent or direct writes.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS '.self::INDEX_NAME
            .' ON locations (provider_id) WHERE is_primary = true'
        );
    }

    /**
     * Roll back the index and restore the previous database default.
     *
     * Historical Locations and their primary flags are intentionally untouched.
     */
    public function down(): void
    {
        if (! Schema::hasTable('locations')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);
        DB::statement('ALTER TABLE locations ALTER COLUMN is_primary SET DEFAULT true');
    }
};

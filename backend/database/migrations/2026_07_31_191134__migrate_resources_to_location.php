<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Wave 2C.2: Migrate Resources from Salon-authoritative to Location-canonical
 * 
 * Canonical model: Provider → Location → Resource
 * - location_id = required canonical physical owner
 * - provider_id = not present (inferred via location.provider_id)
 * - salon_id = removed (legacy)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip if salons table doesn't exist (fresh database)
        if (!Schema::hasTable('salons') || !Schema::hasColumn('resources', 'salon_id')) {
            return;
        }

        // Step 1: Backfill location_id from salon.location_id for resources with null location_id
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                UPDATE resources r
                SET location_id = (
                    SELECT location_id FROM salons WHERE id = r.salon_id
                )
                WHERE salon_id IS NOT NULL
                  AND location_id IS NULL
                  AND EXISTS (SELECT 1 FROM salons WHERE salons.id = r.salon_id AND salons.location_id IS NOT NULL)
            ');
        }

        // Step 2: Verify no unmappable resources remain (resources with null location_id)
        // If this check fails, the migration will roll back
        if (DB::getDriverName() !== 'sqlite') {
            $unmappedCount = DB::selectOne('
                SELECT COUNT(*) as count FROM resources 
                WHERE location_id IS NULL
            ')->count;

            if ($unmappedCount > 0) {
                throw new \Exception("Cannot proceed: {$unmappedCount} resources have null location_id and cannot be mapped from salon.location_id");
            }
        }

        // Step 3: Make location_id required (not nullable)
        Schema::table('resources', function (Blueprint $table) {
            $table->uuid('location_id')->nullable(false)->change();
        });

        // Step 4: Drop salon_id foreign key and column
        Schema::table('resources', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->dropIndex(['salon_id']);
            $table->dropColumn('salon_id');
        });

        // Step 5: Add index for location_id queries
        Schema::table('resources', function (Blueprint $table) {
            $table->index(['location_id', 'is_active']);
        });
    }

    public function down(): void
    {
        // Drop new composite index
        Schema::table('resources', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'is_active']);
        });

        // Add salon_id back
        Schema::table('resources', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable()->after('id');
            
            $table->foreign('salon_id')
                ->references('id')
                ->on('salons')
                ->onDelete('cascade');
                
            $table->index('salon_id');
        });

        // Migrate data back: copy location_id to salon_id via location salon relationship
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                UPDATE resources r
                SET salon_id = (
                    SELECT id FROM salons WHERE location_id = r.location_id
                )
                WHERE location_id IS NOT NULL
                  AND EXISTS (SELECT 1 FROM salons WHERE salons.location_id = r.location_id)
            ');
        }

        // Make location_id nullable again
        Schema::table('resources', function (Blueprint $table) {
            $table->uuid('location_id')->nullable()->change();
        });
    }
};

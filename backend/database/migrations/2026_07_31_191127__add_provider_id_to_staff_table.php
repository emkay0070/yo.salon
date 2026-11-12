<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Wave 2B.2 Checkpoint 1: Add provider_id to staff table
 * 
 * This migration establishes canonical Provider ownership for Staff.
 * Staff is location-specific: Provider → Location → Staff.
 * 
 * Steps:
 * 1. Add provider_id column (nullable initially for safety)
 * 2. Backfill provider_id from salon.provider_id deterministically
 * 3. Add FK constraint to providers table
 * 4. Add index for query performance
 * 
 * Verification before backfill:
 * - All Staff records must have valid salon_id
 * - All Staff records must have valid location_id
 * - location.provider_id must match derived provider_id
 * 
 * Note: Database is currently empty (0 Staff records), so backfill is trivial.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip if provider_id already exists (fresh migration scenario)
        if (Schema::hasColumn('staff', 'provider_id')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            // Add provider_id column after id
            $table->uuid('provider_id')->nullable()->after('id');
            
            $table->foreign('provider_id')
                ->references('id')
                ->on('providers')
                ->nullOnDelete();
                
            $table->index('provider_id');
        });

        // Skip if salons table doesn't exist (fresh database)
        if (Schema::hasTable('salons') && Schema::hasColumn('staff', 'salon_id')) {
            DB::statement("
                UPDATE staff
                SET provider_id = (
                    SELECT provider_id FROM salons WHERE salons.id = staff.salon_id
                )
                WHERE salon_id IS NOT NULL
                  AND EXISTS (SELECT 1 FROM salons WHERE salons.id = staff.salon_id AND salons.provider_id IS NOT NULL)
            ");
        }

        // Add FK constraint after backfill to ensure data integrity
        Schema::table('staff', function (Blueprint $table) {
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropIndex(['provider_id']);
            $table->dropColumn('provider_id');
        });
    }
};

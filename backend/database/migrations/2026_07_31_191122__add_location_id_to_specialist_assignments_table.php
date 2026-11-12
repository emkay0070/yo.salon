<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4: Add location_id to specialist_assignments table
 * 
 * This migration adds a direct location relationship to SpecialistAssignment,
 * enabling location-level resolution without going through Salon.
 * 
 * FK Behavior: cascade (matches salon_id cascade behavior to maintain consistency)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip if location_id already exists (fresh migration scenario)
        if (Schema::hasColumn('specialist_assignments', 'location_id')) {
            return;
        }

        Schema::table('specialist_assignments', function (Blueprint $table) {
            $table->uuid('location_id')->nullable()->after('provider_id');
            
            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->onDelete('cascade');
                
            $table->index('location_id');
        });

        // Skip if salons table doesn't exist (fresh database)
        if (Schema::hasTable('salons') && Schema::hasColumn('specialist_assignments', 'salon_id')) {
            DB::statement("
                UPDATE specialist_assignments
                SET location_id = (
                    SELECT location_id FROM salons WHERE salons.id = specialist_assignments.salon_id
                )
                WHERE salon_id IS NOT NULL
                  AND EXISTS (SELECT 1 FROM salons WHERE salons.id = specialist_assignments.salon_id AND salons.location_id IS NOT NULL)
            ");
        }
    }

    public function down(): void
    {
        Schema::table('specialist_assignments', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['location_id']);
            $table->dropIndex(['location_id', 'status']);
            $table->dropColumn('location_id');
        });
    }
};

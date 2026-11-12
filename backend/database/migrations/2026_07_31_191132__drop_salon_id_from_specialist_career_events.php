<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2B.5: Drop salon_id from specialist_career_events
 * 
 * SpecialistCareerEvent is specialist-global career history with optional workplace context.
 * Canonical workplace context is provided by assignment_id → SpecialistAssignment → Provider/Location.
 * salon_id is legacy and must be removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specialist_career_events', function (Blueprint $table) {
            // Drop salon_id only if it exists (fresh migration scenario)
            if (Schema::hasColumn('specialist_career_events', 'salon_id')) {
                // Drop foreign key constraint first
                $table->dropForeign(['salon_id']);
                // Drop the column
                $table->dropColumn('salon_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('specialist_career_events', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable();
            
            // Skip foreign key if salons table doesn't exist
            if (Schema::hasTable('salons')) {
                $table->foreign('salon_id')
                    ->references('id')
                    ->on('salons')
                    ->nullOnDelete();
            }
            
            // Add index
            $table->index('salon_id');
        });
    }
};

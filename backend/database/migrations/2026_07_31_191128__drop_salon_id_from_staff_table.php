<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2B.2 Checkpoint 3: Drop salon_id from staff table
 * 
 * Staff now uses canonical Provider → Location ownership:
 * - provider_id: Provider ownership
 * - location_id: Physical operating context
 * 
 * salon_id is no longer needed for Staff domain authority.
 * All Staff queries have been migrated to use provider_id + location_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // Drop salon_id only if it exists (fresh migration scenario)
            if (Schema::hasColumn('staff', 'salon_id')) {
                // Drop foreign key constraint first
                $table->dropForeign(['salon_id']);
                // Drop the index
                $table->dropIndex(['salon_id']);
                // Drop the column
                $table->dropColumn('salon_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable();
            
            // Skip foreign key if salons table doesn't exist
            if (Schema::hasTable('salons')) {
                $table->foreign('salon_id')
                    ->references('id')
                    ->on('salons')
                    ->cascadeOnDelete();
            }
            
            // Add index
            $table->index('salon_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2B.3: Drop salon_id from professional_assessments
 * 
 * ProfessionalAssessment now uses canonical Provider → Location context:
 * - provider_id: Business ownership
 * - location_id: Physical occurrence context
 * 
 * salon_id is no longer needed for ProfessionalAssessment domain authority.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_assessments', function (Blueprint $table) {
            // Drop salon_id only if it exists (fresh migration scenario)
            if (Schema::hasColumn('professional_assessments', 'salon_id')) {
                // Drop foreign key constraint first
                $table->dropForeign(['salon_id']);
                // Drop the index
                $table->dropIndex(['salon_id', 'created_at']);
                // Drop the column
                $table->dropColumn('salon_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('professional_assessments', function (Blueprint $table) {
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

            // Note: Data would need to be restored from provider_id + location_id
            // This is a structural rollback only

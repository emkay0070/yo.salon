<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Wave 2B.3: Add provider_id and location_id to professional_assessments
 * 
 * ProfessionalAssessment canonical architecture:
 * - specialist_id: Global professional identity
 * - customer_id: Customer being assessed
 * - provider_id: Business context (from salon.provider_id)
 * - location_id: Physical occurrence context (from salon.location_id)
 * - booking_id: Optional visit context
 * 
 * salon_id will be removed after successful backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip if provider_id and location_id already exist (fresh migration scenario)
        if (Schema::hasColumn('professional_assessments', 'provider_id') && Schema::hasColumn('professional_assessments', 'location_id')) {
            return;
        }

        Schema::table('professional_assessments', function (Blueprint $table) {
            $table->uuid('provider_id')->nullable()->after('id');
            $table->uuid('location_id')->nullable()->after('provider_id');
            
            $table->foreign('provider_id')
                ->references('id')
                ->on('providers')
                ->nullOnDelete();
                
            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
                
            $table->index('provider_id');
            $table->index('location_id');
        });

        // Skip if salons table doesn't exist (fresh database)
        if (Schema::hasTable('salons') && Schema::hasColumn('professional_assessments', 'salon_id')) {
            DB::statement("
                UPDATE professional_assessments
                SET provider_id = (
                    SELECT provider_id FROM salons WHERE salons.id = professional_assessments.salon_id
                ),
                location_id = (
                    SELECT location_id FROM salons WHERE salons.id = professional_assessments.salon_id
                )
                WHERE salon_id IS NOT NULL
                  AND EXISTS (
                      SELECT 1 FROM salons 
                      WHERE salons.id = professional_assessments.salon_id 
                      AND salons.provider_id IS NOT NULL 
                      AND salons.location_id IS NOT NULL
                  )
            ");
        }

        // Verify backfill completed successfully
        $incomplete = DB::table('professional_assessments')
            ->whereNull('provider_id')
            ->orWhereNull('location_id')
            ->count();

        if ($incomplete > 0) {
            throw new \Exception("Backfill incomplete: {$incomplete} assessments have missing provider_id or location_id");
        }

        // Add FK constraints after backfill
        Schema::table('professional_assessments', function (Blueprint $table) {
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
        });

        // Make columns NOT NULL after successful backfill
        Schema::table('professional_assessments', function (Blueprint $table) {
            $table->uuid('provider_id')->nullable(false)->change();
            $table->uuid('location_id')->nullable(false)->change();
        });

        // Add FK constraints
        Schema::table('professional_assessments', function (Blueprint $table) {
            $table->foreign('provider_id')
                ->references('id')
                ->on('providers')
                ->cascadeOnDelete();

            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->cascadeOnDelete();
        });

        // Add indexes for query performance
        Schema::table('professional_assessments', function (Blueprint $table) {
            $table->index(['provider_id', 'created_at']);
            $table->index(['location_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('professional_assessments', function (Blueprint $table) {
            // Drop FK constraints
            $table->dropForeign(['provider_id']);
            $table->dropForeign(['location_id']);
            
            // Drop indexes
            $table->dropIndex(['provider_id', 'created_at']);
            $table->dropIndex(['location_id', 'created_at']);
            
            // Drop columns
            $table->dropColumn(['provider_id', 'location_id']);
        });
    }
};

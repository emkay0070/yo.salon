<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrates Media from Salon-based ownership to Provider-based ownership
     * with optional Location context.
     *
     * Changes:
     * - Remove salon_id (Salon is no longer the authoritative owner)
     * - Add location_id (optional, for physical-location context)
     * - provider_id remains as the canonical business owner
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Add location_id before removing salon_id to allow data migration if needed
            $table->uuid('location_id')->nullable()->after('provider_id');
            $table->index('location_id');
            
            // Foreign key to locations table
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            
            // Remove salon_id (Salon is no longer the authoritative owner)
            // Check if foreign key exists before dropping (for safety)
            if (Schema::hasColumn('media', 'salon_id')) {
                $table->dropForeign(['salon_id']);
                $table->dropColumn('salon_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Re-add salon_id for rollback
            $table->uuid('salon_id')->nullable()->after('provider_id');
            $table->index('salon_id');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            
            // Remove location_id
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};

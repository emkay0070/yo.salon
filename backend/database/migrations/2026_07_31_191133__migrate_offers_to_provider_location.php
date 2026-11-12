<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Wave 2C.1: Migrate Offers from Salon-authoritative to Provider/Location-canonical
 * 
 * Canonical model:
 * - provider_id = required canonical business owner
 * - location_id = nullable physical-context override (provider-wide if null)
 * - salon_id = removed (legacy)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip if location_id already exists (fresh migration scenario)
        if (Schema::hasColumn('offers', 'location_id')) {
            return;
        }

        // Fresh database scenario: just add location_id
        if (!Schema::hasColumn('offers', 'salon_id')) {
            Schema::table('offers', function (Blueprint $table) {
                $table->uuid('location_id')->nullable()->after('provider_id');
                    
                $table->foreign('location_id')
                    ->references('id')
                    ->on('locations')
                    ->nullOnDelete();
            });

            // Add composite indexes
            Schema::table('offers', function (Blueprint $table) {
                $table->index(['location_id', 'active']);
            });
            return;
        }

        // Legacy migration scenario: migrate from salon_id to provider_id/location_id
        Schema::table('offers', function (Blueprint $table) {
            $table->uuid('provider_id')->nullable()->after('id');
            $table->uuid('location_id')->nullable()->after('provider_id');
        });

        // Backfill data from salons table
        if (Schema::hasTable('salons')) {
            DB::statement('
                UPDATE offers o
                SET provider_id = (
                    SELECT provider_id FROM salons WHERE id = o.salon_id
                )
                WHERE salon_id IS NOT NULL
            ');
            
            DB::statement('
                UPDATE offers o
                SET location_id = (
                    SELECT location_id FROM salons WHERE id = o.salon_id
                )
                WHERE salon_id IS NOT NULL
                  AND EXISTS (SELECT 1 FROM salons WHERE salons.id = o.salon_id AND salons.location_id IS NOT NULL)
            ');
        }

        // Add FK constraints
        Schema::table('offers', function (Blueprint $table) {
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
        });

        // Make provider_id not nullable
        Schema::table('offers', function (Blueprint $table) {
            $table->uuid('provider_id')->nullable(false)->change();
        });

        // Drop salon_id foreign key and column
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->dropIndex(['salon_id', 'active']);
            $table->dropColumn('salon_id');
        });

        // Add new composite indexes
        Schema::table('offers', function (Blueprint $table) {
            $table->index(['provider_id', 'active']);
            $table->index(['location_id', 'active']);
        });
    }

    public function down(): void
    {
        // Drop new composite indexes
        Schema::table('offers', function (Blueprint $table) {
            $table->dropIndex(['provider_id', 'active']);
            $table->dropIndex(['location_id', 'active']);
        });

        // Add salon_id back
        Schema::table('offers', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable()->after('id');
            
            $table->foreign('salon_id')
                ->references('id')
                ->on('salons')
                ->onDelete('cascade');
                
            $table->index(['salon_id', 'active']);
        });

        // Migrate data back: copy provider_id to salon_id via provider salon relationship
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                UPDATE offers o
                SET salon_id = (
                    SELECT id FROM salons WHERE provider_id = o.provider_id
                )
                WHERE provider_id IS NOT NULL
            ');
        }

        // Drop provider_id foreign key and column
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropIndex(['provider_id']);
            $table->dropColumn('provider_id');
        });

        // Drop location_id foreign key and column
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['location_id']);
            $table->dropColumn('location_id');
        });

        // Make salon_id not nullable
        Schema::table('offers', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable(false)->change();
        });
    }
};

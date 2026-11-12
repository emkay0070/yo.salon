<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Wave 2C.3: Migrate Bookings from Salon-authoritative to Location-canonical
 * 
 * Canonical model: Provider → Location → Booking
 * - provider_id = required canonical business owner (already NOT NULL)
 * - location_id = required physical location (make NOT NULL)
 * - salon_id = removed (legacy)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip if salons table doesn't exist (fresh database)
        if (!Schema::hasTable('salons') || !Schema::hasColumn('bookings', 'salon_id')) {
            return;
        }

        // Step 1: Backfill location_id from salon.location_id for bookings with null location_id
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                UPDATE bookings b
                SET location_id = (
                    SELECT location_id FROM salons WHERE id = b.salon_id
                )
                WHERE salon_id IS NOT NULL
                  AND location_id IS NULL
                  AND EXISTS (SELECT 1 FROM salons WHERE salons.id = b.salon_id AND salons.location_id IS NOT NULL)
            ');
        }

        // Step 2: Verify no unmappable bookings remain (bookings with null location_id)
        if (DB::getDriverName() !== 'sqlite') {
            $unmappedCount = DB::selectOne('
                SELECT COUNT(*) as count FROM bookings 
                WHERE location_id IS NULL
            ')->count;

            if ($unmappedCount > 0) {
                throw new \Exception("Cannot proceed: {$unmappedCount} bookings have null location_id and cannot be mapped from salon.location_id");
            }
        }

        // Step 3: Verify provider_id consistency (booking.provider_id === location.provider_id)
        if (DB::getDriverName() !== 'sqlite') {
            $inconsistentCount = DB::selectOne('
                SELECT COUNT(*) as count FROM bookings b
                JOIN locations l ON b.location_id = l.id
                WHERE b.provider_id != l.provider_id
            ')->count;

            if ($inconsistentCount > 0) {
                throw new \Exception("Cannot proceed: {$inconsistentCount} bookings have inconsistent provider_id vs location.provider_id");
            }
        }

        // Step 4: Make location_id required (not nullable)
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('location_id')->nullable(false)->change();
        });

        // Step 5: Drop salon_id foreign key and column
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->dropIndex(['salon_id']);
            $table->dropColumn('salon_id');
        });

        // Step 6: Add index for provider_id and location_id queries
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['provider_id', 'location_id']);
        });
    }

    public function down(): void
    {
        // Drop new composite index
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['provider_id', 'location_id']);
        });

        // Add salon_id back only if salons table exists
        if (Schema::hasTable('salons')) {
            Schema::table('bookings', function (Blueprint $table) {
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
                    UPDATE bookings b
                    SET salon_id = (
                        SELECT id FROM salons WHERE location_id = b.location_id
                    )
                    WHERE location_id IS NOT NULL
                      AND EXISTS (SELECT 1 FROM salons WHERE salons.location_id = b.location_id)
                ');
            }
        }

        // Make location_id nullable again
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('location_id')->nullable()->change();
        });
    }
};

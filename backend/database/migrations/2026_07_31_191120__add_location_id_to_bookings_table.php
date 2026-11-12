<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if location_id already exists (fresh migration scenario)
        if (Schema::hasColumn('bookings', 'location_id')) {
            return;
        }

        // Add location_id column after provider_id
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('location_id')->nullable()->after('provider_id');
            
            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->onDelete('cascade');
                
            $table->index('location_id');
        });

        // Backfill existing booking data: booking.salon_id → salon.location_id → booking.location_id
        // Skip if salons table doesn't exist (fresh database)
        if (Schema::hasTable('salons') && Schema::hasColumn('bookings', 'salon_id')) {
            DB::statement("
                UPDATE bookings
                SET location_id = (
                    SELECT location_id FROM salons WHERE salons.id = bookings.salon_id
                )
                WHERE salon_id IS NOT NULL
                  AND EXISTS (SELECT 1 FROM salons WHERE salons.id = bookings.salon_id AND salons.location_id IS NOT NULL)
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop location_id column
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};

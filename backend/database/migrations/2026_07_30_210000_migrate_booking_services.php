<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing bookings to have services in pivot table
        DB::statement("
            INSERT INTO booking_service (booking_id, service_id, created_at, updated_at)
            SELECT id, service_id, created_at, updated_at 
            FROM bookings 
            WHERE service_id IS NOT NULL 
            AND id NOT IN (SELECT DISTINCT booking_id FROM booking_service)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the migrated entries
        DB::statement("
            DELETE FROM booking_service 
            WHERE booking_id IN (
                SELECT id FROM bookings WHERE service_id IS NOT NULL
            )
        ");
    }
};

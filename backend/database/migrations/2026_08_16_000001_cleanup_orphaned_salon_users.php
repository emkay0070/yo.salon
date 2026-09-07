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
        // Clean up orphaned salon_users records where the salon doesn't exist
        // PostgreSQL-compatible syntax
        $deleted = DB::delete('
            DELETE FROM salon_users
            WHERE salon_id NOT IN (SELECT id FROM salons)
        ');

        // Log how many orphaned records were cleaned up
        if ($deleted > 0) {
            \Log::info("Cleaned up {$deleted} orphaned salon_users records");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse this migration as we don't have the original data
        // This is a one-time cleanup operation
    }
};

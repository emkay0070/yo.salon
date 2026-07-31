<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clear existing tokens since they have integer IDs that can't be cast to UUID
        DB::table('personal_access_tokens')->delete();

        // Use raw SQL for PostgreSQL with proper casting
        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE UUID USING tokenable_id::text::uuid');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to bigint
        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE BIGINT USING tokenable_id::text::bigint');
    }
};

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
        // Change tokenable_id from UUID to VARCHAR(36) so it can store both
        if (DB::getDriverName() !== 'sqlite') {
            // Cast to text which is compatible with varchar
            DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE VARCHAR(36) USING tokenable_id::text');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Change tokenable_id back to UUID from VARCHAR(36)
            DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE UUID USING tokenable_id::uuid');
        }
    }
};

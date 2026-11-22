<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 5: Drop the legacy `type` column from `providers` table now that
     * `provider_type_id` is the single authoritative classification source.
     */
    public function up(): void
    {
        if (Schema::hasTable('providers') && Schema::hasColumn('providers', 'type')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('providers') && !Schema::hasColumn('providers', 'type')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->string('type')->nullable()->after('id');
                $table->index('type');
            });

            // Backfill legacy column from provider_types.key
            if (Schema::hasTable('provider_types')) {
                DB::statement("
                    UPDATE providers p
                    SET type = pt.key
                    FROM provider_types pt
                    WHERE p.provider_type_id = pt.id
                ");
            }
        }
    }
};

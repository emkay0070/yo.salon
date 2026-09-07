<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            if (!Schema::hasColumn('salons', 'schedule_mode')) {
                // schedule_mode: INHERIT (inherits from provider), CUSTOM
                $table->string('schedule_mode')->default('INHERIT');
            }
        });

        Schema::table('specialist_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('specialist_assignments', 'schedule_mode')) {
                // schedule_mode: INHERIT (inherits from branch), CUSTOM
                $table->string('schedule_mode')->default('INHERIT');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            if (Schema::hasColumn('salons', 'schedule_mode')) {
                $table->dropColumn('schedule_mode');
            }
        });

        Schema::table('specialist_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('specialist_assignments', 'schedule_mode')) {
                $table->dropColumn('schedule_mode');
            }
        });
    }
};

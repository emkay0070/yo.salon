<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make location.address nullable to support coordinate-only locations.
     * 
     * Architectural decision: A Location can be valid with lat/lng coordinates
     * even without a human-readable street address (e.g., map-selected locations).
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
        });
    }
};

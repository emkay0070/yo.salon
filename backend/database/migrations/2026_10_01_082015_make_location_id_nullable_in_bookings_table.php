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
        Schema::table('bookings', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['location_id']);
            // Make location_id nullable
            $table->uuid('location_id')->nullable()->change();
            // Re-add foreign key constraint
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['location_id']);
            // Make location_id not nullable
            $table->uuid('location_id')->nullable(false)->change();
            // Re-add foreign key constraint
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
        });
    }
};

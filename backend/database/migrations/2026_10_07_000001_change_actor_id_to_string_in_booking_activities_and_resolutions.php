<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change actor_id from UUID to string to support both integer user IDs
     * and UUID entity IDs (staff, specialists, customers, providers).
     *
     * This preserves actor identity for all actor types in audit history.
     */
    public function up(): void
    {
        Schema::table('booking_activities', function (Blueprint $table) {
            $table->string('actor_id')->nullable()->change();
        });

        Schema::table('booking_resolutions', function (Blueprint $table) {
            $table->string('actor_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_activities', function (Blueprint $table) {
            $table->uuid('actor_id')->nullable()->change();
        });

        Schema::table('booking_resolutions', function (Blueprint $table) {
            $table->uuid('actor_id')->nullable()->change();
        });
    }
};

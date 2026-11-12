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
        Schema::table('booking_service', function (Blueprint $table) {
            // Drop existing composite primary key
            $table->dropPrimary(['booking_id', 'service_id']);
            
            // Add id as UUID primary key
            $table->uuid('id')->first()->primary();
            
            // Add timing fields
            $table->timestamp('start_time')->nullable()->after('service_id');
            $table->timestamp('end_time')->nullable()->after('start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_service', function (Blueprint $table) {
            $table->dropColumn(['id', 'start_time', 'end_time']);
            // Restore composite primary key
            $table->primary(['booking_id', 'service_id']);
        });
    }
};

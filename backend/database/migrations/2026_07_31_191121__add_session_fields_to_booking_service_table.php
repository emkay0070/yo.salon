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
            // Session operational state fields
            $table->timestamp('started_at')->nullable()->after('end_time');
            $table->timestamp('actual_end_at')->nullable()->after('started_at');
            $table->string('session_status')->default('scheduled')->after('actual_end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_service', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'actual_end_at', 'session_status']);
        });
    }
};

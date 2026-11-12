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
        Schema::table('locations', function (Blueprint $table) {
            $table->text('description')->nullable()->after('email');
            $table->boolean('is_open')->default(true)->after('is_active');
            $table->boolean('accepting_online_bookings')->default(true)->after('is_open');
            $table->text('parking_info')->nullable()->after('accepting_online_bookings');
            $table->string('schedule_mode')->nullable()->after('parking_info');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_open', 'accepting_online_bookings', 'parking_info', 'schedule_mode']);
        });
    }
};

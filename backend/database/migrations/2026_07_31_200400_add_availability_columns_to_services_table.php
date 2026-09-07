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
        Schema::table('services', function (Blueprint $table) {
            $table->integer('buffer_before')->default(0)->after('duration');
            $table->integer('buffer_after')->default(0)->after('buffer_before');
            $table->boolean('requires_specialist')->default(true)->after('buffer_after');
            $table->integer('max_concurrent')->default(1)->after('requires_specialist');
            $table->integer('min_booking_notice')->default(2)->after('max_concurrent'); // hours
            $table->integer('max_booking_days_ahead')->default(30)->after('min_booking_notice');
            $table->integer('cancellation_cutoff_hours')->default(24)->after('max_booking_days_ahead');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'buffer_before',
                'buffer_after',
                'requires_specialist',
                'max_concurrent',
                'min_booking_notice',
                'max_booking_days_ahead',
                'cancellation_cutoff_hours'
            ]);
        });
    }
};

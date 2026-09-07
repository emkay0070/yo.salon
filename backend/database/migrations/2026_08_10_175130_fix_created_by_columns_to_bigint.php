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
        // Fix schedule_exceptions.created_by from UUID to BIGINT
        Schema::table('schedule_exceptions', function (Blueprint $table) {
            // Drop existing UUID column (no data to preserve based on check)
            $table->dropColumn('created_by');
        });

        Schema::table('schedule_exceptions', function (Blueprint $table) {
            // Add as BIGINT with foreign key
            $table->unsignedBigInteger('created_by')->nullable()->after('recurring_yearly');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // Add created_by to salon_schedules (it doesn't exist yet)
        Schema::table('salon_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('published_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_exceptions', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });

        Schema::table('schedule_exceptions', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('recurring_yearly');
        });

        Schema::table('salon_schedules', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};

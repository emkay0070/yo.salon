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
            $table->unsignedBigInteger('started_by_user_id')->nullable()->after('started_at');
            $table->unsignedBigInteger('completed_by_user_id')->nullable()->after('actual_end_at');

            $table->foreign('started_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_service', function (Blueprint $table) {
            $table->dropForeign(['started_by_user_id']);
            $table->dropForeign(['completed_by_user_id']);
            $table->dropColumn(['started_by_user_id', 'completed_by_user_id']);
        });
    }
};

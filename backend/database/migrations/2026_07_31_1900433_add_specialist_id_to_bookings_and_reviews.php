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
            $table->uuid('specialist_id')->nullable()->after('staff_id');
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('set null');
            $table->index('specialist_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->uuid('specialist_id')->nullable()->after('staff_id');
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('set null');
            $table->index('specialist_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['specialist_id']);
            $table->dropIndex(['specialist_id']);
            $table->dropColumn('specialist_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['specialist_id']);
            $table->dropIndex(['specialist_id']);
            $table->dropColumn('specialist_id');
        });
    }
};

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
            $table->uuid('salon_id')->nullable()->after('id');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->index('salon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->dropIndex(['salon_id']);
            $table->dropColumn('salon_id');
        });
    }
};

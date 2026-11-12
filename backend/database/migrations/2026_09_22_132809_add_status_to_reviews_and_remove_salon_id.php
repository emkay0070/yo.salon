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
        Schema::table('reviews', function (Blueprint $table) {
            // Add status field with default 'pending'
            $table->enum('status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending')->after('comment');
            $table->index('status');

            // Remove salon_id if it exists
            if (Schema::hasColumn('reviews', 'salon_id')) {
                $table->dropForeign(['salon_id']);
                $table->dropIndex(['salon_id']);
                $table->dropColumn('salon_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');

            // Add salon_id back for rollback
            $table->uuid('salon_id')->nullable()->after('provider_id');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->index('salon_id');
        });
    }
};

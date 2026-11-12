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
        Schema::table('intelligence_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('intelligence_runs', 'location_id')) {
                $table->uuid('location_id')->nullable();
                $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
                $table->index('location_id');
                $table->index(['provider_id', 'location_id', 'status']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intelligence_runs', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropIndex('location_id');
            $table->dropIndex(['provider_id', 'location_id', 'status']);
            $table->dropColumn('location_id');
        });
    }
};

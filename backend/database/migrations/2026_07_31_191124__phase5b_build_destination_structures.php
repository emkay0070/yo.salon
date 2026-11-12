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
        // 1. Create LocationSchedule
        Schema::create('location_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('location_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week'); // Monday, Tuesday, etc.
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['location_id', 'day_of_week']);
        });

        // 2. Add provider_id to BrandExperience (skip if already exists in fresh migration scenario)
        if (!Schema::hasColumn('brand_experiences', 'provider_id')) {
            Schema::table('brand_experiences', function (Blueprint $table) {
                $table->uuid('provider_id')->nullable()->after('id');
                
                // Allow salon_id to be nullable for now before we drop it in the next migration
                if (Schema::hasColumn('brand_experiences', 'salon_id')) {
                    $table->uuid('salon_id')->nullable()->change();
                }
                
                $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
                $table->unique('provider_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_experiences', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropColumn('provider_id');
            // We cannot easily revert salon_id to non-nullable safely if there are null records, 
            // but in a fresh DB it's fine.
            $table->uuid('salon_id')->nullable(false)->change();
        });

        Schema::dropIfExists('location_schedules');
    }
};

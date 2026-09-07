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
        Schema::table('salons', function (Blueprint $table) {
            if (!Schema::hasColumn('salons', 'category')) {
                $table->string('category')->nullable()->after('city');
            }
            if (!Schema::hasColumn('salons', 'vibe')) {
                $table->string('vibe')->nullable()->after('category');
            }
            if (!Schema::hasColumn('salons', 'business_type')) {
                $table->string('business_type')->nullable()->after('vibe');
            }
            if (!Schema::hasColumn('salons', 'team_size')) {
                $table->string('team_size')->nullable()->after('business_type');
            }
            if (!Schema::hasColumn('salons', 'branches')) {
                $table->string('branches')->nullable()->after('team_size');
            }
            if (!Schema::hasColumn('salons', 'timezone')) {
                $table->string('timezone')->nullable()->default('Africa/Kampala')->after('branches');
            }
            if (!Schema::hasColumn('salons', 'currency')) {
                $table->string('currency')->nullable()->default('UGX')->after('timezone');
            }
            if (!Schema::hasColumn('salons', 'opening_hours')) {
                $table->json('opening_hours')->nullable()->after('currency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn(['category', 'vibe', 'business_type', 'team_size', 'branches', 'timezone', 'currency', 'opening_hours']);
        });
    }
};

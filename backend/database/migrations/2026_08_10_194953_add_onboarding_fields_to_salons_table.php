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
            $table->string('category')->nullable()->after('city');
            $table->string('vibe')->nullable()->after('category');
            $table->string('business_type')->nullable()->after('vibe');
            $table->string('team_size')->nullable()->after('business_type');
            $table->string('branches')->nullable()->after('team_size');
            $table->string('timezone')->nullable()->default('Africa/Kampala')->after('branches');
            $table->string('currency')->nullable()->default('UGX')->after('timezone');
            $table->json('opening_hours')->nullable()->after('currency');
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

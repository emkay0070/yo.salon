<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links plans to the resources they include and at what limits.
     * Replaces hardcoded columns on the plans table (staff_limit, branches_limit, etc.)
     * with a flexible, configurable entitlement system.
     *
     * Example:
     *   Professional Plan → SMS → 1000 per month
     *   Professional Plan → AI_REQUEST → 500 per month
     *   Professional Plan → STAFF_SEAT → -1 (unlimited)
     */
    public function up(): void
    {
        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('resource_code');        // FK ref to billing_resources.code
            $table->integer('limit')->default(0);   // -1 = unlimited, 0 = not included
            $table->string('reset_period')->default('monthly'); // 'monthly', 'yearly', 'never'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->onDelete('cascade');

            $table->unique(['plan_id', 'resource_code'], 'plan_resource_unique');
            $table->index('plan_id');
            $table->index('resource_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_entitlements');
    }
};

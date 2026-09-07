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
        Schema::create('specialist_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            $table->string('goal_type'); // DAILY_REVENUE, MONTHLY_REVENUE, CLIENTS, REBOOKING, RATING
            $table->decimal('target_value', 10, 2);
            $table->string('metric')->nullable();
            $table->string('period_type')->default('monthly'); // daily, weekly, monthly, quarterly, yearly
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('active'); // active, paused, achieved, abandoned
            
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->index(['specialist_id', 'status']);
            $table->index('goal_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialist_goals');
    }
};

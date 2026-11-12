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
        if (Schema::hasTable('intelligence_insights')) {
            return;
        }

        Schema::create('intelligence_insights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->uuid('provider_id');
            $table->string('type'); // churn_risk, low_demand, high_fees, demand_spike, etc.
            $table->string('severity')->default('medium'); // critical, high, medium, low
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('evidence')->nullable();
            $table->decimal('confidence', 3, 2)->default(0);
            $table->string('recommended_action')->nullable();
            $table->string('deep_link')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->boolean('is_predictive')->default(false);
            $table->timestamp('dismissed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('intelligence_runs')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->index(['run_id', 'severity']);
            $table->index(['provider_id', 'is_dismissed']);
            $table->index(['provider_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_insights');
    }
};

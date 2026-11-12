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
        if (Schema::hasTable('intelligence_forecasts')) {
            return;
        }

        Schema::create('intelligence_forecasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->uuid('provider_id');
            $table->string('forecast_type'); // monthly_revenue, demand_spike, churn_risk
            $table->string('period'); // daily, weekly, monthly
            $table->date('forecast_date');
            $table->decimal('forecast_value', 15, 2)->nullable();
            $table->decimal('confidence', 3, 2)->default(0);
            $table->json('forecast_breakdown')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('intelligence_runs')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->index(['run_id', 'forecast_type']);
            $table->index(['provider_id', 'forecast_date']);
            $table->index(['provider_id', 'forecast_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_forecasts');
    }
};

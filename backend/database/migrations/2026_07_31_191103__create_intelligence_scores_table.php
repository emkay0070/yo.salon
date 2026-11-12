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
        if (Schema::hasTable('intelligence_scores')) {
            return;
        }

        Schema::create('intelligence_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->uuid('provider_id');
            $table->string('subject_type'); // specialist, customer, service, salon
            $table->uuid('subject_id')->nullable();
            $table->string('score_type'); // intelligence, performance, churn_risk, demand
            $table->integer('score')->nullable();
            $table->decimal('confidence', 3, 2)->default(0);
            $table->integer('sample_size')->default(0);
            $table->string('status')->default('insufficient_data'); // insufficient_data, low, medium, high
            $table->json('score_breakdown')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('intelligence_runs')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->index(['run_id', 'subject_type', 'subject_id']);
            $table->index(['provider_id', 'score_type']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_scores');
    }
};

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
        Schema::create('journey_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            $table->uuid('specialist_assignment_id')->nullable();
            $table->enum('scope', ['SPECIALIST', 'WORKPLACE'])->default('SPECIALIST');
            $table->string('metric_key');
            $table->decimal('value', 15, 2)->default(0);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
            $table->index(['specialist_id', 'scope', 'metric_key', 'recorded_at']);
            $table->index(['specialist_assignment_id', 'metric_key', 'recorded_at']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_metrics');
    }
};

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
        if (Schema::hasTable('intelligence_runs')) {
            return;
        }

        Schema::create('intelligence_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->string('analysis_type')->default('comprehensive');
            $table->unsignedTinyInteger('schema_version')->default(1);
            $table->string('algorithm_version')->default('v1');
            $table->timestamp('generated_at');
            $table->timestamp('data_through');
            $table->string('status')->default('completed');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->decimal('confidence', 4, 2)->default(0.00);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->index('provider_id');
            $table->index(['provider_id', 'status']);
            $table->index(['provider_id', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intelligence_runs');
    }
};

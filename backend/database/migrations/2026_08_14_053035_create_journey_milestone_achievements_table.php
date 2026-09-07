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
        Schema::create('journey_milestone_achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            $table->uuid('milestone_definition_id');
            $table->uuid('specialist_assignment_id')->nullable();
            $table->timestamp('achieved_at');
            $table->json('context_data')->nullable();
            $table->enum('verification_method', ['AUTO', 'MANUAL'])->default('AUTO');
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->foreign('milestone_definition_id')->references('id')->on('journey_milestone_definitions')->onDelete('cascade');
            $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
            
            // Unique constraint for idempotency
            $table->unique(['specialist_id', 'milestone_definition_id', 'specialist_assignment_id'], 'unique_achievement');
            $table->index(['specialist_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_milestone_achievements');
    }
};

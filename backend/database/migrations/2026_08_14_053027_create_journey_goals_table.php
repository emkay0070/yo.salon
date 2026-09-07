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
        Schema::create('journey_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            $table->uuid('specialist_assignment_id')->nullable();
            $table->enum('scope', ['SPECIALIST', 'WORKPLACE'])->default('SPECIALIST');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('goal_type', ['NUMERIC', 'QUALITATIVE']);
            $table->string('metric_key')->nullable();
            $table->decimal('target_value', 15, 2)->nullable();
            $table->decimal('current_value', 15, 2)->nullable();
            $table->string('unit')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->enum('status', ['DRAFT', 'ACTIVE', 'PAUSED', 'COMPLETED', 'FAILED', 'CANCELLED'])->default('DRAFT');
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])->default('MEDIUM');
            $table->enum('created_by', ['SPECIALIST', 'SYSTEM'])->default('SPECIALIST');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
            $table->index(['specialist_id', 'status']);
            $table->index(['specialist_assignment_id']);
            $table->index(['metric_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_goals');
    }
};

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
        Schema::create('journey_growth_opportunities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            $table->uuid('specialist_assignment_id')->nullable();
            $table->enum('scope', ['SPECIALIST', 'WORKPLACE'])->default('SPECIALIST');
            $table->string('rule_key')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('opportunity_type', ['RETENTION', 'AVAILABILITY', 'CRAFT_EXPANSION', 'PRICING', 'MARKETING', 'SKILL_DEVELOPMENT']);
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])->default('MEDIUM');
            $table->enum('source', ['RULE', 'AI', 'SYSTEM'])->default('RULE');
            $table->json('evidence')->nullable();
            $table->text('action_suggestion')->nullable();
            $table->json('estimated_impact')->nullable();
            $table->enum('status', ['SUGGESTED', 'DISMISSED', 'ACCEPTED', 'IN_PROGRESS', 'COMPLETED', 'EXPIRED'])->default('SUGGESTED');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->foreign('specialist_assignment_id')->references('id')->on('specialist_assignments')->onDelete('cascade');
            
            // Unique constraint on rule_key to prevent duplicate opportunities from same rule
            $table->unique(['specialist_id', 'rule_key', 'specialist_assignment_id'], 'unique_opportunity');
            $table->index(['specialist_id', 'status']);
            $table->index(['specialist_assignment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_growth_opportunities');
    }
};

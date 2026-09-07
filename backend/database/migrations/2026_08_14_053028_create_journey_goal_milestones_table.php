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
        Schema::create('journey_goal_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('goal_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('criteria');
            $table->uuid('craft_taxonomy_id')->nullable();
            $table->integer('order')->default(0);
            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'SKIPPED'])->default('PENDING');
            $table->timestamp('achieved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('goal_id')->references('id')->on('journey_goals')->onDelete('cascade');
            $table->foreign('craft_taxonomy_id')->references('id')->on('craft_taxonomy')->onDelete('set null');
            $table->index(['goal_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_goal_milestones');
    }
};

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
        Schema::create('specialist_service', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            $table->uuid('service_id');
            $table->enum('skill_level', ['beginner', 'intermediate', 'expert'])->default('intermediate');
            $table->boolean('is_primary')->default(false);
            $table->decimal('price_override', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->unique(['specialist_id', 'service_id']);
            $table->index('specialist_id');
            $table->index('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialist_service');
    }
};

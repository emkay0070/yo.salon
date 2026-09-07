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
        Schema::create('journey_milestone_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('icon')->nullable();
            $table->enum('category', ['CLIENT', 'SERVICE', 'REPUTATION', 'CRAFT', 'BUSINESS']);
            $table->enum('tier', ['BRONZE', 'SILVER', 'GOLD', 'PLATINUM'])->default('BRONZE');
            $table->json('trigger_criteria');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['code']);
            $table->index(['category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_milestone_definitions');
    }
};

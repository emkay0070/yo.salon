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
        Schema::create('specialist_expertise', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            $table->string('name');
            $table->enum('skill_level', ['beginner', 'intermediate', 'advanced', 'expert']);
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->index('specialist_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialist_expertise');
    }
};

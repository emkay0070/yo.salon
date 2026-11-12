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
        Schema::create('profession_specialist', function (Blueprint $table) {
            $table->id();
            $table->uuid('specialist_id');
            $table->uuid('profession_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->foreign('profession_id')->references('id')->on('professions')->onDelete('cascade');
            
            $table->unique(['specialist_id', 'profession_id']);
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profession_specialist');
    }
};

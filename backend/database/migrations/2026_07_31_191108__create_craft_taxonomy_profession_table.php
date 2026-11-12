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
        Schema::create('craft_taxonomy_profession', function (Blueprint $table) {
            $table->id();
            $table->uuid('profession_id');
            $table->uuid('craft_taxonomy_id');
            $table->timestamps();
            
            $table->foreign('profession_id')->references('id')->on('professions')->onDelete('cascade');
            $table->foreign('craft_taxonomy_id')->references('id')->on('craft_taxonomy')->onDelete('cascade');
            
            $table->unique(['profession_id', 'craft_taxonomy_id'], 'prof_craft_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('craft_taxonomy_profession');
    }
};

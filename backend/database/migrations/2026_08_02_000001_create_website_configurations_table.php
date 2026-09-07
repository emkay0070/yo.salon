<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('configurable'); // salon or provider
            $table->json('enabled_sections')->default('["hero","services","team","contact"]');
            $table->json('section_order')->default('["hero","services","team","contact"]');
            $table->string('theme')->default('luxury_noir');
            $table->json('custom_colors')->nullable();
            $table->json('custom_fonts')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_configurations');
    }
};

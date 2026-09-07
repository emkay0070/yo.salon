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
        Schema::create('seeder_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('seeder_class')->unique();
            $table->string('display_name');
            $table->string('description')->nullable();
            $table->string('category')->default('general')->index();
            $table->boolean('is_seeded')->default(false);
            $table->timestamp('last_seeded_at')->nullable();
            $table->string('last_seeded_by')->nullable();
            $table->integer('records_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_seeded']);
            $table->index(['is_required', 'is_seeded']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seeder_statuses');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_breaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('specialist_id')->constrained('specialists')->cascadeOnDelete();
            
            $table->string('started_by_actor_type')->nullable();
            $table->string('started_by_actor_id')->nullable();
            
            $table->timestamp('started_at');
            $table->timestamp('expected_end_at');
            
            $table->timestamp('actual_end_at')->nullable();
            $table->string('ended_by_actor_type')->nullable();
            $table->string('ended_by_actor_id')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_breaks');
    }
};

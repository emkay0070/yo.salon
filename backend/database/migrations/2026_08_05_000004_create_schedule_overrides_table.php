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
        Schema::create('schedule_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->references('id')->on('specialist_assignments')->cascadeOnDelete();
            
            $table->date('date');
            
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            
            $table->boolean('is_working_day')->default(true);
            $table->string('reason')->nullable();
            
            $table->timestamps();

            $table->unique(['assignment_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_overrides');
    }
};

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
        Schema::create('specialist_time_off', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->references('id')->on('specialist_assignments')->cascadeOnDelete();
            
            $table->date('date');
            
            $table->time('start_time')->nullable(); // If null, means whole day
            $table->time('end_time')->nullable();
            
            $table->string('reason')->nullable(); // Sick, Annual Leave, Training, etc.
            $table->string('status')->default('APPROVED'); // PENDING, APPROVED, REJECTED, CANCELLED
            
            $table->timestamps();

            $table->index(['assignment_id', 'date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialist_time_off');
    }
};

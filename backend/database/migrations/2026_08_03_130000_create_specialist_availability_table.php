<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_windows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->references('id')->on('specialist_assignments')->cascadeOnDelete();
            
            $table->date('date');
            
            $table->time('starts_at');
            $table->time('ends_at');
            
            $table->integer('version')->default(1);
            $table->string('status')->default('AVAILABLE'); // AVAILABLE, BOOKED, BLOCKED, BREAK, LEAVE, HELD
            
            $table->timestamps();
            
            $table->index(['assignment_id', 'date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_windows');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assignment_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->references('id')->on('specialist_assignments')->cascadeOnDelete();
            
            $table->string('day_of_week'); // Monday, Tuesday, etc.
            
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            
            $table->boolean('is_working_day')->default(true);
            
            if (DB::getDriverName() === 'sqlite') {
                $table->string('status')->default('ACTIVE');
            } else {
                $table->enum('status', ['DRAFT', 'ACTIVE'])->default('ACTIVE');
            }
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            
            $table->timestamps();

            $table->unique(['assignment_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_schedules');
    }
};

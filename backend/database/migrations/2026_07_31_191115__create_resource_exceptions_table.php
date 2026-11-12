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
        Schema::create('resource_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('resource_id');
            $table->date('date')->nullable();
            $table->string('day_of_week')->nullable();
            $table->string('exception_type'); // 'closed', 'maintenance', 'blocked'
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
            $table->index('resource_id');
            $table->index('date');
            $table->index('day_of_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_exceptions');
    }
};

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
        Schema::create('provider_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->integer('day_of_week'); // 0-6 (Sunday-Saturday)
            $table->time('open_time');
            $table->time('close_time');
            $table->boolean('is_closed')->default(false);
            $table->date('effective_date')->default(now()->toDateString());
            $table->date('expires_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->index(['provider_id', 'day_of_week', 'effective_date']);
            $table->index(['provider_id', 'effective_date', 'expires_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_schedules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            $table->string('type'); // booking, review, follow, achievement, service_added
            $table->json('data')->nullable();
            $table->timestamp('created_at');

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->index(['specialist_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_activities');
    }
};

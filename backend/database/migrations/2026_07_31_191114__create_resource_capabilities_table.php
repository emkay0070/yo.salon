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
        Schema::create('resource_capabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('resource_id');
            $table->string('capability');
            $table->string('level')->nullable();
            $table->timestamps();

            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
            $table->index('resource_id');
            $table->index('capability');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_capabilities');
    }
};

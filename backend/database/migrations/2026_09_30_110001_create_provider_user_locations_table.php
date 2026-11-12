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
        Schema::create('provider_user_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_user_id');
            $table->uuid('location_id');
            $table->timestamps();

            $table->foreign('provider_user_id')
                ->references('id')
                ->on('provider_users')
                ->onDelete('cascade');

            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->onDelete('cascade');

            $table->unique(['provider_user_id', 'location_id']);
            $table->index('provider_user_id');
            $table->index('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_user_locations');
    }
};

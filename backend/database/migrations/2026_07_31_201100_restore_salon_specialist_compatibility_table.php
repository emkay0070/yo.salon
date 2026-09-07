<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restore the legacy `salon_specialist` pivot table.
 * 
 * This table supports existing controllers and services that still reference it.
 * The new `specialist_assignments` table (201000) powers the Availability Engine.
 * Controllers will be migrated to use `specialist_assignments` incrementally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_specialist', function (Blueprint $table) {
            $table->foreignUuid('specialist_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('salon_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('SPECIALIST');
            $table->json('availability')->nullable();
            $table->json('services')->nullable();
            $table->boolean('active')->default(true);
            $table->primary(['specialist_id', 'salon_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_specialist');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1A: Repoint specialist_notes.specialist_id → specialists.id
 *
 * Same reasoning as for professional_assessments.
 * A specialist note is authored by the global professional.
 * specialist_id must reference specialists.id, not staff.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specialist_notes', function (Blueprint $table) {
            // Drop old FK constraint that pointed to staff.id
            $table->dropForeign(['specialist_id']);

            // Re-add FK pointing to specialists.id
            $table->foreign('specialist_id')
                  ->references('id')
                  ->on('specialists')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('specialist_notes', function (Blueprint $table) {
            $table->dropForeign(['specialist_id']);
            $table->foreign('specialist_id')
                  ->references('id')
                  ->on('staff')
                  ->onDelete('cascade');
        });
    }
};

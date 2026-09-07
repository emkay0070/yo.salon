<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1A: Repoint professional_assessments.specialist_id → specialists.id
 *
 * Previously, this FK pointed to staff.id (the salon-local identity).
 * An assessment belongs to the global professional, not the local employee.
 * Therefore specialist_id must reference specialists.id exclusively.
 *
 * Data safety: Since staff.id === specialist.id for all existing V1 records
 * (the shared UUID invariant was deliberately maintained), this re-pointing
 * does not require a data backfill — the UUID values are already correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_assessments', function (Blueprint $table) {
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
        Schema::table('professional_assessments', function (Blueprint $table) {
            $table->dropForeign(['specialist_id']);
            $table->foreign('specialist_id')
                  ->references('id')
                  ->on('staff')
                  ->onDelete('cascade');
        });
    }
};

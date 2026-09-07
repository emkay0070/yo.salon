<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1A: Add staff_id (nullable) to specialist_assignments.
 *
 * Architecture Intent:
 *   SpecialistAssignment is the ONLY bridge between a global Specialist identity
 *   and a salon-local Staff identity. specialist_id → specialists.id exclusively.
 *   staff_id → staff.id exclusively.
 *
 * This migration is intentionally staged:
 *   - staff_id is nullable here so we can backfill before enforcing NOT NULL.
 *   - A subsequent migration (phase 1D) will enforce NOT NULL once backfill is verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specialist_assignments', function (Blueprint $table) {
            // Add nullable staff_id — NOT NULL will be enforced after backfill verification
            $table->uuid('staff_id')->nullable()->after('salon_id');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('set null');

            // Unique constraint: one specialist per salon (multi-salon allowed across salons)
            $table->unique(['specialist_id', 'salon_id'], 'uq_specialist_salon_assignment');
        });
    }

    public function down(): void
    {
        Schema::table('specialist_assignments', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropUnique('uq_specialist_salon_assignment');
            $table->dropColumn('staff_id');
        });
    }
};

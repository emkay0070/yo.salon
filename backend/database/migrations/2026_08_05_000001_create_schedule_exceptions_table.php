<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schedule Exceptions
 *
 * Separates one-off / temporary changes (holidays, vacation, training, closures)
 * from the recurring weekly schedules (provider_schedule, salon_schedule, assignment_schedule).
 *
 * Hierarchical: an exception can target any of the 3 layers:
 *   - provider  → applies to ALL branches of this business
 *   - salon     → applies only to one branch
 *   - assignment → applies only to one specialist at one branch
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ── Scope / hierarchy ──────────────────────────────────────────────
            $table->enum('scope_level', ['provider', 'salon', 'assignment'])->default('salon');
            $table->uuid('provider_id')->nullable()->index();
            $table->uuid('salon_id')->nullable()->index();
            $table->uuid('assignment_id')->nullable()->index();

            // ── Exception details ─────────────────────────────────────────────
            $table->date('start_date');
            $table->date('end_date')->nullable();   // NULL = single-day exception

            $table->string('title');                // e.g. "Christmas Day", "Annual Leave", "Power Outage"
            $table->text('notes')->nullable();

            // ── Reason / type ─────────────────────────────────────────────────
            $table->enum('type', [
                'HOLIDAY',          // Public / company-wide holiday
                'VACATION',         // Specialist / owner personal leave
                'TRAINING',         // Training day / team building
                'CLOSURE',          // Temporary business closure (renovation, etc.)
                'POWER_OUTAGE',     // Unplanned
                'EVENT',            // Pop-up, wedding, photoshoot
                'OTHER',            // Catch-all (notes will explain)
            ])->default('HOLIDAY');

            // ── Behaviour flags ───────────────────────────────────────────────
            $table->boolean('is_closed')->default(true);   // true = closed all day; false = custom hours below
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();

            // ── Repeats ───────────────────────────────────────────────────────
            $table->boolean('recurring_yearly')->default(false);  // e.g. Christmas every Dec 25

            // ── Standard timestamps + ownership ──────────────────────────────
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
    }
};

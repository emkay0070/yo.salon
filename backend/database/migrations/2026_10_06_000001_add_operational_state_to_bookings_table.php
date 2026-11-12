<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add operational state tracking to bookings table.
     *
     * This migration adds the foundation for the three-layer booking lifecycle:
     * - booking.status: authoritative lifecycle state (pending, confirmed, arrived, etc.)
     * - booking.operational_state: persisted current operational state (upcoming, due_soon, grace_period, needs_resolution, resolved)
     * - grace_period timestamps: for tracking when grace period starts/ends
     *
     * The operational state is persisted by the scheduler, not calculated by the UI.
     * This ensures yesterday's bookings remain queryable and resolvable.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Operational state: time-derived state persisted by scheduler
            $table->string('operational_state')->default('upcoming')->after('status');
            
            // Grace period tracking
            $table->timestamp('grace_period_started_at')->nullable()->after('operational_state');
            $table->timestamp('grace_period_ends_at')->nullable()->after('grace_period_started_at');
            
            // Index for scheduler queries
            $table->index('operational_state');
            $table->index(['operational_state', 'date', 'time']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['operational_state', 'date', 'time']);
            $table->dropIndex('operational_state');
            $table->dropColumn(['operational_state', 'grace_period_started_at', 'grace_period_ends_at']);
        });
    }
};

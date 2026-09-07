<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Immutable, append-only audit log of every resource consumption event.
     * This is separate from the `usage` table which tracks aggregated running totals.
     *
     * `usage`        = "How much has been used vs. the limit?" (aggregate counter)
     * `usage_events` = "What was consumed, by whom, when, and why?" (audit log)
     *
     * IMPORTANT: Never update or delete rows from this table.
     * Add a model-level guard to enforce this.
     */
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');            // the salon/provider consuming the resource
            $table->uuid('subscription_id')->nullable(); // optional — if tied to a subscription
            $table->string('resource_code');        // e.g. 'SMS', 'AI_REQUEST'
            $table->integer('units')->default(1);   // how many units consumed in this event
            $table->json('metadata')->nullable();   // context: booking_id, customer_id, model, etc.
            $table->timestamp('occurred_at');       // when the consumption actually happened
            $table->timestamps();

            $table->index('provider_id');
            $table->index('resource_code');
            $table->index('subscription_id');
            $table->index('occurred_at');
            $table->index(['provider_id', 'resource_code', 'occurred_at'], 'usage_events_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};

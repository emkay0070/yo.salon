<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earnings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Who earned it — any compensatable entity
            $table->string('compensatable_type');
            $table->uuid('compensatable_id');

            // What caused this earning:
            //   commission/per_booking → App\Models\Booking
            //   salary/base            → App\Domain\Finance\Compensation\CompensationPeriod
            $table->string('source_type');
            $table->uuid('source_id');

            // Policy snapshot — locked at time of earning creation.
            // Policy changes do NOT retroactively alter past earnings.
            $table->uuid('compensation_policy_id');

            // Set when this Earning is included in a period (period-based schedules)
            $table->uuid('compensation_period_id')->nullable();

            // Set only for 'immediate' schedule — convenience FK to Settlement
            $table->unsignedBigInteger('settlement_id')->nullable();

            $table->decimal('gross_amount', 12, 2);
            $table->string('currency')->default('UGX');

            // State machine:
            //   earned → included (period) → payable (settlement created) → paid (payout done)
            //   earned → payable (immediate) → paid
            //   earned|included → voided
            $table->enum('status', [
                'earned',
                'included',
                'payable',
                'paid',
                'voided',
            ])->default('earned');

            // Calculation trace: rate applied, basis, breakdown
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('compensation_policy_id')
                ->references('id')->on('compensation_policies')
                ->restrictOnDelete();

            $table->foreign('settlement_id')
                ->references('id')->on('settlements')
                ->nullOnDelete();

            // Indexes
            $table->index(['compensatable_type', 'compensatable_id']);
            $table->index(['source_type', 'source_id']);
            $table->index('compensation_period_id');
            $table->index('settlement_id');
            $table->index('status');
            $table->index('compensation_policy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earnings');
    }
};

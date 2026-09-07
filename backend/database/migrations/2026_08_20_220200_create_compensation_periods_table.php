<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensation_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The payee this period belongs to
            $table->string('compensatable_type');
            $table->uuid('compensatable_id');

            // The salon / provider
            $table->string('provider_type');
            $table->uuid('provider_id');

            // Policy snapshot at time of period opening
            $table->uuid('compensation_policy_id');

            // Date range this period covers
            $table->date('period_start');
            $table->date('period_end');

            // Financials (recomputed as earnings/adjustments are added)
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('adjustments_total', 12, 2)->default(0); // signed
            $table->decimal('payable_amount', 12, 2)->default(0);    // gross + adjustments
            $table->string('currency')->default('UGX');

            // State machine:
            //   open      = accumulating earnings + adjustments
            //   closing   = being finalized (payable_amount locked, Settlement being created)
            //   closed    = Settlement exists, obligation on record, money not yet paid
            //   settled   = all Payouts completed, money actually received
            //   voided    = cancelled (no earnings, staff terminated with nothing due)
            $table->enum('status', [
                'open',
                'closing',
                'closed',
                'settled',
                'voided',
            ])->default('open');

            // Set when period closes and Settlement is created
            $table->unsignedBigInteger('settlement_id')->nullable();

            // Audit: who closed this period (null = auto-closed by PeriodClosingService)
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();

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
            $table->index(['provider_type', 'provider_id']);
            $table->index('compensation_policy_id');
            $table->index('status');
            $table->index('period_start');
            $table->index('period_end');

            // One open period per payee+provider+date-range (prevents duplicates)
            $table->unique([
                'compensatable_type',
                'compensatable_id',
                'provider_type',
                'provider_id',
                'period_start',
                'period_end',
            ], 'unique_compensation_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_periods');
    }
};

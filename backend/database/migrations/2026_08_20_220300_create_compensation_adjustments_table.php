<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensation_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The payee receiving (or having deducted) this adjustment
            $table->string('compensatable_type');
            $table->uuid('compensatable_id');

            // The period this adjustment applies to (nullable for standalone adjustments)
            $table->uuid('compensation_period_id')->nullable();

            // Adjustment type
            $table->enum('type', [
                'bonus',
                'tip',
                'allowance',
                'deduction',
                'penalty',
            ]);

            // Signed amount:
            //   bonus / tip / allowance → positive
            //   deduction / penalty     → negative
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('UGX');

            // Required — manager must document why
            $table->string('description');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Foreign key to period
            $table->foreign('compensation_period_id')
                ->references('id')->on('compensation_periods')
                ->nullOnDelete();

            // Indexes
            $table->index(['compensatable_type', 'compensatable_id']);
            $table->index('compensation_period_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_adjustments');
    }
};

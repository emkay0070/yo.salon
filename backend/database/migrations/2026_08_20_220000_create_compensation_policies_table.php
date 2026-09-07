<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensation_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The payee — any compensatable entity (Specialist, User, etc.)
            $table->string('compensatable_type');
            $table->uuid('compensatable_id');

            // The salon / provider that owns this policy
            $table->string('provider_type');
            $table->uuid('provider_id');

            // Optional: link to specialist_assignments when payee is a specialist.
            // Allows the same specialist to have different rates at different salons.
            $table->uuid('assignment_id')->nullable();

            // Compensation type
            $table->enum('type', [
                'commission',
                'salary',
                'per_booking',
                'fixed',
                'hybrid',
            ]);

            // JSON rules — format varies by type:
            //   commission/per_booking: [{ "service_category": "haircut", "rate": 0.40, "basis": "service_revenue" }]
            //   salary/fixed:           [{ "amount": 650000, "currency": "UGX" }]
            //   hybrid:                 [{ "type": "base", "amount": 300000 }, { "type": "commission", "rate": 0.20 }]
            $table->json('rules');

            // When earnings become a Settlement obligation
            $table->enum('settlement_frequency', [
                'immediate',
                'weekly',
                'biweekly',
                'monthly',
            ])->default('monthly');

            // Manager control flags
            $table->boolean('auto_create_earnings')->default(true);
            $table->boolean('auto_close_periods')->default(true);
            $table->boolean('manager_approval_required')->default(false);

            // Time-bounded — policy changes preserve full history
            $table->date('effective_from');
            $table->date('effective_until')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['compensatable_type', 'compensatable_id']);
            $table->index(['provider_type', 'provider_id']);
            $table->index('assignment_id');
            $table->index('type');
            $table->index('settlement_frequency');
            $table->index('is_active');
            $table->index('effective_from');
            $table->index('effective_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_policies');
    }
};

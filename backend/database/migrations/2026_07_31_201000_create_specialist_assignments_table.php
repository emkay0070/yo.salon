<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Runs AFTER providers (190000), specialists (144354+190200), and salons (164127+190100).
     */
    public function up(): void
    {
        Schema::create('specialist_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('specialist_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('salon_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('role')->default('SPECIALIST'); // OWNER, MANAGER, SENIOR_SPECIALIST, SPECIALIST, TRAINEE
            $table->string('employment_type')->default('EMPLOYEE'); // EMPLOYEE, FREELANCER, CONTRACTOR
            $table->boolean('is_primary')->default(true);

            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();

            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status')->default('ACTIVE'); // ACTIVE, INACTIVE, TERMINATED
            $table->text('notes')->nullable();

            $table->json('services')->nullable();

            $table->timestamps();

            $table->index(['salon_id', 'status']);
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialist_assignments');
    }
};
